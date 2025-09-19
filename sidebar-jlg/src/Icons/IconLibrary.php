<?php

namespace JLG\Sidebar\Icons;

class IconLibrary
{
    private ?array $allIcons = null;
    private array $rejectedCustomIcons = [];
    private array $customIconSources = [];
    private string $pluginDir;

    public function __construct(string $pluginFile)
    {
        $this->pluginDir = plugin_dir_path($pluginFile);
    }

    public function getAllIcons(): array
    {
        if ($this->allIcons !== null) {
            return $this->allIcons;
        }

        $standard = $this->loadStandardIcons();
        $custom = $this->loadCustomIcons();

        $this->allIcons = array_merge($standard, $custom);

        return $this->allIcons;
    }

    public function getCustomIconSource(string $iconKey): ?array
    {
        $this->getAllIcons();

        return $this->customIconSources[$iconKey] ?? null;
    }

    public function consumeRejectedCustomIcons(): array
    {
        $icons = array_unique($this->rejectedCustomIcons);
        sort($icons, SORT_STRING);
        $this->rejectedCustomIcons = [];

        return $icons;
    }

    private function loadStandardIcons(): array
    {
        $iconsFile = $this->pluginDir . 'assets/icons/standard-icons.php';

        if (file_exists($iconsFile)) {
            $icons = require $iconsFile;
            return is_array($icons) ? $icons : [];
        }

        return [];
    }

    private function loadCustomIcons(): array
    {
        $uploadDir = wp_upload_dir();
        $iconsDir = trailingslashit($uploadDir['basedir']) . 'sidebar-jlg/icons/';

        if (!is_dir($iconsDir) || !is_readable($iconsDir)) {
            return [];
        }

        $maxFileSize = 200 * 1024;
        $files = scandir($iconsDir);

        if (!is_array($files)) {
            return [];
        }

        $allowedMimes = ['svg' => 'image/svg+xml'];
        $customIcons = [];
        $this->customIconSources = [];
        $this->rejectedCustomIcons = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || strpos($file, '.') === 0) {
                continue;
            }

            $filePath = $iconsDir . $file;
            if (!is_file($filePath) || !is_readable($filePath)) {
                continue;
            }

            $fileType = wp_check_filetype($filePath, $allowedMimes);
            if (empty($fileType['ext']) || $fileType['ext'] !== 'svg' || empty($fileType['type'])) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $fileSize = filesize($filePath);
            if ($fileSize === false || $fileSize > $maxFileSize) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $rawContents = file_get_contents($filePath);
            if ($rawContents === false) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $sanitizedContents = wp_kses($rawContents, $this->getAllowedSvgElements());
            if (empty($sanitizedContents)) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $normalizedOriginal = $this->normalizeSvgContent($rawContents);
            $normalizedSanitized = $this->normalizeSvgContent($sanitizedContents);
            if ($normalizedOriginal === '' || $normalizedOriginal !== $normalizedSanitized) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $iconKey = sanitize_key(pathinfo($file, PATHINFO_FILENAME));
            if ($iconKey === '') {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $iconName = 'custom_' . $iconKey;
            $relativePath = 'sidebar-jlg/icons/' . $file;
            $encodedFile = rawurlencode($file);

            $this->customIconSources[$iconName] = [
                'relative_path' => $relativePath,
                'encoded_filename' => $encodedFile,
                'url' => trailingslashit($uploadDir['baseurl']) . 'sidebar-jlg/icons/' . $encodedFile,
            ];

            $customIcons[$iconName] = $sanitizedContents;
        }

        return $customIcons;
    }

    private function getAllowedSvgElements(): array
    {
        static $allowed = null;

        if ($allowed !== null) {
            return $allowed;
        }

        $commonAttributes = [
            'class' => true,
            'id' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'stroke-miterlimit' => true,
            'stroke-dasharray' => true,
            'stroke-dashoffset' => true,
            'fill-opacity' => true,
            'stroke-opacity' => true,
            'fill-rule' => true,
            'clip-rule' => true,
            'opacity' => true,
            'transform' => true,
            'data-name' => true,
            'focusable' => true,
        ];

        $allowed = [
            'svg' => array_merge(
                $commonAttributes,
                [
                    'xmlns' => true,
                    'xmlns:xlink' => true,
                    'width' => true,
                    'height' => true,
                    'viewBox' => true,
                    'preserveAspectRatio' => true,
                    'aria-hidden' => true,
                    'aria-labelledby' => true,
                    'role' => true,
                    'version' => true,
                    'xml:space' => true,
                ]
            ),
            'g' => $commonAttributes,
            'title' => [],
            'path' => array_merge($commonAttributes, ['d' => true]),
            'circle' => array_merge($commonAttributes, ['cx' => true, 'cy' => true, 'r' => true]),
            'ellipse' => array_merge($commonAttributes, ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true]),
            'rect' => array_merge($commonAttributes, ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true]),
            'line' => array_merge($commonAttributes, ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true]),
            'polyline' => array_merge($commonAttributes, ['points' => true]),
            'polygon' => array_merge($commonAttributes, ['points' => true]),
            'linearGradient' => array_merge($commonAttributes, [
                'gradientUnits' => true,
                'gradientTransform' => true,
                'x1' => true, 'y1' => true,
                'x2' => true,
                'y2' => true,
            ]),
            'radialGradient' => array_merge($commonAttributes, [
                'gradientUnits' => true,
                'gradientTransform' => true,
                'cx' => true,
                'cy' => true,
                'fx' => true,
                'fy' => true,
                'r' => true,
            ]),
            'stop' => array_merge($commonAttributes, [
                'offset' => true,
                'stop-color' => true,
                'stop-opacity' => true,
            ]),
            'defs' => $commonAttributes,
            'clipPath' => array_merge($commonAttributes, ['id' => true, 'clipPathUnits' => true]),
            'mask' => array_merge($commonAttributes, ['id' => true, 'maskUnits' => true, 'maskContentUnits' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true]),
            'symbol' => array_merge($commonAttributes, ['viewBox' => true, 'preserveAspectRatio' => true]),
            'use' => array_merge($commonAttributes, ['x' => true, 'y' => true, 'xlink:href' => true, 'href' => true]),
            'text' => array_merge($commonAttributes, [
                'x' => true,
                'y' => true,
                'dx' => true,
                'dy' => true,
                'text-anchor' => true,
                'font-family' => true,
                'font-size' => true,
                'font-weight' => true,
                'letter-spacing' => true,
                'word-spacing' => true,
            ]),
            'tspan' => array_merge($commonAttributes, [
                'x' => true,
                'y' => true,
                'dx' => true,
                'dy' => true,
                'text-anchor' => true,
            ]),
        ];

        return $allowed;
    }

    private function normalizeSvgContent(string $content): string
    {
        $content = preg_replace('/<\?xml.*?\?>/i', '', $content);
        $content = preg_replace('/<!DOCTYPE.*?>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $content = preg_replace('/\s+/', '', $content ?? '');

        return trim($content ?? '');
    }
}
