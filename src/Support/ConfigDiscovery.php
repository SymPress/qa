<?php

declare(strict_types=1);

namespace SymPress\Qa\Support;

use JsonException;

final class ConfigDiscovery
{
    /** @param list<string> $fileNames */
    public function firstExistingFile(string $packageDir, array $fileNames): ?string
    {
        foreach ($fileNames as $fileName) {
            if (is_readable($packageDir . '/' . $fileName)) {
                return $fileName;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function readComposer(string $packageDir): ?array
    {
        $composerFile = $packageDir . '/composer.json';
        if (!is_readable($composerFile)) {
            return null;
        }

        try {
            $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $this->stringKeyArray($composer);
    }

    public function usesLocalVendorAutoload(string $packageDir, string $config): bool
    {
        $contents = file_get_contents($packageDir . '/' . $config);
        if ($contents === false) {
            return false;
        }

        return preg_match('~(?<![./])vendor/autoload\.php~', $contents) === 1;
    }

    public function usesProjectVendorAutoload(string $packageDir, string $config): bool
    {
        $contents = file_get_contents($packageDir . '/' . $config);
        if ($contents === false) {
            return false;
        }

        return str_contains($contents, '../../vendor/autoload.php');
    }

    /** @return list<string> */
    public function missingLocalSymPressDependencies(string $packageDir): array
    {
        $composer = $this->readComposer($packageDir);
        if ($composer === null) {
            return [];
        }

        $requires = array_merge(
            $this->stringKeyArray($composer['require'] ?? null),
            $this->stringKeyArray($composer['require-dev'] ?? null),
        );
        $missing = [];

        foreach (array_keys($requires) as $packageName) {
            if (!str_starts_with($packageName, 'sympress/')) {
                continue;
            }

            if (in_array($packageName, ['sympress/coding-standards', 'sympress/qa'], true)) {
                continue;
            }

            $vendorName = substr($packageName, strlen('sympress/'));
            if (is_dir($packageDir . '/vendor/sympress/' . $vendorName)) {
                continue;
            }

            $missing[] = $packageName;
        }

        return $missing;
    }

    /** @return array<string, mixed> */
    private function stringKeyArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
