<?php

namespace JLG\Sidebar\Accessibility;

use function __;
use function abs;
use function apply_filters;
use function array_map;
use function array_unique;
use function ceil;
use function esc_url_raw;
use function escapeshellarg;
use function escapeshellcmd;
use function explode;
use function fclose;
use function feof;
use function filter_var;
use function floor;
use function function_exists;
use function in_array;
use function ini_get;
use function is_array;
use function is_callable;
use function is_dir;
use function is_executable;
use function is_file;
use function is_resource;
use function is_string;
use function json_decode;
use function max;
use function microtime;
use function preg_match;
use function preg_replace;
use function preg_split;
use function proc_get_status;
use function proc_terminate;
use function shell_exec;
use function sprintf;
use function str_contains;
use function strtolower;
use function strpos;
use function strtoupper;
use function substr;
use function stream_select;
use function stream_set_blocking;
use function trim;
use const FILTER_VALIDATE_URL;
use const PHP_OS;
use const PHP_EOL;

class AuditRunner
{
    private const DEFAULT_MAX_RUNTIME = 60.0;

    private string $pluginFile;
    private string $pluginDir;
    private string $projectRoot;
    private float $maxRuntime;

    public function __construct(string $pluginFile, float $maxRuntime = self::DEFAULT_MAX_RUNTIME)
    {
        $this->pluginFile = $pluginFile;
        $this->pluginDir = dirname($pluginFile);
        $this->projectRoot = $this->resolveProjectRoot($this->pluginDir);
        $this->maxRuntime = $maxRuntime > 0 ? $maxRuntime : self::DEFAULT_MAX_RUNTIME;
    }

    /**
     * @return array{can_run:bool, checks:array<int, array<string, mixed>>, binary: string|null}
     */
    public function getEnvironmentReport(): array
    {
        $checks = [];

        $checks[] = [
            'id' => 'proc_open',
            'label' => __('Fonction PHP proc_open()', 'sidebar-jlg'),
            'passed' => $this->isProcOpenAvailable(),
            'help' => __('L’hébergement doit autoriser la fonction proc_open() pour exécuter des commandes système.', 'sidebar-jlg'),
        ];

        $checks[] = [
            'id' => 'project_root',
            'label' => __('Répertoire du plugin accessible', 'sidebar-jlg'),
            'passed' => is_dir($this->pluginDir),
            'help' => __('Le dossier racine du plugin est introuvable. Vérifiez les permissions du dossier.', 'sidebar-jlg'),
        ];

        $localBinary = $this->findLocalPa11yBinary();
        $globalBinary = $localBinary ? null : $this->findGlobalPa11yBinary();

        $checks[] = [
            'id' => 'pa11y_binary',
            'label' => __('Commande Pa11y disponible', 'sidebar-jlg'),
            'passed' => ( $localBinary || $globalBinary ) ? true : false,
            'help' => __('Installez les dépendances Node du plugin (npm install) ou fournissez un chemin Pa11y personnalisé.', 'sidebar-jlg'),
        ];

        $canRun = true;
        foreach ($checks as $check) {
            if (empty($check['passed'])) {
                $canRun = false;
                break;
            }
        }

        return [
            'can_run' => $canRun,
            'checks' => array_map(
                static function ($check) {
                    $check['passed'] = ! empty($check['passed']);

                    if (isset($check['label']) && is_string($check['label'])) {
                        $check['label'] = $check['label'];
                    }

                    if (isset($check['help']) && is_string($check['help'])) {
                        $check['help'] = $check['help'];
                    }

                    return $check;
                },
                $checks
            ),
            'binary' => $canRun ? ( $localBinary ?: $globalBinary ) : null,
        ];
    }

    public function canRun(): bool
    {
        $report = $this->getEnvironmentReport();

        return ! empty($report['can_run']);
    }

    /**
     * @param string $targetUrl
     * @return array<string, mixed>
     */
    public function run(string $targetUrl): array
    {
        $normalizedUrl = esc_url_raw(trim($targetUrl));
        if ($normalizedUrl === '' || ! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => __('L’URL fournie est invalide. Vérifiez qu’elle commence par http(s)://', 'sidebar-jlg'),
            ];
        }

