<?php

namespace JLG\Sidebar\Settings;

final class ValueNormalizer
{
    private const ALLOWED_UNITS = ['px', 'rem', 'em', '%', 'vh', 'vw', 'vmin', 'vmax', 'ch'];

    /**
     * @var array{numeric_pattern:string,dimension_pattern:string}|null
     */
    private static ?array $dimensionPatterns = null;

    /**
     * @param mixed $value
     * @param mixed $fallback
     */
    public static function normalizeCssDimension($value, $fallback): string
    {
        $fallback = is_string($fallback) || is_numeric($fallback) ? (string) $fallback : '';
        $sanitizedFallback = sanitize_text_field($fallback);

        $value = is_string($value) || is_numeric($value) ? (string) $value : '';
        $value = trim($value);

        if ($value === '') {
            return $sanitizedFallback;
        }

        $value = sanitize_text_field($value);

        $patterns = self::getDimensionPatterns();
        $numericPattern = $patterns['numeric_pattern'];
        $dimensionPattern = $patterns['dimension_pattern'];

        if (preg_match($numericPattern, $value)) {
            return $value;
        }

        if (self::isValidCalcExpression($value, $dimensionPattern)) {
            return $value;
        }

        if (preg_match('/^0(?:\.0+)?$/', $value)) {
            return '0';
        }

        return $sanitizedFallback;
    }

    /**
     * @param mixed $value
     * @param mixed $existingValue
     */
    public static function normalizeColorWithExisting($value, $existingValue): string
    {
        $existingValue = (is_string($existingValue) || is_numeric($existingValue))
            ? (string) $existingValue
            : '';

        $candidate = $value;
        if ($candidate === null) {
            $candidate = $existingValue;
        }

        $sanitizedCandidate = self::sanitizeRgbaColor($candidate);
        if ($sanitizedCandidate !== '') {
            return $sanitizedCandidate;
        }

        $sanitizedExisting = self::sanitizeRgbaColor($existingValue);
        if ($sanitizedExisting !== '') {
            return $sanitizedExisting;
        }

        return '';
    }

    /**
     * @param mixed $color
     */
    private static function sanitizeRgbaColor($color): string
    {
        if (empty($color) || is_array($color)) {
            return '';
        }

        $color = trim((string) $color);

        if (0 !== stripos($color, 'rgba')) {
            $sanitizedHex = sanitize_hex_color($color);
            return $sanitizedHex ? $sanitizedHex : '';
        }

        $pattern = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+|1\.0+)\s*\)$/i';

        if (!preg_match($pattern, $color, $matches)) {
            return '';
        }

        $r = (int) $matches[1];
        $g = (int) $matches[2];
        $b = (int) $matches[3];
        $aValue = (float) $matches[4];

        foreach ([$r, $g, $b] as $component) {
            if ($component < 0 || $component > 255) {
                return '';
            }
        }

        if ($aValue < 0 || $aValue > 1) {
            return '';
        }

        $alpha = $matches[4];

        if ('.' === substr($alpha, 0, 1)) {
            $alpha = '0' . $alpha;
        }

        $alpha = rtrim($alpha, '0');
        $alpha = rtrim($alpha, '.');

        if ('' === $alpha) {
            $alpha = '0';
        }

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha);
    }

    private static function getDimensionPatterns(): array
    {
        if (self::$dimensionPatterns !== null) {
            return self::$dimensionPatterns;
        }

        $unitPattern = '(?:' . implode('|', array_map(static function ($unit) {
            return preg_quote($unit, '/');
        }, self::ALLOWED_UNITS)) . ')';

        self::$dimensionPatterns = [
            'numeric_pattern'   => '/^-?(?:\d+|\d*\.\d+)(?:' . $unitPattern . ')$/i',
            'dimension_pattern' => '/^[-+]?(?:\d+|\d*\.\d+)(?:' . $unitPattern . ')?$/i',
        ];

        return self::$dimensionPatterns;
    }

    private static function isValidCalcExpression(string $value, string $dimensionPattern): bool
    {
        if (!preg_match('/^calc\((.*)\)$/i', $value, $matches)) {
            return false;
        }

        $expression = trim($matches[1]);

        if ($expression === '') {
            return false;
        }

        if (!preg_match('/^[0-9+\-*\/().%a-z\s]+$/i', $expression)) {
            return false;
        }

        $expression = preg_replace('/\s+/', '', $expression);

        if ($expression === '') {
            return false;
        }

        $length = strlen($expression);
        $tokens = [];
        $current = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if (strpos('+-*/()', $char) !== false) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                $tokens[] = $char;
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        $balance = 0;
        $prevToken = '';

        foreach ($tokens as $token) {
            if ($token === '(') {
                $balance++;
            } elseif ($token === ')') {
                $balance--;

                if ($balance < 0) {
                    return false;
                }
            }

            if (in_array($token, ['+', '-', '*', '/'], true)) {
                if ($prevToken === '' || in_array($prevToken, ['+', '-', '*', '/', '('], true)) {
                    return false;
                }
            } elseif (!in_array($token, ['(', ')'], true)) {
                if (!preg_match($dimensionPattern, $token) && !preg_match('/^\d+(?:\.\d+)?$/', $token)) {
                    return false;
                }
            }

            $prevToken = $token;
        }

        return $balance === 0 && !in_array($prevToken, ['+', '-', '*', '/'], true);
    }
}
