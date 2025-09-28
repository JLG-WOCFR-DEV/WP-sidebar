<?php

namespace JLG\Sidebar\Icons;

class IconLibrary
{
    private const CUSTOM_ICON_CACHE_KEY = 'sidebar_jlg_custom_icons_cache';
    private const CUSTOM_ICON_INDEX_OPTION = 'sidebar_jlg_custom_icon_index';
    private const CUSTOM_ICON_CACHE_TTL = 86400;
    private const MAX_CUSTOM_ICON_FILES = 200;

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

    /**
     * @param array{base_url: string, normalized_basedir: string, url_parts: array, scheme: string, host: string, port: ?int, normalized_base_path: string}|null $uploadsContext
     * @param array{reason: string, context?: array}|null $failure
     * @return array{svg: string, modified?: true}|null
     */
    public function sanitizeSvgMarkup(string $svgMarkup, ?array $uploadsContext = null, ?array &$failure = null): ?array
    {
        $failure = null;

        $sanitizedContents = wp_kses($svgMarkup, $this->getAllowedSvgElements());

        if (empty($sanitizedContents)) {
            $failure = ['reason' => 'empty_after_sanitize'];

            return null;
        }

        $validationFailure = null;
        $validationResult = $this->validateSanitizedSvg($sanitizedContents, $uploadsContext, $validationFailure);

        if ($validationResult === null) {
            $failure = [
                'reason' => 'validation_failed',
                'context' => ['detail' => $validationFailure],
            ];

            return null;
        }

        $normalizedOriginal = $this->normalizeSvgContent($svgMarkup);

        if ($normalizedOriginal === '') {
            $failure = ['reason' => 'empty_original'];

            return null;
        }

        $sanitizedSvg = $validationResult['svg'];
        $normalizedSanitized = $this->normalizeSvgContent($sanitizedSvg);

        if (
            $normalizedOriginal !== $normalizedSanitized
            && empty($validationResult['modified'])
        ) {
            $failure = ['reason' => 'mismatched_sanitization'];

            return null;
        }

        $result = ['svg' => $sanitizedSvg];

        if (!empty($validationResult['modified'])) {
            $result['modified'] = true;
        }

        return $result;
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
        if (empty($this->rejectedCustomIcons)) {
            return [];
        }

        $messages = [];

        foreach ($this->rejectedCustomIcons as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $file = isset($entry['file']) ? (string) $entry['file'] : '';
            if ($file === '') {
                continue;
            }

            $reasonKey = isset($entry['reason']) && is_string($entry['reason'])
                ? $entry['reason']
                : 'unknown';
            $context = isset($entry['context']) && is_array($entry['context'])
                ? $entry['context']
                : [];

            $messages[] = $this->formatRejectedIconMessage($file, $reasonKey, $context);
        }

        sort($messages, SORT_STRING);
        $this->rejectedCustomIcons = [];

        return $messages;
    }

