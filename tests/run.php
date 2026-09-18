#!/usr/bin/env php
<?php

declare(strict_types=1);

$files = [
    __DIR__.'/settings.php',
    __DIR__.'/likes.php',
    __DIR__.'/sort.php',
    __DIR__.'/layouts.php',
    __DIR__.'/api_contracts.php',
    __DIR__.'/boot.php',
];

$failed = 0;
foreach ($files as $file) {
    echo '== '.basename($file)." ==\n";
    passthru('php '.escapeshellarg($file), $code);
    echo "\n";
    if ($code !== 0) {
        $failed++;
    }
}

echo "== vitest ==\n";
passthru('npm test', $jsCode);
echo "\n";
if ($jsCode !== 0) {
    $failed++;
}

exit($failed === 0 ? 0 : 1);
