<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use JsonException;
use SymPress\Qa\Support\ConfigDiscovery;
use SymPress\Qa\Support\PackageContextFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DoctorCommand extends AbstractPackageCommand
{
    public function __construct(
        PackageContextFactory $contextFactory,
        private readonly ConfigDiscovery $configDiscovery,
    ) {

        parent::__construct($contextFactory, 'doctor');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check package QA adoption and local configuration.')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when required adoption gates are missing.')
            ->addOption('adoption-file', null, InputOption::VALUE_REQUIRED, 'JSON adoption file.')
            ->configurePackageOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $context = $this->packageContext($input);
        $packageDir = $context->packageDir();
        $composer = $this->configDiscovery->readComposer($packageDir);

        if ($composer === null) {
            $style->error(sprintf('No valid composer.json found in %s.', $packageDir));

            return self::FAILURE;
        }

        $packageName = is_string($composer['name'] ?? null)
            ? $composer['name']
            : basename($packageDir);
        $scripts = $this->stringKeyArray($composer['scripts'] ?? null);
        $adoption = $this->loadAdoption($input, $packageDir, $context->projectDir());
        $requiredGates = $this->adoptionGates($adoption, $packageName, $context->relativePackageDir(), 'required');
        $plannedGates = $this->adoptionGates($adoption, $packageName, $context->relativePackageDir(), 'planned');
        $errors = [];

        $style->title(sprintf('QA doctor: %s', $packageName));
        $style->writeln(sprintf('Package: %s', $context->relativePackageDir()));

        if (!array_key_exists('qa', $scripts)) {
            $errors[] = 'missing composer qa script';
        }

        foreach ($requiredGates as $gate) {
            if (!$this->hasGateSupport($gate, $scripts, $packageDir)) {
                $errors[] = sprintf('required gate is not configured: %s', $gate);
                continue;
            }

            $style->writeln(sprintf('<info>OK</info> required gate configured: %s', $gate));

            if (!array_key_exists('qa', $scripts) || $this->qaRunsGate($gate, $scripts)) {
                continue;
            }

            $errors[] = sprintf('required gate is not referenced by composer qa: %s', $gate);
        }

        foreach ($plannedGates as $gate) {
            if (in_array($gate, $requiredGates, true)) {
                continue;
            }

            if ($this->hasGateSupport($gate, $scripts, $packageDir)) {
                $style->writeln(sprintf('<info>OK</info> planned gate already configured: %s', $gate));
                continue;
            }

            $style->writeln(sprintf('<comment>WARN</comment> planned gate not configured yet: %s', $gate));
        }

        if ($errors === []) {
            return self::SUCCESS;
        }

        foreach ($errors as $error) {
            $style->error($error);
        }

        return $input->getOption('strict') === true ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function loadAdoption(InputInterface $input, string $packageDir, string $projectDir): array
    {
        $configuredFile = $input->getOption('adoption-file');
        $candidates = [];

        if (is_string($configuredFile) && $configuredFile !== '') {
            $candidates[] = $this->resolvePath($configuredFile, (string) getcwd());
        }

        $candidates[] = $projectDir . '/docs/qa-adoption.json';
        $candidates[] = $projectDir . '/.github/qa-adoption.json';
        $candidates[] = $packageDir . '/qa-adoption.json';

        foreach ($candidates as $candidate) {
            if (!is_readable($candidate)) {
                continue;
            }

            try {
                $decoded = json_decode((string) file_get_contents($candidate), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->defaultAdoption();
            }

            return $this->stringKeyArray($decoded);
        }

        return $this->defaultAdoption();
    }

    /** @return array<string, mixed> */
    private function defaultAdoption(): array
    {
        return [
            'defaults' => [
                'required' => ['cs'],
                'planned'  => ['static-analysis', 'tests'],
            ],
            'packages' => [],
        ];
    }

    /**
     * @param array<string, mixed> $adoption
     * @return list<string>
     */
    private function adoptionGates(array $adoption, string $packageName, string $relativePackageDir, string $key): array
    {
        $defaults = $this->gateList($this->stringKeyArray($adoption['defaults'] ?? null)[$key] ?? null);
        $packages = $this->stringKeyArray($adoption['packages'] ?? null);
        $packageAdoption = null;

        if (is_array($packages[$packageName] ?? null)) {
            $packageAdoption = $this->stringKeyArray($packages[$packageName]);
        } elseif (is_array($packages[$relativePackageDir] ?? null)) {
            $packageAdoption = $this->stringKeyArray($packages[$relativePackageDir]);
        }

        if ($packageAdoption === null) {
            return $defaults;
        }

        $configured = $this->gateList($packageAdoption[$key] ?? null);

        return $configured === [] && $key === 'required'
            ? $defaults
            : $configured;
    }

    /** @return list<string> */
    private function gateList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $gates = [];
        foreach ($value as $gate) {
            if (!is_string($gate) || $gate === '') {
                continue;
            }

            $gates[] = $gate;
        }

        return array_values(array_unique($gates));
    }

    /** @param array<string, mixed> $scripts */
    private function hasGateSupport(string $gate, array $scripts, string $packageDir): bool
    {
        return match ($gate) {
            'cs' => array_key_exists('cs', $scripts)
                || $this->configDiscovery->firstExistingFile($packageDir, ['phpcs.xml', 'phpcs.xml.dist']) !== null,
            'static-analysis' => array_key_exists('static-analysis', $scripts)
                || $this->configDiscovery->firstExistingFile($packageDir, ['phpstan.neon', 'phpstan.neon.dist']) !== null,
            'tests' => array_key_exists('tests', $scripts)
                || array_key_exists('test', $scripts)
                || $this->configDiscovery->firstExistingFile($packageDir, ['phpunit.xml', 'phpunit.xml.dist']) !== null,
            default => array_key_exists($gate, $scripts),
        };
    }

    /** @param array<string, mixed> $scripts */
    private function qaRunsGate(string $gate, array $scripts): bool
    {
        $script = implode("\n", $this->scriptCommands($scripts, 'qa'));

        if (str_contains($script, 'qa qa')) {
            return true;
        }

        return match ($gate) {
            'cs' => str_contains($script, '@cs') || str_contains($script, 'qa cs') || str_contains($script, 'phpcs'),
            'static-analysis' => str_contains($script, '@static-analysis')
                || str_contains($script, 'qa static-analysis')
                || str_contains($script, 'phpstan'),
            'tests' => str_contains($script, '@tests')
                || str_contains($script, '@test')
                || str_contains($script, 'qa tests')
                || str_contains($script, 'phpunit'),
            default => str_contains($script, '@' . $gate) || str_contains($script, 'qa ' . $gate),
        };
    }

    /**
     * @param array<string, mixed> $scripts
     * @return list<string>
     */
    private function scriptCommands(array $scripts, string $scriptName): array
    {
        $script = $scripts[$scriptName] ?? null;

        if (is_string($script)) {
            return [$script];
        }

        if (!is_array($script)) {
            return [];
        }

        $commands = [];
        foreach ($script as $command) {
            if (!is_string($command)) {
                continue;
            }

            $commands[] = $command;
        }

        return $commands;
    }

    /** @return array<string, mixed> */
    private function stringKeyArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    private function resolvePath(string $path, string $baseDir): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($baseDir, '/') . '/' . $path;
    }
}
