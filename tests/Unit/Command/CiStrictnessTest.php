<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use SymPress\Qa\Console\ApplicationFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class CiStrictnessTest extends TestCase
{
    public function testCiRequiresGatesWithoutStrictOptionAndLocalSkipsRemainVisible(): void
    {
        $workspace = sys_get_temp_dir() . '/qa-ci-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($workspace);
        file_put_contents($workspace . '/composer.json', '{"name":"sympress/fixture"}');
        $ciEnvironment = getenv('CI');
        $github = getenv('GITHUB_ACTIONS');
        try {
            foreach (['true', '1', 'yes', 'on', 'false', '0', 'no', 'off', ''] as $value) {
                putenv('CI=' . $value);
                putenv('GITHUB_ACTIONS=false');
                foreach (['qa', 'cs', 'static-analysis', 'tests', 'doctor'] as $command) {
                    $tester = new CommandTester(ApplicationFactory::create()->find($command));
                    self::assertSame(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $tester->execute(['--package' => $workspace]), $command . ': ' . $value);
                }
            }
            putenv('CI=false');
            putenv('GITHUB_ACTIONS=true');
            $tester = new CommandTester(ApplicationFactory::create()->find('cs'));
            self::assertSame(1, $tester->execute(['--package' => $workspace]));
            self::assertStringContainsString('FAILED', $tester->getDisplay());
        } finally {
            putenv($ciEnvironment === false ? 'CI' : 'CI=' . $ciEnvironment);
            putenv($github === false ? 'GITHUB_ACTIONS' : 'GITHUB_ACTIONS=' . $github);
            (new Filesystem())->remove($workspace);
        }
    }
}
