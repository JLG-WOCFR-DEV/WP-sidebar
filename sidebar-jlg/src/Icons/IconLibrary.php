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

            $validationResult = $this->validateSanitizedSvg($sanitizedContents, (string) ($uploadDir['baseurl'] ?? ''));
            if ($validationResult === null) {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            $sanitizedContents = $validationResult['svg'];

            $normalizedOriginal = $this->normalizeSvgContent($rawContents);
            $normalizedSanitized = $this->normalizeSvgContent($sanitizedContents);
            if ($normalizedOriginal === '') {
                $this->rejectedCustomIcons[] = $file;
                continue;
            }

            if ($normalizedOriginal !== $normalizedSanitized && empty($validationResult['modified'])) {
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

    /**
     * Analyse un SVG déjà nettoyé via wp_kses et applique des validations supplémentaires.
     *
     * Le SVG est rejeté si l'analyse DOM échoue ou si une balise <use> conserve un lien
     * potentiel vers une ressource non autorisée. Cette méthode centralise les contrôles
     * post-sanitization afin de faciliter l'ajout de nouvelles règles à l'avenir.
     *
     * @return array{
     *     svg: string,
     *     modified?: bool
     * }|null
     */
    private function validateSanitizedSvg(string $sanitizedSvg, string $uploadsBaseUrl): ?array
    {
        $sanitizedSvg = trim($sanitizedSvg);

        if ($sanitizedSvg === '') {
            return null;
        }

        $previousLibxml = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $loaded = $dom->loadXML($sanitizedSvg, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        if ($loaded === false || $dom->documentElement === null) {
            return null;
        }

        $useElements = $dom->getElementsByTagName('use');

        foreach ($useElements as $useElement) {
            foreach (['href', 'xlink:href'] as $attributeName) {
                if (!$useElement->hasAttribute($attributeName)) {
                    continue;
                }

                $attributeValue = trim($useElement->getAttribute($attributeName));

                if ($attributeValue === '') {
                    continue;
                }

                if (!$this->isSafeUseReference($attributeValue, $uploadsBaseUrl)) {
                    return null;
                }
            }
        }

        return [
            'svg' => $sanitizedSvg,
        ];
    }

    /**
     * Vérifie qu'une valeur d'attribut href/xlink:href de balise <use> est locale ou provient
     * de la médiathèque WordPress.
     */
    private function isSafeUseReference(string $value, string $uploadsBaseUrl): bool
    {
        if ($value === '') {
            return true;
        }

        if (strncmp($value, '#', 1) === 0) {
            return (bool) preg_match('/^#[A-Za-z0-9_][A-Za-z0-9:._-]*$/', $value);
        }

        $uploadsInfo = wp_upload_dir();
        $uploadsBaseDir = isset($uploadsInfo['basedir']) ? (string) $uploadsInfo['basedir'] : '';
        $uploadsBaseUrlValue = $uploadsBaseUrl !== ''
            ? $uploadsBaseUrl
            : (isset($uploadsInfo['baseurl']) ? (string) $uploadsInfo['baseurl'] : '');

        if ($uploadsBaseDir === '' || $uploadsBaseUrlValue === '') {
            return false;
        }

        $normalizedUploadsDir = wp_normalize_path($uploadsBaseDir);

        if ($normalizedUploadsDir === '') {
            return false;
        }

        $uploadsUrlParts = wp_parse_url($uploadsBaseUrlValue);
        $referenceParts = wp_parse_url($value);

        if (!is_array($uploadsUrlParts) || !is_array($referenceParts)) {
            return false;
        }

        $uploadsScheme = isset($uploadsUrlParts['scheme']) ? strtolower((string) $uploadsUrlParts['scheme']) : '';
        $uploadsHost = isset($uploadsUrlParts['host']) ? strtolower((string) $uploadsUrlParts['host']) : '';
        $referenceScheme = isset($referenceParts['scheme']) ? strtolower((string) $referenceParts['scheme']) : '';
        $referenceHost = isset($referenceParts['host']) ? strtolower((string) $referenceParts['host']) : '';

        if ($uploadsScheme === '' || $uploadsHost === '' || $referenceScheme === '' || $referenceHost === '') {
            return false;
        }

        if ($uploadsScheme !== $referenceScheme || $uploadsHost !== $referenceHost) {
            return false;
        }

        $uploadsPort = $uploadsUrlParts['port'] ?? null;
        $referencePort = $referenceParts['port'] ?? null;

        if ($uploadsPort !== $referencePort) {
            return false;
        }

        $basePath = isset($uploadsUrlParts['path']) ? (string) $uploadsUrlParts['path'] : '';
        $normalizedBasePath = wp_normalize_path($basePath);
        $normalizedBasePath = rtrim($normalizedBasePath, '/');

        $referencePath = isset($referenceParts['path']) ? (string) $referenceParts['path'] : '';

        if ($referencePath === '') {
            return false;
        }

        $decodedReferencePath = rawurldecode($referencePath);

        if (preg_match('#(^|/)\.\.(?:/|$)#', $decodedReferencePath)) {
            return false;
        }

        $normalizedReferencePath = wp_normalize_path($decodedReferencePath);
        $expectedPrefix = $normalizedBasePath === '' ? '/' : $normalizedBasePath . '/';

        if (strncmp($normalizedReferencePath, $expectedPrefix, strlen($expectedPrefix)) !== 0) {
            return false;
        }

        $relativePath = substr($normalizedReferencePath, strlen($normalizedBasePath));
        $relativePath = ltrim((string) $relativePath, '/');

        if ($relativePath === '') {
            return false;
        }

        $normalizedUploadsDirWithSlash = trailingslashit($normalizedUploadsDir);
        $resolvedPath = wp_normalize_path($normalizedUploadsDirWithSlash . $relativePath);

        return strncmp($resolvedPath, $normalizedUploadsDirWithSlash, strlen($normalizedUploadsDirWithSlash)) === 0;
    }
}
