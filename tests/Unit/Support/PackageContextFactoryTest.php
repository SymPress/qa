<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Support\PackageContextFactory;

final class PackageContextFactoryTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-qa-context-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testMonorepoPackageResolvesProjectRootAndRelativePackageDir(): void
    {
        $projectDir = $this->workspace . '/project';
        $packageDir = $projectDir . '/packages/example';

        self::assertTrue(mkdir($packageDir, 0777, true));
        file_put_contents($projectDir . '/composer.json', '{}');

        $context = (new PackageContextFactory())->fromPath($packageDir);

        self::assertSame($packageDir, $context->packageDir());
        self::assertSame($projectDir, $context->projectDir());
        self::assertSame('packages/example', $context->relativePackageDir());
    }

    public function testStandalonePackageUsesPackageDirAsProjectDir(): void
    {
        $packageDir = $this->workspace . '/standalone';

        self::assertTrue(mkdir($packageDir));

        $context = (new PackageContextFactory())->fromPath($packageDir);

        self::assertSame($packageDir, $context->packageDir());
        self::assertSame($packageDir, $context->projectDir());
        self::assertSame($packageDir, $context->relativePackageDir());
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        self::assertIsArray($items);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
