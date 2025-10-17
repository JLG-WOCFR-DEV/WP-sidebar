<?php

namespace JLG\Sidebar\Analytics;

use function apply_filters;
use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function function_exists;
use function get_transient;
use function is_array;
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
        $remoteAddr = $this->readServerValue('REMOTE_ADDR');
        $trustedProxies = $this->getTrustedProxyIps();

        if ($remoteAddr !== '' && $this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($forwarded as $entry) {
                    $trimmed = trim($entry);
                    if ($trimmed !== '') {
                        $candidates[] = $trimmed;
                    }
                }
            }

            $clientIp = $this->readServerValue('HTTP_CLIENT_IP');
            if ($clientIp !== '') {
                $candidates[] = $clientIp;
            }
        }

        if ($remoteAddr !== '') {
            $candidates[] = $remoteAddr;
        }

        if ($candidates !== []) {
            $candidates = array_values(array_unique($candidates));
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

    /**
     * @return string[]
     */
    private function getTrustedProxyIps(): array
    {
        $trusted = [];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sidebar_jlg_trusted_proxy_ips', []);
            if (is_array($filtered)) {
                $trusted = $filtered;
            }
        }

        $normalized = [];
        foreach ($trusted as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param string[] $trusted
     */
    private function isTrustedProxy(string $ipAddress, array $trusted): bool
    {
        foreach ($trusted as $trustedIp) {
            if ($trustedIp === $ipAddress) {
                return true;
            }
        }

        return false;
    }

    private function readServerValue(string $key): string
    {
        if (!isset($_SERVER[$key]) || !is_string($_SERVER[$key])) {
            return '';
        }

        return trim($_SERVER[$key]);
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
