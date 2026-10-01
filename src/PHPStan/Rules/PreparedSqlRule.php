<?php

declare(strict_types=1);

namespace SymPress\Qa\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

/** @implements Rule<MethodCall> */
final class PreparedSqlRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || !in_array(strtolower($node->name->name), ['query', 'get_var', 'get_col', 'get_row', 'get_results'], true)) {
            return [];
        }
        if (!$this->wpdbReceiver($node->var, $scope)) {
            return [];
        }
        $query = $this->query($node);
        if ($query === null || $this->constant($query, $scope) || $this->prepared($query, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('wpdb SQL must be constant SQL or a direct typed wpdb::prepare() call with a constant template; audit dynamic identifiers and raw SQL boundaries explicitly.')
                ->identifier('sympress.preparedSql')->build(),
        ];
    }

    private function wpdbReceiver(Node\Expr $receiver, Scope $scope): bool
    {
        foreach ($scope->getType($receiver)->getObjectClassNames() as $class) {
            if ((new ObjectType('wpdb'))->isSuperTypeOf(new ObjectType($class))->yes()) {
                return true;
            }
        }

        return false;
    }

    private function query(MethodCall $call): ?Node\Expr
    {
        foreach ($call->getArgs() as $position => $argument) {
            if ($argument->name?->name === 'query' || ($position === 0 && $argument->name === null)) {
                return $argument->value;
            }
        }

        return null;
    }

    private function constant(Node\Expr $query, Scope $scope): bool
    {
        $type = $scope->getType($query);

        return $type->isConstantValue()->yes() && $type->isString()->yes();
    }

    private function prepared(Node\Expr $query, Scope $scope): bool
    {
        if (
            !$query instanceof MethodCall || !$query->name instanceof Identifier
            || strtolower($query->name->name) !== 'prepare'
            || !(new ObjectType('wpdb'))->isSuperTypeOf($scope->getType($query->var))->yes()
        ) {
            return false;
        }
        $template = $this->query($query);

        return $template !== null && $this->constant($template, $scope);
    }
}
