<?php
declare(strict_types=1);

use JLG\Sidebar\Accessibility\AuditRunner;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';

$pluginFile = __DIR__ . '/../sidebar-jlg/sidebar-jlg.php';
$timeoutSeconds = 1.0;

$temporaryScript = tempnam(sys_get_temp_dir(), 'sidebar-jlg-pa11y-');
if ($temporaryScript === false) {
    fwrite(STDERR, "[FAIL] Impossible de créer un script temporaire.\n");
    exit(1);
}

$scriptPath = $temporaryScript . '.php';
if (!@rename($temporaryScript, $scriptPath)) {
    $scriptPath = $temporaryScript;
}

$scriptContent = <<<'PHP_SCRIPT'
#!/usr/bin/env php
<?php
declare(strict_types=1);

usleep(3000000);
fwrite(STDOUT, "{\"issues\":[]}\n");
PHP_SCRIPT;

if (file_put_contents($scriptPath, $scriptContent) === false) {
    fwrite(STDERR, "[FAIL] Impossible d'écrire le script temporaire.\n");
    @unlink($scriptPath);
    exit(1);
}

@chmod($scriptPath, 0755);

$GLOBALS['wp_test_function_overrides']['apply_filters'] = static function ($hook, $value) use ($scriptPath) {
    if ($hook === 'sidebar_jlg_pa11y_binary') {
        return $scriptPath;
    }

    return $value;
};

if (!function_exists('assertTrue')) {
    function assertTrue($condition, string $message): void
    {
        if ($condition) {
            return;
        }

        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

if (!function_exists('assertSame')) {
    function assertSame($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            return;
        }

        fwrite(STDERR, sprintf("[FAIL] %s (attendu: %s, obtenu: %s)\n", $message, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack, string $message): void
    {
        if (strpos($haystack, $needle) !== false) {
            return;
        }

        fwrite(STDERR, sprintf("[FAIL] %s (recherche: %s)\n", $message, $needle));
        exit(1);
    }
}

try {
    $runner = new AuditRunner($pluginFile, $timeoutSeconds);
    $result = $runner->run('https://example.com');

    assertSame(false, $result['success'] ?? null, 'Le résultat doit indiquer un échec.');
    assertSame(true, $result['timeout'] ?? null, 'Le résultat doit signaler un dépassement de délai.');
    assertStringContains('Pa11y a dépassé la durée maximale d’exécution', $result['message'] ?? '', 'Le message doit mentionner le timeout.');
    assertStringContains('Pa11y interrompu', $result['log'] ?? '', 'Le journal doit rappeler le timeout.');
    assertTrue(is_int($result['exit_code'] ?? null), 'Le code de sortie doit être un entier.');
} finally {
    unset($GLOBALS['wp_test_function_overrides']['apply_filters']);
    @unlink($scriptPath);
}

printf("[OK] Le dépassement de délai est correctement géré.\n");
