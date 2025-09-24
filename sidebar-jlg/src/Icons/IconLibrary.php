<?php

namespace JLG\Sidebar\Icons;

class IconLibrary
{
    private const CUSTOM_ICON_CACHE_KEY = 'sidebar_jlg_custom_icons_cache';
    private const CUSTOM_ICON_INDEX_OPTION = 'sidebar_jlg_custom_icon_index';
    private const CUSTOM_ICON_CACHE_TTL = 86400;

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

    public function getIconManifest(): array
    {
        $icons = $this->getAllIcons();
        $manifest = [];

        foreach ($icons as $key => $_svg) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $readable = str_replace(['_', '-'], ' ', $key);
            $readable = trim($readable);
            $label = $readable === '' ? $key : ucwords($readable);

            $manifest[] = [
                'key' => $key,
                'label' => $label,
                'is_custom' => strpos($key, 'custom_') === 0,
            ];
        }

        return $manifest;
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
        $this->customIconSources = [];
        $this->rejectedCustomIcons = [];

        $uploadDir = wp_upload_dir();

        if (!is_array($uploadDir)) {
            return [];
        }

        $baseDir = $uploadDir['basedir'] ?? '';
        $errorValue = $uploadDir['error'] ?? null;

        $hasError = false;
        if ($errorValue !== null) {
            if (is_wp_error($errorValue)) {
                $hasError = (string) $errorValue->get_error_message() !== '';
            } elseif (is_string($errorValue) && $errorValue !== '') {
                $hasError = true;
            }
        }

        if (!is_string($baseDir) || $baseDir === '' || $hasError) {
            return [];
        }

        $baseUrl = $uploadDir['baseurl'] ?? '';
        $baseUrl = is_string($baseUrl) ? $baseUrl : '';

        $uploadsContext = $this->createUploadsContext($baseDir, $baseUrl);

        if ($uploadsContext === null) {
            return [];
        }

        $iconsDir = trailingslashit($baseDir) . 'sidebar-jlg/icons/';

        if (!is_dir($iconsDir) || !is_readable($iconsDir)) {
            $this->resetCustomIconCacheArtifacts();

            return [];
        }

        $files = scandir($iconsDir);

        if (!is_array($files)) {
            $this->resetCustomIconCacheArtifacts();

            return [];
        }

