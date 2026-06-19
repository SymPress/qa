<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Support\ConfigDiscovery;

final class ConfigDiscoveryTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-qa-config-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testFirstExistingFileReturnsFirstReadableCandidate(): void
    {
        file_put_contents($this->workspace . '/phpstan.neon.dist', 'parameters: []');
        file_put_contents($this->workspace . '/phpstan.neon', 'parameters: []');

        $configDiscovery = new ConfigDiscovery();

        self::assertSame('phpstan.neon', $configDiscovery->firstExistingFile(
            $this->workspace,
            ['phpstan.neon', 'phpstan.neon.dist'],
        ));
    }

    public function testReadComposerReturnsStringKeyedComposerJson(): void
    {
        file_put_contents($this->workspace . '/composer.json', json_encode([
            'name'    => 'sympress/example',
            'scripts' => ['qa' => '@cs'],
        ], JSON_THROW_ON_ERROR));

        $composer = (new ConfigDiscovery())->readComposer($this->workspace);

        self::assertIsArray($composer);
        self::assertSame('sympress/example', $composer['name']);
        self::assertSame(['qa' => '@cs'], $composer['scripts']);
    }

    public function testInvalidComposerJsonReturnsNull(): void
    {
        file_put_contents($this->workspace . '/composer.json', '{');

        self::assertNull((new ConfigDiscovery())->readComposer($this->workspace));
    }

    public function testAutoloadUsageIsDetectedFromConfigContents(): void
    {
        file_put_contents($this->workspace . '/phpstan.neon', <<<'NEON'
parameters:
    bootstrapFiles:
        - vendor/autoload.php
NEON);
        file_put_contents($this->workspace . '/phpunit.xml.dist', <<<'XML'
<phpunit bootstrap="../../vendor/autoload.php" />
XML);

        $configDiscovery = new ConfigDiscovery();

        self::assertTrue($configDiscovery->usesLocalVendorAutoload($this->workspace, 'phpstan.neon'));
        self::assertFalse($configDiscovery->usesProjectVendorAutoload($this->workspace, 'phpstan.neon'));
        self::assertTrue($configDiscovery->usesProjectVendorAutoload($this->workspace, 'phpunit.xml.dist'));
    }

    public function testMissingLocalSymPressDependenciesIgnoresQaAndCodingStandards(): void
    {
        file_put_contents($this->workspace . '/composer.json', json_encode([
            'require'     => [
                'sympress/kernel' => 'dev-main',
                'sympress/qa'     => 'dev-main',
            ],
            'require-dev' => [
                'sympress/coding-standards' => 'dev-main',
            ],
        ], JSON_THROW_ON_ERROR));

        $missing = (new ConfigDiscovery())->missingLocalSymPressDependencies($this->workspace);

        self::assertSame(['sympress/kernel'], $missing);
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
