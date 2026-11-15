<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
}

class Container implements ContainerInterface
{
    public function get(string $id): mixed
    {
        return null;
    }

    public function has(string $id): bool
    {
        return false;
    }
}
