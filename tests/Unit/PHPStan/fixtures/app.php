<?php

declare(strict_types=1);

namespace App;

use Psr\Container\ContainerInterface as Locator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Menu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'render']);
        \add_filter('the_title', static fn (string $title): string => $title);
        has_action('init');
    }
}

final class Registry
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
}

final class Clock
{
    private static int $calls = 0;

    public static function create(): self
    {
        return new self();
    }
}

final class Reports
{
    public function __construct(private ContainerInterface $container, private Locator $locator)
    {
    }

    public function setContainer(?Container $container): void
    {
    }
}

final class TagPass
{
    public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
    }
}