        if (! $this->isProcOpenAvailable()) {
            return [
                'success' => false,
                'message' => __('La fonction proc_open() est désactivée sur ce serveur. Impossible d’exécuter Pa11y.', 'sidebar-jlg'),
            ];
        }

        $binary = $this->resolvePa11yBinary();
        if (! $binary) {
            return [
                'success' => false,
                'message' => __('La commande Pa11y est introuvable. Installez les dépendances Node ou configurez un chemin personnalisé.', 'sidebar-jlg'),
            ];
        }

        $configPath = $this->findPa11yConfigPath();
        $commandParts = [];
        $commandParts[] = $this->escapeCommand($binary);

        if ($configPath) {
            $commandParts[] = '--config ' . $this->escapeCommand($configPath);
        }

        $commandParts[] = '--reporter json';
        $commandParts[] = $this->escapeCommand($normalizedUrl);

        $command = implode(' ', $commandParts);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $start = microtime(true);
        $process = @proc_open($command, $descriptorSpec, $pipes, $this->projectRoot);

        if (! is_resource($process)) {
            return [
                'success' => false,
                'message' => __('Impossible de lancer Pa11y. Vérifiez les permissions du serveur.', 'sidebar-jlg'),
            ];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
            $pipes[0] = null;
        }

        $stdout = '';
        $stderr = '';
        $timeoutReached = false;

        $streamMap = [];

        if (isset($pipes[1]) && is_resource($pipes[1])) {
            stream_set_blocking($pipes[1], false);
            $streamMap[(int) $pipes[1]] = ['resource' => $pipes[1], 'type' => 'stdout'];
        }

        if (isset($pipes[2]) && is_resource($pipes[2])) {
            stream_set_blocking($pipes[2], false);
            $streamMap[(int) $pipes[2]] = ['resource' => $pipes[2], 'type' => 'stderr'];
        }

