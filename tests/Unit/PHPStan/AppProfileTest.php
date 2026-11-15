<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\PHPStan;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** The app profile as a consumer includes it: the container rule needs real class reflection. */
final class AppProfileTest extends TestCase
{
    public function testAppProfileReportsAllRulesInConsumerCli(): void
    {
        $root = dirname(__DIR__, 3);
        $config = __DIR__ . '/fixtures/app-consumer.neon';
        $process = new Process([PHP_BINARY, $root . '/vendor/bin/phpstan', 'analyse', '-c', $config, '--error-format=json', '--no-progress'], $root);
        $process->run();
        /** @var array{files: array<string, array{messages: list<array{identifier?: string, line: int}>}>} $result */
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $found = [];
        foreach (array_merge(...array_column($result['files'], 'messages')) as $message) {
            if (!str_starts_with($message['identifier'] ?? '', 'sympress.')) {
                continue;
            }

            $found[] = [$message['identifier'], $message['line']];
        }

        // The PSR locator (line 43) and the plugin bootstrap file stay allowed.
        self::assertSame([
            ['sympress.hookRegistration', 15],
            ['sympress.hookRegistration', 16],
            ['sympress.singleton', 23],
            ['sympress.containerInjection', 43],
            ['sympress.containerInjection', 47],
        ], $found);
    }
}
