<?php

declare(strict_types=1);

namespace SymPress\Qa\Console;

use SymPress\Qa\Command\DoctorCommand;
use SymPress\Qa\Command\QaCommand;
use SymPress\Qa\Command\ToolCommand;
use SymPress\Qa\Runner\ToolRunner;
use SymPress\Qa\Support\ConfigDiscovery;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public static function create(): Application
    {
        $contextFactory = new PackageContextFactory();
        $configDiscovery = new ConfigDiscovery();
        $toolRunner = new ToolRunner($configDiscovery);

        $application = new Application('SymPress QA', '0.1.0');
        $application->addCommand(new ToolCommand('cs', 'Run PHP_CodeSniffer.', $contextFactory, $toolRunner));
        $application->addCommand(new ToolCommand('cs:fix', 'Fix PHPCS violations with PHPCBF.', $contextFactory, $toolRunner));
        $application->addCommand(new ToolCommand('static-analysis', 'Run PHPStan.', $contextFactory, $toolRunner));
        $application->addCommand(new ToolCommand('tests', 'Run PHPUnit.', $contextFactory, $toolRunner));
        $application->addCommand(new QaCommand($contextFactory, $toolRunner));
        $application->addCommand(new DoctorCommand($contextFactory, $configDiscovery));
        $application->setDefaultCommand('qa', false);

        return $application;
    }
}