        try {
            while ($streamMap !== []) {
                $elapsed = microtime(true) - $start;
                if ($elapsed >= $this->maxRuntime) {
                    $timeoutReached = true;
                    break;
                }

                $readStreams = [];
                foreach ($streamMap as $entry) {
                    if (isset($entry['resource']) && is_resource($entry['resource'])) {
                        $readStreams[] = $entry['resource'];
                    }
                }

                if ($readStreams === []) {
                    break;
                }

                $write = null;
                $except = null;

                $remaining = max(0.0, $this->maxRuntime - $elapsed);
                $seconds = (int) floor($remaining);
                $microseconds = (int) round(($remaining - $seconds) * 1000000);

                if ($seconds === 0 && $microseconds === 0) {
                    $microseconds = 200000; // 200 ms
                }

                $selected = @stream_select($readStreams, $write, $except, $seconds, $microseconds);

                if ($selected === false) {
                    break;
                }

                if ($selected === 0) {
                    $status = proc_get_status($process);
                    if (! ($status['running'] ?? false)) {
                        foreach ($streamMap as $key => $entry) {
                            $resource = $entry['resource'] ?? null;
                            if (is_resource($resource) && feof($resource)) {
                                unset($streamMap[$key]);
                            }
                        }
                    }

                    continue;
                }

                foreach ($readStreams as $stream) {
                    $key = (int) $stream;
                    if (! isset($streamMap[$key])) {
                        continue;
                    }

                    $chunk = '';
                    while (($line = fgets($stream)) !== false) {
                        $chunk .= $line;
                    }

                    if ($chunk !== '') {
                        if (($streamMap[$key]['type'] ?? '') === 'stderr') {
                            $stderr .= $chunk;
                        } else {
                            $stdout .= $chunk;
                        }
                    }

                    if (feof($stream)) {
                        unset($streamMap[$key]);
                    }
                }
            }

            if (! $timeoutReached) {
                foreach ($streamMap as $key => $entry) {
                    $resource = $entry['resource'] ?? null;
                    if (! is_resource($resource)) {
                        continue;
                    }

                    $remaining = stream_get_contents($resource);
                    if ($remaining !== false && $remaining !== '') {
                        if (($entry['type'] ?? '') === 'stderr') {
                            $stderr .= $remaining;
                        } else {
                            $stdout .= $remaining;
                        }
                    }

                    if (feof($resource)) {
                        unset($streamMap[$key]);
                    }
                }
            }
        } finally {
            foreach ($pipes as &$pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }

                $pipe = null;
            }
            unset($pipe);
        }

        $status = proc_get_status($process);

        if ($timeoutReached) {
            if ($status['running'] ?? false) {
                @proc_terminate($process);
            }

            $exitCode = proc_close($process);
            $durationMs = (int) round(abs((microtime(true) - $start) * 1000));

            $logOutput = sprintf(
                'Pa11y interrompu après %.2f secondes (timeout fixé à %.2f secondes).',
                $durationMs / 1000,
                $this->maxRuntime
            );

            $extraOutput = $stderr !== '' ? $stderr : $stdout;
            if ($extraOutput !== '') {
                $logOutput .= PHP_EOL . $extraOutput;
            }

            return [
                'success' => false,
                'message' => sprintf(
                    __('Pa11y a dépassé la durée maximale d’exécution (%d secondes).', 'sidebar-jlg'),
                    (int) ceil($this->maxRuntime)
                ),
                'log' => $this->normalizeLogOutput($logOutput),
                'exit_code' => $exitCode,
                'timeout' => true,
            ];
        }

        $running = $status['running'] ?? false;
        $exitCode = null;

        if ($running) {
            $exitCode = proc_close($process);
        } else {
            $exitCode = $status['exitcode'] ?? null;
            $closeResult = proc_close($process);
            if ($exitCode === null || $exitCode === -1) {
                $exitCode = $closeResult;
            }
        }

        $durationMs = (int) round(abs((microtime(true) - $start) * 1000));

        if ($exitCode !== 0 || ($status['signaled'] ?? false)) {
            return [
                'success' => false,
                'message' => __('Pa11y a retourné une erreur. Consultez les journaux pour plus de détails.', 'sidebar-jlg'),
                'log' => $this->normalizeLogOutput($stderr ?: $stdout),
                'exit_code' => $exitCode,
            ];
        }

        $decoded = json_decode(trim($stdout), true);
        if (! is_array($decoded)) {
            $decoded = $this->attemptJsonRecovery($stdout);
        }

        if (! is_array($decoded)) {
            return [
                'success' => false,
                'message' => __('Impossible d’analyser la sortie JSON de Pa11y.', 'sidebar-jlg'),
                'log' => $this->normalizeLogOutput($stdout . PHP_EOL . $stderr),
            ];
        }

        $issues = [];
        $summary = [
            'error' => 0,
            'warning' => 0,
            'notice' => 0,
        ];

        if (isset($decoded['issues']) && is_array($decoded['issues'])) {
            foreach ($decoded['issues'] as $issue) {
                if (! is_array($issue)) {
                    continue;
                }

                $type = isset($issue['type']) && is_string($issue['type']) ? strtolower($issue['type']) : 'notice';
                if (isset($summary[$type])) {
                    $summary[$type]++;
                }

                $issues[] = [
                    'type' => $type,
                    'code' => isset($issue['code']) && is_string($issue['code']) ? $issue['code'] : '',
                    'message' => isset($issue['message']) && is_string($issue['message']) ? $issue['message'] : '',
                    'selector' => isset($issue['selector']) && is_string($issue['selector']) ? $issue['selector'] : '',
                    'context' => isset($issue['context']) && is_string($issue['context']) ? $issue['context'] : '',
                ];
            }
        }

        return [
            'success' => true,
            'summary' => $summary,
            'issues' => $issues,
            'meta' => [
                'document_title' => isset($decoded['documentTitle']) && is_string($decoded['documentTitle'])
                    ? $decoded['documentTitle']
                    : '',
                'page_url' => isset($decoded['pageUrl']) && is_string($decoded['pageUrl'])
                    ? $decoded['pageUrl']
                    : $normalizedUrl,
                'execution_time_ms' => $durationMs,
                'binary' => $binary,
            ],
            'log' => $this->normalizeLogOutput($stderr),
        ];
    }

    private function resolveProjectRoot(string $pluginDir): string
    {
        $candidates = [$pluginDir];

        $parent = dirname($pluginDir);
        if ($parent !== '' && $parent !== $pluginDir) {
            $candidates[] = $parent;
        }

        $grandParent = dirname($parent);
        if ($grandParent !== '' && $grandParent !== $parent && $grandParent !== $pluginDir) {
            $candidates[] = $grandParent;
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate . '/node_modules/.bin')) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate . '/package.json') && is_dir($candidate . '/node_modules')) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate . '/pa11y.config.json')) {
                return $candidate;
            }
        }

        return $pluginDir;
    }

    /**
     * @return array<int, string>
     */
    private function getSearchDirectories(): array
    {
        $directories = [$this->pluginDir];

        if ($this->projectRoot !== $this->pluginDir) {
            $directories[] = $this->projectRoot;
        }

        $parent = dirname($this->pluginDir);
        if ($parent !== '' && $parent !== $this->pluginDir) {
            $directories[] = $parent;
        }

        $projectParent = dirname($this->projectRoot);
        if ($projectParent !== '' && $projectParent !== $this->projectRoot) {
            $directories[] = $projectParent;
        }

        return array_unique($directories);
    }

    private function findPa11yConfigPath(): ?string
    {
        foreach ($this->getSearchDirectories() as $directory) {
            $configPath = $directory . '/pa11y.config.json';
            if (is_file($configPath)) {
                return $configPath;
            }
        }

        return null;
    }

    private function escapeCommand(string $value): string
    {
        if (str_contains($value, ' ')) {
            return escapeshellarg($value);
        }

        return escapeshellcmd($value);
    }

    private function isProcOpenAvailable(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = ini_get('disable_functions');
        if (! is_string($disabled) || $disabled === '') {
            return true;
        }

        $disabledFunctions = array_map('trim', explode(',', $disabled));

        return ! in_array('proc_open', $disabledFunctions, true);
    }

    private function resolvePa11yBinary(): ?string
    {
        $custom = apply_filters('sidebar_jlg_pa11y_binary', null);
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        $local = $this->findLocalPa11yBinary();
        if ($local) {
            return $local;
        }

        $global = $this->findGlobalPa11yBinary();
        if ($global) {
            return $global;
        }

        return null;
    }

    private function findLocalPa11yBinary(): ?string
    {
        foreach ($this->getSearchDirectories() as $directory) {
            $base = rtrim($directory, '/\\') . '/node_modules/.bin/pa11y';
            $candidates = [
                $base,
                $base . '.cmd',
                $base . '.ps1',
            ];

            foreach ($candidates as $candidate) {
                if (! is_file($candidate)) {
                    continue;
                }

                $isWindowsScript = str_contains($candidate, '.cmd') || str_contains($candidate, '.ps1');
                if ($isWindowsScript || is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function findGlobalPa11yBinary(): ?string
    {
        if (! is_callable('shell_exec')) {
            return null;
        }

        $command = 'pa11y';
        if (! preg_match('/^[a-z0-9._-]+$/i', $command)) {
            return null;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $lookup = $isWindows
            ? sprintf('where %s 2>nul', $command)
            : sprintf('command -v %s 2>/dev/null', escapeshellarg($command));

        $result = shell_exec($lookup);

        if (is_string($result) && trim($result) !== '') {
            return $command;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attemptJsonRecovery(string $output): ?array
    {
        $output = trim($output);
        if ($output === '') {
            return null;
        }

        $firstBrace = strpos($output, '{');
        if ($firstBrace === false) {
            $firstBrace = strpos($output, '[');
        }

        if ($firstBrace === false) {
            return null;
        }

        $jsonCandidate = substr($output, $firstBrace);
        $decoded = json_decode(trim($jsonCandidate), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function normalizeLogOutput(string $log): string
    {
        $log = trim($log);
        if ($log === '') {
            return '';
        }

        $lines = preg_split("/\r?\n/", $log);
        if (! is_array($lines)) {
            return $log;
        }

        $cleanLines = [];
        foreach ($lines as $line) {
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $line);
            $cleanLines[] = trim((string) $cleaned);
        }

        return implode(PHP_EOL, $cleanLines);
    }
}
