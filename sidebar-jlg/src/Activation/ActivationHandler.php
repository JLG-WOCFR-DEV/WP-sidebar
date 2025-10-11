<?php

namespace JLG\Sidebar\Activation;

use JLG\Sidebar\Cache\MenuCache;

class ActivationHandler
{
    private string $version;
    private MenuCache $menuCache;

    public function __construct(string $version, MenuCache $menuCache)
    {
        $this->version = $version;
        $this->menuCache = $menuCache;
    }

    public function handle(): void
    {
        $uploadDir = wp_upload_dir();

        [$baseDir, $errorMessage] = $this->resolveUploadsDirectory($uploadDir);

        if ($baseDir === null) {
            $this->recordFailure('uploads_access_error', $errorMessage, function (?string $message): void {
                if ($message !== null && $message !== '' && function_exists('error_log')) {
                    error_log(sprintf('[Sidebar JLG] Activation skipped: %s', $message));
                }
            });

            return;
        }

        $basePath = function_exists('trailingslashit')
            ? trailingslashit($baseDir)
            : rtrim($baseDir, "\\/") . '/';
        $iconsDir = $basePath . 'sidebar-jlg/icons/';

        if (!$this->ensureDirectoryExists($iconsDir)) {
            $this->recordFailure('icons_directory_creation_failed', null, static function (): void {
                if (function_exists('error_log')) {
                    error_log('[Sidebar JLG] Activation failed: unable to create icons directory.');
                }
            });

            return;
        }

        delete_option('sidebar_jlg_pending_maintenance');
        update_option('sidebar_jlg_plugin_version', $this->version);

        $this->menuCache->clear();
    }

    /**
     * @param mixed $uploadDir
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveUploadsDirectory($uploadDir): array
    {
        if (!is_array($uploadDir)) {
            return [null, null];
        }

        $baseDirValue = $uploadDir['basedir'] ?? '';
        $baseDir = is_string($baseDirValue) && $baseDirValue !== '' ? $baseDirValue : null;

        $errorValue = $uploadDir['error'] ?? null;
        $errorMessage = $this->normalizeUploadDirError($errorValue);

        if ($baseDir === null || $errorMessage !== null) {
            return [null, $errorMessage];
        }

        return [$baseDir, null];
    }

    /**
     * @param mixed $error
     */
    private function normalizeUploadDirError($error): ?string
    {
        if ($error === null || $error === '' || $error === false) {
            return null;
        }

        if (function_exists('is_wp_error') && is_wp_error($error)) {
            $message = $error->get_error_message();

            if (!is_string($message)) {
                return null;
            }

            $message = trim($message);

            return $message === '' ? null : $message;
        }

        if (is_string($error)) {
            $message = trim($error);

            return $message === '' ? null : $message;
        }

        return null;
    }

    private function ensureDirectoryExists(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (function_exists('wp_mkdir_p')) {
            return (bool) wp_mkdir_p($path);
        }

        return (bool) @mkdir($path, 0777, true);
    }

    /**
     * @param callable|null $logger
     */
    private function recordFailure(string $code, ?string $details, ?callable $logger = null): void
    {
        if ($logger !== null) {
            $logger($details);
        }

        if (!function_exists('set_transient')) {
            return;
        }

        $expiration = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;

        $payload = [
            'code' => $code,
            'details' => $details !== null ? $details : '',
        ];

        set_transient('sidebar_jlg_activation_error', $payload, $expiration);
    }
}
