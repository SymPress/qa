<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Console\ApplicationFactory;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/sympress-qa-doctor-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace));
        file_put_contents($this->workspace . '/composer.json', '{"name":"sympress/fixture","scripts":{}}');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workspace . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->workspace);
    }

    public function testStrictModeTurnsMissingRequiredGateIntoFailure(): void
    {
        $ciEnvironment = getenv('CI');
        $githubEnvironment = getenv('GITHUB_ACTIONS');
        try {
            // This case explicitly compares local advisory mode with --strict.
            // CI strictness is exercised separately, so preserve hosted settings.
            putenv('CI=false');
            putenv('GITHUB_ACTIONS=false');
            $command = ApplicationFactory::create()->find('doctor');

            $advisory = new CommandTester($command);
            self::assertSame(0, $advisory->execute(['--package' => $this->workspace]));
            self::assertStringContainsString('missing composer qa script', $advisory->getDisplay());

            $strict = new CommandTester($command);
            self::assertSame(1, $strict->execute(['--package' => $this->workspace, '--strict' => true]));
        } finally {
            putenv($ciEnvironment === false ? 'CI' : 'CI=' . $ciEnvironment);
            putenv($githubEnvironment === false ? 'GITHUB_ACTIONS' : 'GITHUB_ACTIONS=' . $githubEnvironment);
        }
    }

    public function testInvalidAdoptionFileAlwaysFails(): void
    {
        $adoption = $this->workspace . '/adoption.json';
        file_put_contents($adoption, '{');

        $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

        self::assertSame(1, $tester->execute([
            '--package'       => $this->workspace,
            '--adoption-file' => $adoption,
        ]));
        self::assertStringContainsString('Invalid QA adoption file', $tester->getDisplay());
    }

    public function testUnsupportedAdoptionVersionAlwaysFails(): void
    {
        $adoption = $this->workspace . '/adoption.json';
        file_put_contents($adoption, '{"version":2,"defaults":{},"packages":{}}');

        $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

        self::assertSame(1, $tester->execute([
            '--package'       => $this->workspace,
            '--adoption-file' => $adoption,
        ]));
        self::assertStringContainsString('Unsupported QA adoption version', $tester->getDisplay());
        self::assertStringContainsString('version 1', $tester->getDisplay());
    }

    public function testInvalidAdoptionShapesAlwaysFail(): void
    {
        $invalid = [
            'unknown property'  => '{"version":1,"defaults":{"required":[],"planned":[]},"packages":{},"extra":true}',
            'invalid defaults'  => '{"version":1,"defaults":"disabled","packages":{}}',
            'invalid packages'  => '{"version":1,"defaults":{"required":[],"planned":[]},"packages":[]}',
            'invalid gate list' => '{"version":1,"defaults":{"required":[1],"planned":[]},"packages":{}}',
        ];

        foreach ($invalid as $case => $json) {
            $adoption = $this->workspace . '/adoption.json';
            file_put_contents($adoption, $json);
            $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

            self::assertSame(1, $tester->execute([
                '--package'       => $this->workspace,
                '--adoption-file' => $adoption,
            ]), $case);
            self::assertStringContainsString('Invalid QA adoption file', $tester->getDisplay(), $case);
        }
    }

    public function testMissingExplicitAdoptionFileAlwaysFails(): void
    {
        $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

        self::assertSame(1, $tester->execute([
            '--package'       => $this->workspace,
            '--adoption-file' => $this->workspace . '/missing.json',
        ]));
        self::assertStringContainsString('is not readable', $tester->getDisplay());
    }

    public function testAggregateGateDoesNotImplicitlyRunTheOptInPhpLinter(): void
    {
        file_put_contents(
            $this->workspace . '/composer.json',
            '{"name":"sympress/fixture","scripts":{"qa":"@php vendor/bin/qa qa --strict","lint:php":"@php vendor/bin/qa lint:php"}}',
        );
        file_put_contents(
            $this->workspace . '/qa-adoption.json',
            '{"version":1,"defaults":{"required":["lint:php"],"planned":[]},"packages":{}}',
        );
        $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

        self::assertSame(1, $tester->execute(['--package' => $this->workspace, '--strict' => true]));
        self::assertStringContainsString('required gate is not referenced by composer qa: lint:php', $tester->getDisplay());
    }

    public function testEmptyPackageRequiredListOverridesDefaults(): void
    {
        file_put_contents(
            $this->workspace . '/composer.json',
            '{"name":"sympress/fixture","scripts":{"qa":"@php -v"}}',
        );
        $adoption = $this->workspace . '/adoption.json';
        file_put_contents(
            $adoption,
            '{"version":1,"defaults":{"required":["cs"],"planned":[]},'
                . '"packages":{"sympress/fixture":{"required":[],"planned":[]}}}',
        );
        $tester = new CommandTester(ApplicationFactory::create()->find('doctor'));

        self::assertSame(0, $tester->execute([
            '--package'       => $this->workspace,
            '--adoption-file' => $adoption,
            '--strict'        => true,
        ]));
    }
}
