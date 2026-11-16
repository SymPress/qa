<?php

declare(strict_types=1);

namespace SymPress\Qa\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/** @implements Rule<MethodCall> */
final class PreparedSqlRule implements Rule
{
    /** wpdb properties holding trusted table names, safe to interpolate into SQL. */
    private const array TABLE_PROPERTIES = [
        'prefix', 'base_prefix',
        'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships',
        'options', 'links', 'users', 'usermeta',
        'blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'registration_log',
    ];

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

        return $this->untypedGlobalWpdb($receiver, $scope);
    }

    /** `global $wpdb` and `$GLOBALS['wpdb']` are untyped without WordPress type extensions. */
    private function untypedGlobalWpdb(Node\Expr $receiver, Scope $scope): bool
    {
        if ($scope->getType($receiver)->getObjectClassNames() !== []) {
            return false;
        }

        return ($receiver instanceof Variable && $receiver->name === 'wpdb')
            || (
                $receiver instanceof ArrayDimFetch && $receiver->var instanceof Variable && $receiver->var->name === 'GLOBALS'
                && $receiver->dim instanceof String_ && $receiver->dim->value === 'wpdb'
            );
    }

    private function tableName(Node\Expr $expression, Scope $scope): bool
    {
        return $expression instanceof PropertyFetch && $expression->name instanceof Identifier
            && in_array($expression->name->name, self::TABLE_PROPERTIES, true)
            && $this->wpdbReceiver($expression->var, $scope);
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
        if ($query instanceof InterpolatedString) {
            return array_all($query->parts, fn (Node $part): bool => $part instanceof InterpolatedStringPart || ($part instanceof Node\Expr && $this->tableName($part, $scope)));
        }

        if ($query instanceof Concat) {
            return $this->constant($query->left, $scope) && $this->constant($query->right, $scope);
        }

        if ($this->tableName($query, $scope)) {
            return true;
        }

        $type = $scope->getType($query);

        return $type->isConstantValue()->yes() && $type->isString()->yes();
    }

    private function prepared(Node\Expr $query, Scope $scope): bool
    {
        if (
            !$query instanceof MethodCall || !$query->name instanceof Identifier
            || strtolower($query->name->name) !== 'prepare'
            || (!(new ObjectType('wpdb'))->isSuperTypeOf($scope->getType($query->var))->yes() && !$this->untypedGlobalWpdb($query->var, $scope))
        ) {
            return false;
        }
        $template = $this->query($query);

        return $template !== null && $this->constant($template, $scope);
    }
}
