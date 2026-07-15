<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Runner;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Runner\ToolRunner;
use SymPress\Qa\Support\ConfigDiscovery;
use SymPress\Qa\Support\PackageContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ToolRunnerTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-qa-runner-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspace);
    }

    public function testUnavailableGateIsVisibleAndStrictModeFails(): void
    {
        $runner = new ToolRunner(new ConfigDiscovery());
        $context = new PackageContext($this->workspace, $this->workspace);
        $output = new BufferedOutput();
        $style = new SymfonyStyle(new ArrayInput([]), $output);

        self::assertSame(0, $runner->run('cs', $context, $style));
        self::assertStringContainsString('SKIP no PHPCS configuration found.', $output->fetch());
        self::assertSame(1, $runner->run('cs', $context, $style, true));
        self::assertStringContainsString('FAILED no PHPCS configuration found.', $output->fetch());
    }

    public function testToolExitCodeIsPropagated(): void
    {
        self::assertTrue(mkdir($this->workspace . '/vendor/bin', 0777, true));
        file_put_contents($this->workspace . '/phpcs.xml', '<ruleset name="test" />');
        file_put_contents($this->workspace . '/vendor/bin/phpcs', '<?php exit(7);');

        $output = new BufferedOutput();
        $exitCode = (new ToolRunner(new ConfigDiscovery()))->run(
            'cs',
            new PackageContext($this->workspace, $this->workspace),
            new SymfonyStyle(new ArrayInput([]), $output),
            true,
        );

        self::assertSame(7, $exitCode);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
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
