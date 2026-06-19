<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../coding-standards/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadCandidate) {
    if (!is_readable($autoloadCandidate)) {
        continue;
    }

    require_once $autoloadCandidate;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'SymPress\\Qa\\Tests\\' => __DIR__ . '/',
        'SymPress\\Qa\\'        => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (!is_readable($file)) {
            return;
        }

        require $file;

        return;
    }
});
