<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class LintPhpCommand extends AbstractPackageCommand
{
    public function __construct(PackageContextFactory $contextFactory)
    {
        parent::__construct($contextFactory, 'lint:php');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check package PHP syntax with the current PHP interpreter.')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude a directory relative to the package root.')
            ->configurePackageOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->packageContext($input)->packageDir();
        $excluded = $input->getOption('exclude');
        if (!is_array($excluded)) {
            return self::INVALID;
        }
        foreach ($excluded as $path) {
            if (!is_string($path) || $path === '' || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
                $output->writeln('<error>Exclusions must be non-empty package-relative directory paths without .. segments.</error>');

                return self::INVALID;
            }
        }

        $excluded = array_map(static fn (string $path): string => trim($path, '/'), $excluded);
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($root, $excluded): bool {
                if ($file->isLink()) {
                    return false;
                }
                if (!$file->isDir()) {
                    return true;
                }

                return !in_array($file->getFilename(), ['vendor', 'node_modules', '.git', 'var', 'build', 'phpstan-cache', 'test-results', 'playwright-report'], true)
                    && !in_array(substr($file->getPathname(), strlen($root) + 1), $excluded, true);
            },
        ));
        $count = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $process = new Process([PHP_BINARY, '-l', $file->getPathname()], $root);
            $process->run();
            if (!$process->isSuccessful()) {
                $output->write($process->getOutput() . $process->getErrorOutput());

                return $process->getExitCode() ?? self::FAILURE;
            }
            ++$count;
        }
        $output->writeln(sprintf('PHP syntax checked: %d files.', $count));

        return self::SUCCESS;
    }
}