    private function loadStandardIcons(): array
    {
        $iconsFile = $this->pluginDir . 'assets/icons/standard-icons.php';

        if (!file_exists($iconsFile)) {
            return [];
        }

        $icons = require $iconsFile;

        if (!is_array($icons)) {
            return [];
        }

        $sanitizedIcons = [];

        foreach ($icons as $key => $markup) {
            if (!is_string($key) || $key === '' || !is_string($markup) || $markup === '') {
                continue;
            }

            $sanitizationFailure = null;
            $sanitizationResult = $this->sanitizeSvgMarkup($markup, null, $sanitizationFailure);

            if ($sanitizationResult === null) {
                continue;
            }

            $sanitizedIcons[$key] = $sanitizationResult['svg'];
        }

        return $sanitizedIcons;
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
                $this->rejectedCustomIcons = [];
                if (isset($cached['rejected']) && is_array($cached['rejected'])) {
                    foreach ($cached['rejected'] as $rejectedEntry) {
                        if (is_array($rejectedEntry)) {
                            $this->restoreRejectedCustomIcon($rejectedEntry);
                        }
                    }
                }

                return $cached['icons'];
            }
        }

        $maxFileSize = 200 * 1024;
        $allowedMimes = ['svg' => 'image/svg+xml'];
        $customIcons = [];

        $processedFiles = 0;
        $limitLogged = false;
        $processingLimit = self::MAX_CUSTOM_ICON_FILES;

        foreach ($candidateFiles as $file => $filePath) {
            if ($processedFiles >= $processingLimit) {
                if (!$limitLogged && function_exists('error_log')) {
                    $limitLogged = true;
                    error_log(sprintf(
                        '[Sidebar JLG] Custom icons processing limit reached (%d files). Remaining icons will be skipped.',
                        $processingLimit
                    ));
                }

                $this->recordRejectedCustomIcon($file, 'icon_limit_reached', [
                    'limit' => $processingLimit,
                ]);

                continue;
            }

            $processedFiles++;

            $fileType = wp_check_filetype_and_ext($filePath, $file, $allowedMimes);
            if (empty($fileType['ext']) || $fileType['ext'] !== 'svg' || empty($fileType['type'])) {
                $this->recordRejectedCustomIcon($file, 'invalid_type', [
                    'ext' => $fileType['ext'] ?? '',
                    'type' => $fileType['type'] ?? '',
                ]);
                continue;
            }

            $fileSize = filesize($filePath);
            if ($fileSize === false) {
                $this->recordRejectedCustomIcon($file, 'filesize_unreadable');
                continue;
            }

            if ($fileSize > $maxFileSize) {
                $this->recordRejectedCustomIcon($file, 'file_too_large', [
                    'max_bytes' => $maxFileSize,
                ]);
                continue;
            }

            $rawContents = file_get_contents($filePath);
            if ($rawContents === false) {
                $this->recordRejectedCustomIcon($file, 'read_error');
                continue;
            }

            $sanitizationFailure = null;
            $sanitizationResult = $this->sanitizeSvgMarkup($rawContents, $uploadsContext, $sanitizationFailure);

            if ($sanitizationResult === null) {
                $reasonKey = 'unknown';
                $context = [];

                if (is_array($sanitizationFailure)) {
                    if (!empty($sanitizationFailure['reason'])) {
                        $reasonKey = (string) $sanitizationFailure['reason'];
                    }
                    if (!empty($sanitizationFailure['context']) && is_array($sanitizationFailure['context'])) {
                        $context = $sanitizationFailure['context'];
                    }
                }

                $this->recordRejectedCustomIcon($file, $reasonKey, $context);
                continue;
            }

            $sanitizedContents = $sanitizationResult['svg'];

            $iconKey = sanitize_key(pathinfo($file, PATHINFO_FILENAME));
            if ($iconKey === '') {
                $this->recordRejectedCustomIcon($file, 'empty_icon_key');
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
            'rejected' => array_values($this->rejectedCustomIcons),
        ];

        set_transient(self::CUSTOM_ICON_CACHE_KEY, $cachePayload, self::CUSTOM_ICON_CACHE_TTL);
        update_option(self::CUSTOM_ICON_INDEX_OPTION, $currentIndex, 'no');

        return $customIcons;
    }

    public function recordRejectedCustomIcon(string $file, string $reasonKey, array $context = []): void
    {
        $this->storeRejectedCustomIcon($file, $reasonKey, $context, true);
    }

    private function restoreRejectedCustomIcon(array $entry): void
    {
        if (!isset($entry['file'], $entry['reason'])) {
            return;
        }

        $file = (string) $entry['file'];
        $reason = (string) $entry['reason'];
        $context = isset($entry['context']) && is_array($entry['context']) ? $entry['context'] : [];

        $this->storeRejectedCustomIcon($file, $reason, $context, false);
    }

    private function storeRejectedCustomIcon(string $file, string $reasonKey, array $context, bool $log): void
    {
        $file = (string) $file;
        $reasonKey = (string) $reasonKey;

        if ($file === '' || $reasonKey === '') {
            return;
        }

        $hashSource = $file . '|' . $reasonKey . '|' . serialize($context);
        $hash = md5($hashSource);

        $entry = [
            'file' => $file,
            'reason' => $reasonKey,
        ];

        if ($context !== []) {
            $entry['context'] = $context;
        }

        $alreadyRecorded = isset($this->rejectedCustomIcons[$hash]);

        $this->rejectedCustomIcons[$hash] = $entry;

        if ($log && !$alreadyRecorded && function_exists('error_log')) {
            $message = sprintf(
                '[Sidebar JLG] Rejected SVG "%s": %s',
                $file,
                $this->getRejectionReasonMessage($reasonKey, $context)
            );
            error_log($message);
        }
    }

    private function formatRejectedIconMessage(string $file, string $reasonKey, array $context): string
    {
        $fileLabel = sanitize_text_field($file);
        $reasonMessage = $this->getRejectionReasonMessage($reasonKey, $context);

        if ($reasonMessage === '') {
            return $fileLabel;
        }

        return sprintf('%s (%s)', $fileLabel, $reasonMessage);
    }

    private function getRejectionReasonMessage(string $reasonKey, array $context): string
    {
        switch ($reasonKey) {
            case 'invalid_type':
                $detectedParts = [];
                if (!empty($context['ext'])) {
                    $detectedParts[] = '.' . sanitize_key((string) $context['ext']);
                }
                if (!empty($context['type'])) {
                    $detectedParts[] = sanitize_text_field((string) $context['type']);
                }

                if (empty($detectedParts)) {
                    return __('invalid MIME type or extension', 'sidebar-jlg');
                }

                return sprintf(
                    __('invalid MIME type or extension (%s)', 'sidebar-jlg'),
                    implode(', ', $detectedParts)
                );
            case 'filesize_unreadable':
                return __('file size could not be determined', 'sidebar-jlg');
            case 'file_too_large':
                $maxBytes = isset($context['max_bytes']) ? (int) $context['max_bytes'] : 0;
                if ($maxBytes > 0) {
                    $maxKb = (int) ceil($maxBytes / 1024);

                    return sprintf(
                        __('file exceeds the maximum size of %d KB', 'sidebar-jlg'),
                        max(1, $maxKb)
                    );
                }

                return __('file exceeds the maximum allowed size', 'sidebar-jlg');
            case 'read_error':
                return __('file could not be read', 'sidebar-jlg');
            case 'icon_limit_reached':
                $limit = isset($context['limit']) ? (int) $context['limit'] : 0;
                if ($limit > 0) {
                    return sprintf(
                        __('processing limit reached (maximum of %d icons)', 'sidebar-jlg'),
                        max(1, $limit)
                    );
                }

                return __('processing limit reached for custom icons', 'sidebar-jlg');
            case 'empty_after_sanitize':
                return __('SVG markup was empty after sanitization', 'sidebar-jlg');
            case 'validation_failed':
                $detail = $context['detail'] ?? null;
                $detailCode = '';
                $detailInfo = null;

                if (is_array($detail)) {
                    $detailCode = isset($detail['code']) ? (string) $detail['code'] : '';
                    $detailInfo = $detail['info'] ?? null;
                } elseif (is_string($detail)) {
                    $detailCode = $detail;
                }

                switch ($detailCode) {
                    case 'unsafe_use_reference':
                        $suffix = '';
                        $infoCode = '';
                        if (is_array($detailInfo)) {
                            $infoCode = isset($detailInfo['code']) ? (string) $detailInfo['code'] : '';
                        } elseif (is_string($detailInfo)) {
                            $infoCode = $detailInfo;
                        }

                        switch ($infoCode) {
                            case 'outside_basedir':
                            case 'outside_uploads':
                                $suffix = __(' pointing outside of the uploads directory', 'sidebar-jlg');
                                break;
                            case 'path_traversal':
                                $suffix = __(' using a disallowed traversal path', 'sidebar-jlg');
                                break;
                            case 'invalid_fragment_identifier':
                                $suffix = __(' with an invalid fragment identifier', 'sidebar-jlg');
                                break;
                            case 'invalid_url':
                                $suffix = __(' with an invalid URL', 'sidebar-jlg');
                                break;
                            case 'host_mismatch':
                                $suffix = __(' referencing a different domain', 'sidebar-jlg');
                                break;
                            case 'port_mismatch':
                                $suffix = __(' referencing a different port', 'sidebar-jlg');
                                break;
                            case 'empty_path':
                                $suffix = __(' with an empty path value', 'sidebar-jlg');
                                break;
                            case 'uploads_context_missing':
                                $suffix = __(' because the uploads directory context is unavailable', 'sidebar-jlg');
                                break;
                            case 'empty_relative_path':
                                $suffix = __(' with an empty relative path value', 'sidebar-jlg');
                                break;
                            default:
                                $suffix = '';
                                break;
                        }

                        return __('unsupported use reference found', 'sidebar-jlg') . $suffix;
                    case 'dom_import_failed':
                        return __('SVG markup could not be parsed safely', 'sidebar-jlg');
                    case 'dom_export_failed':
                        return __('SVG markup could not be re-exported after validation', 'sidebar-jlg');
                    case 'empty_markup':
                        return __('SVG markup was empty after sanitization', 'sidebar-jlg');
                    default:
                        return __('failed validation checks', 'sidebar-jlg');
                }
            case 'empty_original':
                return __('original SVG markup was empty', 'sidebar-jlg');
            case 'mismatched_sanitization':
                return __('sanitized SVG differed from the original markup', 'sidebar-jlg');
            case 'empty_icon_key':
                return __('filename produced an empty identifier', 'sidebar-jlg');
            case 'external_svg_url':
                return __('external SVG URLs are not allowed', 'sidebar-jlg');
            case 'unknown':
                return __('was rejected', 'sidebar-jlg');
        }

        return __('was rejected', 'sidebar-jlg');
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
     * @param array{base_url: string, normalized_basedir: string, url_parts: array, scheme: string, host: string, port: ?int, normalized_base_path: string}|null $uploadsContext
     * @param array{code: string, info?: mixed}|null $failureContext
     * @return array{svg: string, modified?: bool}|null
     */
    private function validateSanitizedSvg(string $sanitizedSvg, ?array $uploadsContext, ?array &$failureContext = null): ?array
    {
        $failureContext = null;
        $sanitizedSvg = trim($sanitizedSvg);

        if ($sanitizedSvg === '') {
            $failureContext = ['code' => 'empty_markup'];
            return null;
        }

        $previousLibxml = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $loaded = $dom->loadXML($sanitizedSvg, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        if ($loaded === false || $dom->documentElement === null) {
            $failureContext = ['code' => 'dom_import_failed'];
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

                $useFailure = null;
                if (!$this->isSafeUseReference($attributeValue, $uploadsContext, $useFailure)) {
                    $failureContext = [
                        'code' => 'unsafe_use_reference',
                        'info' => $useFailure,
                    ];
                    return null;
                }
            }
        }

        $exportedSvg = $dom->saveXML($dom->documentElement);

        if (!is_string($exportedSvg)) {
            $failureContext = ['code' => 'dom_export_failed'];
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
     *
     * @param array{base_url: string, normalized_basedir: string, url_parts: array, scheme: string, host: string, port: ?int, normalized_base_path: string}|null $uploadsContext
     * @param array{code: string}|null $failureContext
     */
    private function isSafeUseReference(string $value, ?array $uploadsContext, ?array &$failureContext = null): bool
    {
        $failureContext = null;
        if ($value === '') {
            return true;
        }

        if (strpos($value, '#') === 0) {
            if ((bool) preg_match('/^#[A-Za-z0-9_][A-Za-z0-9:._-]*$/', $value)) {
                return true;
            }

            $failureContext = ['code' => 'invalid_fragment_identifier'];

            return false;
        }

        if ($uploadsContext === null) {
            $failureContext = ['code' => 'uploads_context_missing'];

            return false;
        }

        $uploadsBaseUrlValue = isset($uploadsContext['base_url']) ? (string) $uploadsContext['base_url'] : '';
        $normalizedUploadsDir = isset($uploadsContext['normalized_basedir']) ? (string) $uploadsContext['normalized_basedir'] : '';
        $uploadsUrlParts = $uploadsContext['url_parts'] ?? null;
        $uploadsScheme = isset($uploadsContext['scheme']) ? (string) $uploadsContext['scheme'] : '';
        $uploadsHost = isset($uploadsContext['host']) ? (string) $uploadsContext['host'] : '';
        $uploadsPort = $uploadsContext['port'] ?? null;
        $normalizedBasePath = isset($uploadsContext['normalized_base_path']) ? (string) $uploadsContext['normalized_base_path'] : '';

        if ($uploadsBaseUrlValue === '' || $normalizedUploadsDir === '' || !is_array($uploadsUrlParts) || $uploadsScheme === '' || $uploadsHost === '') {
            $failureContext = ['code' => 'uploads_context_missing'];
            return false;
        }

        $referenceParts = function_exists('wp_parse_url') ? wp_parse_url($value) : parse_url($value);

        if (!is_array($uploadsUrlParts) || !is_array($referenceParts)) {
            $failureContext = ['code' => 'invalid_url'];
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
                $failureContext = ['code' => 'invalid_url'];
                return false;
            }

            if ($uploadsScheme !== $referenceScheme || $uploadsHost !== $referenceHost) {
                $failureContext = ['code' => 'host_mismatch'];
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
            $failureContext = ['code' => 'port_mismatch'];
            return false;
        }

        if ($referencePath === '') {
            $failureContext = ['code' => 'empty_path'];
            return false;
        }

        $decodedReferencePath = rawurldecode($referencePath);

        if (preg_match('#(^|/)\.\.(?:/|$)#', $decodedReferencePath)) {
            $failureContext = ['code' => 'path_traversal'];
            return false;
        }

        $normalizedReferencePath = $this->normalizePathValue($decodedReferencePath);
        $expectedPrefix = $normalizedBasePath === '' ? '/' : $normalizedBasePath . '/';

        if (strpos($normalizedReferencePath, $expectedPrefix) !== 0) {
            $failureContext = ['code' => 'outside_uploads'];
            return false;
        }

        $relativePath = substr($normalizedReferencePath, strlen($normalizedBasePath));
        $relativePath = ltrim((string) $relativePath, '/');

        if ($relativePath === '') {
            $failureContext = ['code' => 'empty_relative_path'];
            return false;
        }

        $normalizedUploadsDirWithSlash = trailingslashit($normalizedUploadsDir);
        $resolvedPath = $this->normalizePathValue($normalizedUploadsDirWithSlash . $relativePath);

        if (strpos($resolvedPath, $normalizedUploadsDirWithSlash) !== 0) {
            $failureContext = ['code' => 'outside_basedir'];

            return false;
        }

        return true;
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
