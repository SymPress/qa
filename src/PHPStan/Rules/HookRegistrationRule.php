<?php

declare(strict_types=1);

namespace SymPress\Qa\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * SymPress applications declare hooks on container services (kernel docs: hooks, dependency injection).
 *
 * @implements Rule<FuncCall>
 */
final class HookRegistrationRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name || !$scope->isInClass()) {
            return [];
        }
        $function = strtolower($node->name->getLast());
        if (!in_array($function, ['add_action', 'add_filter'], true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Declare the hook with #[AsHook] or a kernel.hook service tag instead of calling %s() in a class.',
                $function,
            ))->identifier('sympress.hookRegistration')->build(),
        ];
    }
}
