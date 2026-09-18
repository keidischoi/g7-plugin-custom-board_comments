<?php

declare(strict_types=1);

if (! class_exists(\Illuminate\Database\Eloquent\Model::class)) {
    eval(<<<'PHP'
namespace Illuminate\Database\Eloquent {
    #[\AllowDynamicProperties]
    class Model {}
}
PHP);
}

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Plugins\\G7\\Plugin\\Custom\\BoardComments\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    foreach ([$root.'/src/'.$rel.'.php', $root.'/'.$rel.'.php'] as $file) {
        if (is_file($file)) {
            require_once $file;

            return;
        }
    }
});

$passed = 0;
$failed = 0;

function expect(string $label, mixed $actual, mixed $expected): void
{
    global $passed, $failed;
    $ok = $actual === $expected;
    if ($ok) {
        $passed++;
        echo "ok  {$label}\n";

        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
    echo '      expected: '.var_export($expected, true)."\n";
    echo '      actual:   '.var_export($actual, true)."\n";
}

function expectTrue(string $label, mixed $actual): void
{
    expect($label, (bool) $actual, true);
}

function expectFalse(string $label, mixed $actual): void
{
    expect($label, (bool) $actual, false);
}

function finish(): void
{
    global $passed, $failed;
    echo "\n{$passed} passed, {$failed} failed\n";
    if ($failed > 0) {
        exit(1);
    }
}