        $currentIndex = [];
        $candidateFiles = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || strpos($file, '.') === 0) {
                continue;
            }

            $filePath = $iconsDir . $file;
            if (!is_file($filePath) || !is_readable($filePath)) {
                continue;
            }

            $mtime = filemtime($filePath);
            $size = filesize($filePath);

            $currentIndex[$file] = [
                'mtime' => $mtime === false ? 0 : (int) $mtime,
                'size' => $size === false ? 0 : (int) $size,
            ];

            $candidateFiles[$file] = $filePath;
        }

        ksort($currentIndex);
        ksort($candidateFiles);

        $storedIndex = get_option(self::CUSTOM_ICON_INDEX_OPTION, []);
        if (is_array($storedIndex) && $storedIndex === $currentIndex) {
            $cached = get_transient(self::CUSTOM_ICON_CACHE_KEY);
            if (is_array($cached)
                && isset($cached['icons'], $cached['sources'])
                && is_array($cached['icons'])
                && is_array($cached['sources'])
            ) {
                $this->customIconSources = $cached['sources'];
                $this->rejectedCustomIcons = is_array($cached['rejected'] ?? null) ? $cached['rejected'] : [];

                return $cached['icons'];
            }
        }

        $maxFileSize = 200 * 1024;
        $allowedMimes = ['svg' => 'image/svg+xml'];
        $customIcons = [];

        foreach ($candidateFiles as $file => $filePath) {
            $fileType = wp_check_filetype_and_ext($filePath, $file, $allowedMimes);
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

            $validationResult = $this->validateSanitizedSvg($sanitizedContents, $uploadsContext);
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
                'url' => trailingslashit($baseUrl) . 'sidebar-jlg/icons/' . $encodedFile,
            ];

            $customIcons[$iconName] = $sanitizedContents;
        }

        $cachePayload = [
            'icons' => $customIcons,
            'sources' => $this->customIconSources,
            'rejected' => array_values(array_unique($this->rejectedCustomIcons)),
        ];

        set_transient(self::CUSTOM_ICON_CACHE_KEY, $cachePayload, self::CUSTOM_ICON_CACHE_TTL);
        update_option(self::CUSTOM_ICON_INDEX_OPTION, $currentIndex, 'no');

        return $customIcons;
    }

    private function createUploadsContext(string $baseDir, string $baseUrl): ?array
    {
        $normalizedUploadsDir = $this->normalizePathValue($baseDir);

        if ($normalizedUploadsDir === '') {
            return null;
        }

        $uploadsUrlParts = function_exists('wp_parse_url') ? wp_parse_url($baseUrl) : parse_url($baseUrl);

        if (!is_array($uploadsUrlParts)) {
            return null;
        }

        $uploadsScheme = isset($uploadsUrlParts['scheme']) ? strtolower((string) $uploadsUrlParts['scheme']) : '';
        $uploadsHost = isset($uploadsUrlParts['host']) ? strtolower((string) $uploadsUrlParts['host']) : '';

        if ($uploadsScheme === '' || $uploadsHost === '') {
            return null;
        }

        $uploadsPort = isset($uploadsUrlParts['port']) ? (int) $uploadsUrlParts['port'] : null;
        $basePath = isset($uploadsUrlParts['path']) ? (string) $uploadsUrlParts['path'] : '';
        $normalizedBasePath = $this->normalizePathValue($basePath);
        $normalizedBasePath = rtrim($normalizedBasePath, '/');

        return [
            'base_url' => $baseUrl,
            'normalized_basedir' => $normalizedUploadsDir,
            'url_parts' => $uploadsUrlParts,
            'scheme' => $uploadsScheme,
            'host' => $uploadsHost,
            'port' => $uploadsPort,
            'normalized_base_path' => $normalizedBasePath,
        ];
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

    private function resetCustomIconCacheArtifacts(): void
    {
        delete_transient(self::CUSTOM_ICON_CACHE_KEY);
        delete_option(self::CUSTOM_ICON_INDEX_OPTION);
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
    /**
     * @param array{base_url: string, normalized_basedir: string, url_parts: array, scheme: string, host: string, port: ?int, normalized_base_path: string} $uploadsContext
     */
    private function validateSanitizedSvg(string $sanitizedSvg, array $uploadsContext): ?array
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

                if (!$this->isSafeUseReference($attributeValue, $uploadsContext)) {
                    return null;
                }
            }
        }

        $exportedSvg = $dom->saveXML($dom->documentElement);

        if (!is_string($exportedSvg)) {
            return null;
        }

        $exportedSvg = trim($exportedSvg);

        $result = [
            'svg' => $exportedSvg,
        ];

        if ($this->normalizeSvgContent($sanitizedSvg) !== $this->normalizeSvgContent($exportedSvg)) {
            $result['modified'] = true;
        }

        return $result;
    }

    /**
     * Vérifie qu'une valeur d'attribut href/xlink:href de balise <use> est locale ou provient
     * de la médiathèque WordPress.
     */
    /**
     * @param array{base_url: string, normalized_basedir: string, url_parts: array, scheme: string, host: string, port: ?int, normalized_base_path: string} $uploadsContext
     */
    private function isSafeUseReference(string $value, array $uploadsContext): bool
    {
        if ($value === '') {
            return true;
        }

        if (strpos($value, '#') === 0) {
            return (bool) preg_match('/^#[A-Za-z0-9_][A-Za-z0-9:._-]*$/', $value);
        }

        $uploadsBaseUrlValue = isset($uploadsContext['base_url']) ? (string) $uploadsContext['base_url'] : '';
        $normalizedUploadsDir = isset($uploadsContext['normalized_basedir']) ? (string) $uploadsContext['normalized_basedir'] : '';
        $uploadsUrlParts = $uploadsContext['url_parts'] ?? null;
        $uploadsScheme = isset($uploadsContext['scheme']) ? (string) $uploadsContext['scheme'] : '';
        $uploadsHost = isset($uploadsContext['host']) ? (string) $uploadsContext['host'] : '';
        $uploadsPort = $uploadsContext['port'] ?? null;
        $normalizedBasePath = isset($uploadsContext['normalized_base_path']) ? (string) $uploadsContext['normalized_base_path'] : '';

        if ($uploadsBaseUrlValue === '' || $normalizedUploadsDir === '' || !is_array($uploadsUrlParts) || $uploadsScheme === '' || $uploadsHost === '') {
            return false;
        }

        $referenceParts = function_exists('wp_parse_url') ? wp_parse_url($value) : parse_url($value);

        if (!is_array($uploadsUrlParts) || !is_array($referenceParts)) {
            return false;
        }

        $referenceScheme = isset($referenceParts['scheme']) ? strtolower((string) $referenceParts['scheme']) : '';
        $referenceHost = isset($referenceParts['host']) ? strtolower((string) $referenceParts['host']) : '';
        $referencePort = isset($referenceParts['port']) ? (int) $referenceParts['port'] : null;
        $referencePath = isset($referenceParts['path']) ? (string) $referenceParts['path'] : '';

        if ($referenceScheme === '' && $referenceHost === '') {
            $referenceScheme = $uploadsScheme;
            $referenceHost = $uploadsHost;

            if (!array_key_exists('port', $referenceParts)) {
                $referencePort = $uploadsPort;
            }

            if ($referencePath === '' || strpos($referencePath, '/') !== 0) {
                if ($normalizedBasePath === '') {
                    $referencePath = '/' . ltrim($referencePath, '/');
                } else {
                    $referencePath = $normalizedBasePath . '/' . ltrim($referencePath, '/');
                }
            }
        } else {
            if ($referenceScheme === '' || $referenceHost === '') {
                return false;
            }

            if ($uploadsScheme !== $referenceScheme || $uploadsHost !== $referenceHost) {
                return false;
            }
        }

        $normalizePort = static function (?int $port, string $scheme): ?int {
            if ($port !== null) {
                return $port;
            }

            if ($scheme === 'http') {
                return 80;
            }

            if ($scheme === 'https') {
                return 443;
            }

            return null;
        };

        $normalizedUploadsPort = $normalizePort($uploadsPort, $uploadsScheme);
        $normalizedReferencePort = $normalizePort($referencePort, $referenceScheme);

        if ($normalizedUploadsPort !== $normalizedReferencePort) {
            return false;
        }

        if ($referencePath === '') {
            return false;
        }

        $decodedReferencePath = rawurldecode($referencePath);

        if (preg_match('#(^|/)\.\.(?:/|$)#', $decodedReferencePath)) {
            return false;
        }

        $normalizedReferencePath = $this->normalizePathValue($decodedReferencePath);
        $expectedPrefix = $normalizedBasePath === '' ? '/' : $normalizedBasePath . '/';

        if (strpos($normalizedReferencePath, $expectedPrefix) !== 0) {
            return false;
        }

        $relativePath = substr($normalizedReferencePath, strlen($normalizedBasePath));
        $relativePath = ltrim((string) $relativePath, '/');

        if ($relativePath === '') {
            return false;
        }

        $normalizedUploadsDirWithSlash = trailingslashit($normalizedUploadsDir);
        $resolvedPath = $this->normalizePathValue($normalizedUploadsDirWithSlash . $relativePath);

        return strpos($resolvedPath, $normalizedUploadsDirWithSlash) === 0;
    }

    private function normalizePathValue(string $path): string
    {
        if (function_exists('wp_normalize_path')) {
            return (string) wp_normalize_path($path);
        }

        if ($path === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#/+#', '/', $normalized);

        return is_string($normalized) ? $normalized : '';
    }
}
