<?php

declare(strict_types=1);

namespace SymPress\Qa\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\Node;

/**
 * Services come from the compiled container, not from global singletons (kernel docs: dependency injection).
 *
 * @implements Rule<InClassNode>
 */
final class SingletonRule implements Rule
{
    private const PROPERTIES = ['instance', '_instance', 'singleton'];
    private const METHODS = ['getinstance', 'instance'];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getOriginalNode();
        $found = null;
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $item) {
                if (!$property->isStatic() || !in_array(strtolower($item->name->name), self::PROPERTIES, true)) {
                    continue;
                }

                $found ??= [$item->getStartLine(), 'static property $' . $item->name->name];
            }
        }
        foreach ($class->getMethods() as $method) {
            if (!$method->isStatic() || !in_array($method->name->toLowerString(), self::METHODS, true)) {
                continue;
            }

            $found ??= [$method->getStartLine(), 'static method ' . $method->name->name . '()'];
        }
        if ($found === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Register %s as a container service and inject it instead of a singleton (%s).',
                $node->getClassReflection()->getDisplayName(),
                $found[1],
            ))->identifier('sympress.singleton')->line($found[0])->build(),
        ];
    }
}
