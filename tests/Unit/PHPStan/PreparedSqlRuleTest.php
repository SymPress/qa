<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use SymPress\Qa\PHPStan\Rules\PreparedSqlRule;
use Symfony\Component\Process\Process;

/** @extends RuleTestCase<PreparedSqlRule> */
final class PreparedSqlRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/config.neon'];
    }

    protected function getRule(): Rule
    {
        return new PreparedSqlRule();
    }

    public function testRegisteredWordPressProfileReportsExactIdentifierInConsumerCli(): void
    {
        $root = dirname(__DIR__, 3);
        $process = new Process([PHP_BINARY, $root . '/vendor/bin/phpstan', 'analyse', '-c', __DIR__ . '/fixtures/consumer.neon', '--error-format=json', '--no-progress'], $root);
        $process->run();
        self::assertSame(1, $process->getExitCode());
        /** @var array{errors: list<string>, files: array<string, array{messages: list<array{identifier: string, line: int}>}>} $result */
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $result['errors']);
        $messages = array_merge(...array_column($result['files'], 'messages'));
        self::assertSame([['sympress.preparedSql', 7]], array_map(static fn (array $message): array => [$message['identifier'], $message['line']], $messages));
    }

    public function testTypedReceivers(): void
    {
        require_once __DIR__ . '/fixtures/sql.php';
        $message = 'wpdb SQL must be constant SQL or a direct typed wpdb::prepare() call with a constant template; audit dynamic identifiers and raw SQL boundaries explicitly.';
        $this->analyse([__DIR__ . '/fixtures/sql.php'], array_map(static fn (int $line): array => [$message, $line], [19, 20, 21, 22, 23, 24, 25, 26, 35, 37, 38, 39, 40]));
    }
}
