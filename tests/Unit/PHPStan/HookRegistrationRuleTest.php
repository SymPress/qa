<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use SymPress\Qa\PHPStan\Rules\HookRegistrationRule;

/** @extends RuleTestCase<HookRegistrationRule> */
final class HookRegistrationRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/app-config.neon'];
    }

    protected function getRule(): Rule
    {
        return new HookRegistrationRule();
    }

    public function testReportsHookRegistrationOnlyInClasses(): void
    {
        $message = 'Declare the hook with #[AsHook] or a kernel.hook service tag instead of calling %s() in a class.';

        $this->analyse([__DIR__ . '/fixtures/app.php', __DIR__ . '/fixtures/app-boot.php'], [
            [sprintf($message, 'add_action'), 15],
            [sprintf($message, 'add_filter'), 16],
        ]);
    }
}
