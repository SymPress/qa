<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use SymPress\Qa\Support\PackageContext;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractPackageCommand extends Command
{
    public function __construct(
        protected readonly PackageContextFactory $contextFactory,
        ?string $name = null,
    ) {

        parent::__construct($name);
    }

    protected function configurePackageOption(): void
    {
        $this->addOption(
            'package',
            null,
            InputOption::VALUE_REQUIRED,
            'Package directory to run against.',
        );
    }

    protected function packageContext(InputInterface $input): PackageContext
    {
        $package = $input->getOption('package');

        return $this->contextFactory->fromPath(is_string($package) ? $package : null);
    }
}
