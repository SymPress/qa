<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use SymPress\Qa\PHPStan\Rules\SingletonRule;

/** @extends RuleTestCase<SingletonRule> */
final class SingletonRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/app-config.neon'];
    }

    protected function getRule(): Rule
    {
        return new SingletonRule();
    }

    public function testReportsEachSingletonClassOnce(): void
    {
        $this->analyse([__DIR__ . '/fixtures/app.php'], [
            [
                'Register App\Registry as a container service and inject it instead of a singleton '
                . '(static property $instance).',
                23,
            ],
        ]);
    }
}
