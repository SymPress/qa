<?php

declare(strict_types=1);

namespace SymPress\Qa\Command;

use JsonException;
use RuntimeException;
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
        try {
            $adoption = $this->loadAdoption($input, $packageDir, $context->projectDir());
        } catch (RuntimeException $exception) {
            $style->error($exception->getMessage());

            return self::FAILURE;
        }
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

        return $this->strict($input) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function loadAdoption(InputInterface $input, string $packageDir, string $projectDir): array
    {
        $configuredFile = $input->getOption('adoption-file');
        $candidates = [];

        if (is_string($configuredFile) && $configuredFile !== '') {
            $configuredFile = $this->resolvePath($configuredFile, (string) getcwd());
            if (!is_readable($configuredFile)) {
                throw new RuntimeException(sprintf('QA adoption file %s is not readable.', $configuredFile));
            }

            $candidates[] = $configuredFile;
        }

        $candidates[] = $projectDir . '/docs/qa-adoption.json';
        $candidates[] = $projectDir . '/.github/qa-adoption.json';
        $candidates[] = $packageDir . '/qa-adoption.json';

        foreach ($candidates as $candidate) {
            if (!is_readable($candidate)) {
                continue;
            }

            try {
                $decoded = json_decode((string) file_get_contents($candidate), false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(
                    sprintf('Invalid QA adoption file %s: %s', $candidate, $exception->getMessage()),
                    0,
                    $exception,
                );
            }

            return $this->validateAdoption($decoded, $candidate);
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

        return $this->gateList($packageAdoption[$key] ?? null);
    }

    /** @return array<string, mixed> */
    private function validateAdoption(mixed $decoded, string $candidate): array
    {
        if (!$decoded instanceof \stdClass) {
            throw $this->invalidAdoption($candidate, 'the root value must be an object');
        }

        /** @var array<string, mixed> $root */
        $root = get_object_vars($decoded);
        $this->assertKeys($root, ['$schema', 'version', 'defaults', 'packages'], $candidate, 'root');

        foreach (['version', 'defaults', 'packages'] as $required) {
            if (!array_key_exists($required, $root)) {
                throw $this->invalidAdoption($candidate, "missing required root property {$required}");
            }
        }

        if (array_key_exists('$schema', $root) && !is_string($root['$schema'])) {
            throw $this->invalidAdoption($candidate, '$schema must be a string');
        }

        if ($root['version'] !== 1) {
            throw new RuntimeException(sprintf('Unsupported QA adoption version in %s; expected version 1.', $candidate));
        }

        if (!$root['packages'] instanceof \stdClass) {
            throw $this->invalidAdoption($candidate, 'packages must be an object');
        }

        $packages = [];
        foreach (get_object_vars($root['packages']) as $package => $gates) {
            $packages[$package] = $this->validateGateConfiguration($gates, $candidate, "packages.{$package}");
        }

        return [
            'version'  => 1,
            'defaults' => $this->validateGateConfiguration($root['defaults'], $candidate, 'defaults'),
            'packages' => $packages,
        ];
    }

    /** @return array{required: list<string>, planned: list<string>} */
    private function validateGateConfiguration(mixed $decoded, string $candidate, string $path): array
    {
        if (!$decoded instanceof \stdClass) {
            throw $this->invalidAdoption($candidate, "{$path} must be an object");
        }

        /** @var array<string, mixed> $gates */
        $gates = get_object_vars($decoded);
        $this->assertKeys($gates, ['required', 'planned'], $candidate, $path);

        foreach (['required', 'planned'] as $required) {
            if (!array_key_exists($required, $gates)) {
                throw $this->invalidAdoption($candidate, "{$path} is missing required property {$required}");
            }
        }

        return [
            'required' => $this->validateGateList($gates['required'], $candidate, "{$path}.required"),
            'planned'  => $this->validateGateList($gates['planned'], $candidate, "{$path}.planned"),
        ];
    }

    /** @return list<string> */
    private function validateGateList(mixed $decoded, string $candidate, string $path): array
    {
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw $this->invalidAdoption($candidate, "{$path} must be an array");
        }

        foreach ($decoded as $gate) {
            if (!is_string($gate) || $gate === '') {
                throw $this->invalidAdoption($candidate, "{$path} must contain only non-empty strings");
            }
        }

        if (count(array_unique($decoded)) !== count($decoded)) {
            throw $this->invalidAdoption($candidate, "{$path} must not contain duplicates");
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $allowed
     */
    private function assertKeys(array $values, array $allowed, string $candidate, string $path): void
    {
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown !== []) {
            throw $this->invalidAdoption(
                $candidate,
                sprintf('%s contains unknown property %s', $path, (string) reset($unknown)),
            );
        }
    }

    private function invalidAdoption(string $candidate, string $reason): RuntimeException
    {
        return new RuntimeException(sprintf('Invalid QA adoption file %s: %s.', $candidate, $reason));
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

        if (str_contains($script, 'qa qa') && in_array($gate, ['cs', 'static-analysis', 'tests'], true)) {
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
