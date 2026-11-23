<?php

declare(strict_types=1);

namespace SymPress\Qa\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PhpParser\Node;

/**
 * Inject the services a class uses, or a small PSR service locator, not the whole container
 * (kernel docs: services and autowiring).
 *
 * @implements Rule<InClassMethodNode>
 */
final class ContainerInjectionRule implements Rule
{
    private const CONTAINER = 'Symfony\Component\DependencyInjection\ContainerInterface';

    /** Compiler passes, DI extensions and bundles receive the builder by framework contract. */
    private const BUILDER = 'Symfony\Component\DependencyInjection\ContainerBuilder';

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $params = $node->getOriginalNode()->getParams();
        $parameters = $node->getMethodReflection()->getVariants()[0]->getParameters();
        foreach ($parameters as $position => $parameter) {
            $param = $params[$position] ?? null;
            if ($param === null) {
                continue;
            }
            // A nullable parameter is a union with null, which has no object class names of its own.
            foreach (TypeCombinator::removeNull($parameter->getType())->getObjectClassNames() as $class) {
                if (!$this->isContainer($class)) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Inject the services %s needs, or a Psr\Container\ContainerInterface service locator, '
                    . 'instead of the whole Symfony container (%s).',
                    $node->getClassReflection()->getDisplayName(),
                    $class,
                ))->identifier('sympress.containerInjection')->line($param->getStartLine())->build();
            }
        }

        return $errors;
    }

    private function isContainer(string $class): bool
    {
        $type = new ObjectType($class);

        return (new ObjectType(self::CONTAINER))->isSuperTypeOf($type)->yes()
            && !(new ObjectType(self::BUILDER))->isSuperTypeOf($type)->yes();
    }
}
