# SymPress QA

Shared QA tooling for SymPress packages.

This package does not replace `sympress/coding-standards`. The coding standards package remains responsible for PHPCS rulesets and custom sniffs. `sympress/qa` provides the shared toolchain, PHPStan defaults, PHPUnit templates, and the `qa` runner used by package scripts and CI.

The runner is a Symfony Console application and uses Symfony Process for tool execution. It is designed to behave the same in a standalone split package, in the SymPress monorepo, and inside CI containers.

When a monorepo root targets a package via `--package`, the runner prefers the root or QA toolchain before a package-local `vendor/bin`. That keeps direct monorepo checks deterministic even when an individual package still has an older local `vendor` directory. PHPCS and PHPCBF are executed with PHP deprecation notices suppressed, so dependency deprecations in coding-standard tooling do not abort scans on newer PHP runtimes.

## Installation

```bash
composer require --dev sympress/qa
```

The package intentionally keeps PHPCS, PHPStan, PHPUnit and their extensions in `require`. Downstream packages install `sympress/qa` as a dev dependency and receive the complete QA toolchain transitively.

## Composer Scripts

Recommended package scripts:

```json
{
  "scripts": {
    "cs": "qa cs",
    "cs:fix": "qa cs:fix",
    "static-analysis": "qa static-analysis",
    "tests": "qa tests",
    "test": "@tests",
    "qa": [
      "@cs",
      "@static-analysis",
      "@tests"
    ]
  }
}
```

Packages can still keep custom implementations behind the same script names. CI should call `composer qa` and let each package decide whether it uses `sympress/qa` or custom commands.

## Commands

```bash
vendor/bin/qa cs
vendor/bin/qa cs:fix
vendor/bin/qa static-analysis
vendor/bin/qa tests
vendor/bin/qa qa
vendor/bin/qa doctor --strict
```

Missing PHPStan or PHPUnit configuration is skipped by the runner. Use `doctor --strict` with an adoption file when a package is ready to make a gate mandatory.

## DevOps Usage

CI jobs can keep their package-specific behavior simple:

```bash
composer install --prefer-dist --no-progress --no-interaction
composer qa
```

Packages that need custom behavior can keep the same Composer script names and wrap custom commands behind them. The runner also supports direct package targeting from a monorepo root:

```bash
php packages/qa/bin/qa qa --package=packages/kernel
php packages/qa/bin/qa doctor --strict --package=packages/kernel
```

`doctor --strict` checks only configured required gates. Missing optional gates are reported as planned work when an adoption file is present; without one, `cs` is the only required default.

## Config Includes

PHPStan configs can include shared defaults:

```neon
includes:
    - vendor/sympress/qa/config/phpstan/library.neon

parameters:
    paths:
        - src
        - tests
```

WordPress packages can use:

```neon
includes:
    - vendor/sympress/qa/config/phpstan/wordpress.neon

parameters:
    paths:
        - src
    bootstrapFiles:
        - tests/Support/TestEnvironment.php
```

`szepeviktor/phpstan-wordpress` is installed by this package and should be loaded through `phpstan/extension-installer`. Consuming packages need to allow the Composer plugin:

```json
{
  "config": {
    "allow-plugins": {
      "phpstan/extension-installer": true
    }
  }
}
```

PHPCS remains package-local through `phpcs.xml.dist`, usually referencing `SymPress-Pure`, `SymPress-WordPress`, `SymPress-Boundary`, or `SymPress-Templates`.
