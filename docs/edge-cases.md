# Edge Cases

Keep edge cases local and visible.

Recommended order:

1. Improve the shared config when the case is valid for many packages.
2. Configure a PHPCS sniff or PHPStan parameter in the package when the package has a repeatable convention.
3. Exclude a narrow path for generated code, WordPress entrypoints, templates, or temporary parser limitations.
4. Use PHPStan baselines for legacy adoption.
5. Use inline ignores only for small local exceptions, with the full rule code and a short reason.

Examples:

```xml
<rule ref="SymPress-WordPress">
    <exclude name="SymPress.Files.LineLength.TooLong" />
</rule>
```

```neon
includes:
    - vendor/sympress/qa/config/phpstan/wordpress.neon
    - phpstan-baseline.neon

parameters:
    level: 7
```
