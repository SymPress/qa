<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use SymPress\Qa\Runner\ToolRunner;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class QaCommand extends AbstractPackageCommand
{
    public function __construct(
        PackageContextFactory $contextFactory,
        private readonly ToolRunner $toolRunner,
    ) {

        parent::__construct($contextFactory, 'qa');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the package quality gate.')
            ->configurePackageOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $context = $this->packageContext($input);

        foreach (['cs', 'static-analysis', 'tests'] as $gate) {
            $exitCode = $this->toolRunner->run($gate, $context, $style);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }
}
