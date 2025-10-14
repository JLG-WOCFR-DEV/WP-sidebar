<?php

namespace JLG\Sidebar\Analytics;

use function array_filter;
use function array_values;
use function explode;
use function get_transient;
use function is_numeric;
use function is_string;
use function md5;
use function set_transient;
use function time;
use function trim;
use function wp_cache_get;
use function wp_cache_set;
use function wp_using_ext_object_cache;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use function filter_var;

class EventRateLimiter
{
    private const TRANSIENT_PREFIX = 'sidebar_jlg_event_rate_';
    private const CACHE_GROUP = 'sidebar_jlg';

    private int $maxEvents;
    private int $windowSeconds;
    private bool $useTransientStorage;

    public function __construct(int $maxEvents = 20, int $windowSeconds = 300)
    {
        $this->maxEvents = max(1, $maxEvents);
        $this->windowSeconds = max(1, $windowSeconds);
        $this->useTransientStorage = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public function registerHit(string $nonce, ?string $ip = null): bool
    {
        $ipAddress = $ip !== null ? $ip : $this->resolveClientIp();
        $ipAddress = $ipAddress !== '' ? $ipAddress : 'unknown';
        $nonceKey = trim($nonce);

        if ($nonceKey === '') {
            $nonceKey = 'anonymous';
        }

        $storageKey = $this->buildStorageKey($ipAddress, $nonceKey);
        $hits = $this->readHits($storageKey);
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        $hits = array_values(array_filter($hits, static function ($timestamp) use ($windowStart) {
            if (is_int($timestamp)) {
                return $timestamp >= $windowStart;
            }

            if (is_numeric($timestamp)) {
                return (int) $timestamp >= $windowStart;
            }

            return false;
        }));

        $hits[] = $now;
        $this->persistHits($storageKey, $hits);

        return count($hits) > $this->maxEvents;
    }

    private function resolveClientIp(): string
    {
        $candidates = [];

        if (isset($_SERVER['HTTP_CLIENT_IP']) && is_string($_SERVER['HTTP_CLIENT_IP'])) {
            $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($forwarded as $entry) {
                $trimmed = trim($entry);
                if ($trimmed !== '') {
                    $candidates[] = $trimmed;
                }
            }
        }

        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = $_SERVER['REMOTE_ADDR'];
        }

        foreach ($candidates as $candidate) {
            $validated = filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($validated !== false) {
                return $validated;
            }
        }

        foreach ($candidates as $candidate) {
            $validated = filter_var($candidate, FILTER_VALIDATE_IP);
            if ($validated !== false) {
                return $validated;
            }
        }

        return '';
    }

    private function buildStorageKey(string $ip, string $nonce): string
    {
        return self::TRANSIENT_PREFIX . md5($ip . '|' . $nonce);
    }

    /**
     * @return array<int, int>
     */
    private function readHits(string $key): array
    {
        $stored = $this->useTransientStorage
            ? get_transient($key)
            : wp_cache_get($key, self::CACHE_GROUP);

        if (!is_array($stored)) {
            return [];
        }

        $timestamps = [];
        foreach ($stored as $timestamp) {
            if (is_int($timestamp)) {
                $timestamps[] = $timestamp;
            } elseif (is_numeric($timestamp)) {
                $timestamps[] = (int) $timestamp;
            }
        }

        return $timestamps;
    }

    /**
     * @param array<int, int> $hits
     */
    private function persistHits(string $key, array $hits): void
    {
        if ($this->useTransientStorage) {
            set_transient($key, $hits, $this->windowSeconds);

            return;
        }

        wp_cache_set($key, $hits, self::CACHE_GROUP, $this->windowSeconds);
    }
}
