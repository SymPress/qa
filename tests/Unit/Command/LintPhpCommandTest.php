<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Console\ApplicationFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class LintPhpCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-qa-lint space-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->workspace);
        file_put_contents($this->workspace . '/composer.json', '{"name":"sympress/fixture"}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    public function testChecksPublicEntrypointsWithoutExecutingThemAndExcludesDependencies(): void
    {
        foreach (['src', 'public', 'vendor', 'node_modules', 'var', 'build'] as $directory) {
            (new Filesystem())->mkdir($this->workspace . '/' . $directory);
            $php = in_array($directory, ['src', 'public'], true) ? '<?php throw new Exception("Must not execute");' : '<?php syntax error';
            file_put_contents($this->workspace . '/' . $directory . '/index.php', $php);
        }
        $tester = new CommandTester(ApplicationFactory::create()->find('lint:php'));

        self::assertSame(0, $tester->execute(['--package' => $this->workspace]));
        self::assertStringContainsString('PHP syntax checked: 2 files.', $tester->getDisplay());
    }

    public function testReturnsTheInterpreterExitCodeForSyntaxErrors(): void
    {
        $file = $this->workspace . '/broken.php';
        file_put_contents($file, '<?php syntax error');
        $expected = new Process([PHP_BINARY, '-l', $file]);
        $expected->run();
        $tester = new CommandTester(ApplicationFactory::create()->find('lint:php'));

        self::assertNotSame(0, $expected->getExitCode());
        self::assertSame($expected->getExitCode(), $tester->execute(['--package' => $this->workspace]));
        self::assertStringContainsString('broken.php', $tester->getDisplay());
        self::assertStringNotContainsString('PHP syntax checked:', $tester->getDisplay());
    }

    public function testExcludesOnlyTheRequestedRelativeDirectory(): void
    {
        (new Filesystem())->mkdir($this->workspace . '/tests/site/public');
        file_put_contents($this->workspace . '/tests/site/public/wordpress.php', '<?php syntax error');
        file_put_contents($this->workspace . '/tests/site/router.php', '<?php return true;');
        $tester = new CommandTester(ApplicationFactory::create()->find('lint:php'));

        self::assertSame(0, $tester->execute(['--package' => $this->workspace, '--exclude' => ['tests/site/public']]));
        self::assertStringContainsString('PHP syntax checked: 1 files.', $tester->getDisplay());
    }

    public function testRejectsInvalidExclusions(): void
    {
        foreach (['', '/tmp', '../src'] as $path) {
            $tester = new CommandTester(ApplicationFactory::create()->find('lint:php'));
            self::assertSame(2, $tester->execute(['--package' => $this->workspace, '--exclude' => [$path]]));
        }
    }

    public function testDoesNotFollowSymlinksOutsideThePackage(): void
    {
        $external = $this->workspace . '-external';
        (new Filesystem())->mkdir($external);
        file_put_contents($external . '/broken.php', '<?php syntax error');
        try {
            symlink($external, $this->workspace . '/linked');
            symlink($external . '/broken.php', $this->workspace . '/linked.php');
            $tester = new CommandTester(ApplicationFactory::create()->find('lint:php'));
            self::assertSame(0, $tester->execute(['--package' => $this->workspace]));
            self::assertStringContainsString('PHP syntax checked: 0 files.', $tester->getDisplay());
        } finally {
            (new Filesystem())->remove($external);
        }
    }
}
