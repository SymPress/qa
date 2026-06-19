<?php

declare(strict_types=1);

namespace SymPress\Qa\Runner;

use SymPress\Qa\Support\ConfigDiscovery;
use SymPress\Qa\Support\PackageContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class ToolRunner
{
    public function __construct(
        private readonly ConfigDiscovery $configDiscovery,
    ) {
    }

    public function run(string $gate, PackageContext $context, SymfonyStyle $style): int
    {
        return match ($gate) {
            'cs' => $this->runPhpcs($context, $style, false),
            'cs:fix' => $this->runPhpcs($context, $style, true),
            'static-analysis' => $this->runPhpstan($context, $style),
            'tests' => $this->runPhpunit($context, $style),
            default => Command::FAILURE,
        };
    }

    private function runPhpcs(PackageContext $context, SymfonyStyle $style, bool $fix): int
    {
        $packageDir = $context->packageDir();
        $config = $this->configDiscovery->firstExistingFile($packageDir, ['phpcs.xml', 'phpcs.xml.dist']);
        if ($config === null) {
            return $this->skip($style, 'no PHPCS configuration found');
        }

        return $this->runTool($fix ? 'phpcbf' : 'phpcs', ['--standard=' . $config], $context, $style);
    }

    private function runPhpstan(PackageContext $context, SymfonyStyle $style): int
    {
        $packageDir = $context->packageDir();
        $config = $this->configDiscovery->firstExistingFile($packageDir, ['phpstan.neon', 'phpstan.neon.dist']);
        if ($config === null) {
            return $this->skip($style, 'no PHPStan configuration found');
        }

        if (
            $this->configDiscovery->usesLocalVendorAutoload($packageDir, $config)
            && !is_readable($packageDir . '/vendor/autoload.php')
        ) {
            return $this->skip($style, 'no local package vendor/autoload.php found for PHPStan');
        }

        $missingDependencies = $this->configDiscovery->usesProjectVendorAutoload($packageDir, $config)
            ? []
            : $this->configDiscovery->missingLocalSymPressDependencies($packageDir);

        if ($missingDependencies !== []) {
            return $this->skip(
                $style,
                sprintf('missing local package dependencies for PHPStan (%s)', implode(', ', $missingDependencies)),
            );
        }

        return $this->runTool('phpstan', ['analyse', '--memory-limit=1G', '--no-progress', '-c', $config], $context, $style);
    }

    private function runPhpunit(PackageContext $context, SymfonyStyle $style): int
    {
        $packageDir = $context->packageDir();
        $config = $this->configDiscovery->firstExistingFile($packageDir, ['phpunit.xml', 'phpunit.xml.dist']);
        if ($config === null) {
            return $this->skip($style, 'no PHPUnit configuration found');
        }

        $usesLocalVendorAutoload = $this->configDiscovery->usesLocalVendorAutoload($packageDir, $config);
        $usesProjectVendorAutoload = $this->configDiscovery->usesProjectVendorAutoload($packageDir, $config);
        $allowsProjectVendorFallback = $usesProjectVendorAutoload || $this->isQaPackage($packageDir);

        if ($usesLocalVendorAutoload && !is_readable($packageDir . '/vendor/autoload.php')) {
            return $this->skip($style, 'no local package vendor/autoload.php found for PHPUnit');
        }

        if (
            !$usesLocalVendorAutoload
            && !$allowsProjectVendorFallback
            && !is_readable($packageDir . '/vendor/autoload.php')
        ) {
            return $this->skip($style, 'no Composer autoload found for PHPUnit');
        }

        $missingDependencies = $allowsProjectVendorFallback
            ? []
            : $this->configDiscovery->missingLocalSymPressDependencies($packageDir);
        if ($missingDependencies !== []) {
            return $this->skip(
                $style,
                sprintf('missing local package dependencies for PHPUnit (%s)', implode(', ', $missingDependencies)),
            );
        }

        return $this->runTool('phpunit', ['--configuration', $config, '--no-coverage'], $context, $style);
    }

    private function isQaPackage(string $packageDir): bool
    {
        return ($this->configDiscovery->readComposer($packageDir)['name'] ?? null) === 'sympress/qa';
    }

    /** @param list<string> $arguments */
    private function runTool(string $tool, array $arguments, PackageContext $context, SymfonyStyle $style): int
    {
        $bin = $this->findTool($tool, $context);
        if ($bin === null) {
            return $this->skip($style, sprintf('%s is not installed for this package', $tool));
        }

        $phpArguments = in_array($tool, ['phpcs', 'phpcbf'], true)
            ? ['-d', sprintf('error_reporting=%d', E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED)]
            : [];
        $process = new Process(array_merge([PHP_BINARY], $phpArguments, [$bin], $arguments), $context->packageDir());
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($style): void {
            $style->write($buffer);
        });

        return $process->getExitCode() ?? Command::FAILURE;
    }

    private function findTool(string $tool, PackageContext $context): ?string
    {
        $packageBin = $context->packageDir() . '/vendor/bin/' . $tool;
        $projectBin = $context->projectDir() . '/vendor/bin/' . $tool;

        $candidates = $context->packageDir() === $context->projectDir()
            ? [$packageBin]
            : [
                $projectBin,
                $context->projectDir() . '/packages/qa/vendor/bin/' . $tool,
                $packageBin,
                $context->projectDir() . '/packages/coding-standards/vendor/bin/' . $tool,
            ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function skip(SymfonyStyle $style, string $reason): int
    {
        $style->writeln(sprintf('<comment>Skipping:</comment> %s.', $reason));

        return Command::SUCCESS;
    }
}
