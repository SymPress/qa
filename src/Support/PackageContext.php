<?php

declare(strict_types=1);

namespace SymPress\Qa\Support;

final class PackageContext
{
    public function __construct(
        private readonly string $packageDir,
        private readonly string $projectDir,
    ) {
    }

    public function packageDir(): string
    {
        return $this->packageDir;
    }

    public function projectDir(): string
    {
        return $this->projectDir;
    }

    public function relativePackageDir(): string
    {
        $baseDir = rtrim($this->projectDir, '/') . '/';

        if (str_starts_with($this->packageDir, $baseDir)) {
            return substr($this->packageDir, strlen($baseDir));
        }

        return $this->packageDir;
    }
}
