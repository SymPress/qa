<?php

declare(strict_types=1);

namespace SymPress\Qa\Support;

use Symfony\Component\Filesystem\Path;

final class PackageContextFactory
{
    public function fromPath(?string $packageDir): PackageContext
    {
        $packageDir = $this->normalizePath($packageDir ?? (string) getcwd());

        return new PackageContext($packageDir, $this->findProjectDir($packageDir));
    }

    private function normalizePath(string $path): string
    {
        $resolved = realpath($path);
        if (is_string($resolved)) {
            return $resolved;
        }

        if (str_starts_with($path, '/')) {
            return rtrim(Path::canonicalize($path), '/');
        }

        return rtrim(Path::canonicalize((string) getcwd() . '/' . $path), '/');
    }

    private function findProjectDir(string $packageDir): string
    {
        $current = $packageDir;

        while ($current !== dirname($current)) {
            if (is_dir($current . '/packages') && is_file($current . '/composer.json')) {
                return $current;
            }

            $current = dirname($current);
        }

        return $packageDir;
    }
}
