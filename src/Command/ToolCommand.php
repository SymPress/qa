<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use SymPress\Qa\Runner\ToolRunner;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ToolCommand extends AbstractPackageCommand
{
    public function __construct(
        private readonly string $gate,
        private readonly string $description,
        PackageContextFactory $contextFactory,
        private readonly ToolRunner $toolRunner,
    ) {

        parent::__construct($contextFactory, $gate);
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->description)
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail instead of skipping an unavailable gate.')
            ->configurePackageOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->toolRunner->run(
            $this->gate,
            $this->packageContext($input),
            new SymfonyStyle($input, $output),
            $input->getOption('strict') === true,
        );
    }
}
