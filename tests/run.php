#!/usr/bin/env php
<?php
declare(strict_types=1);

$testsDirectory = __DIR__;
$pattern = $testsDirectory . DIRECTORY_SEPARATOR . '*_test.php';
$testFiles = glob($pattern);
if ($testFiles === false) {
    fwrite(STDERR, "Unable to enumerate tests using pattern: {$pattern}\n");
    exit(1);
}

sort($testFiles);

$filters = array_slice($argv, 1);
if ($filters !== []) {
    $testFiles = array_values(array_filter(
        $testFiles,
        static function (string $file) use ($filters): bool {
            $filename = basename($file);
            foreach ($filters as $filter) {
                if ($filter === '') {
                    continue;
                }

                if (strpos($filename, $filter) !== false) {
                    return true;
                }
            }

            return false;
        }
    ));
}

if ($testFiles === []) {
    fwrite(STDERR, "No tests matched the provided filters.\n");
    exit(1);
}

$phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$failures = [];

foreach ($testFiles as $file) {
    $relative = ltrim(str_replace($testsDirectory, '', $file), DIRECTORY_SEPARATOR);
    fwrite(STDOUT, "\n› Running {$relative}\n");

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($file);
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        $failures[] = $relative;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\nThe following tests failed:\n");
    foreach ($failures as $failedTest) {
        fwrite(STDERR, " - {$failedTest}\n");
    }

    exit(1);
}

fwrite(STDOUT, "\nAll PHP smoke tests passed.\n");
exit(0);
