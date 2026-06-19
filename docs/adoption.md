# QA Adoption

`sympress-qa doctor --strict` checks required gates from an adoption file instead of assuming every package must immediately have PHPStan and PHPUnit.

The default project-level file is:

```text
docs/qa-adoption.json
```

Schema:

```json
{
  "version": 1,
  "defaults": {
    "required": ["cs"],
    "planned": ["static-analysis", "tests"]
  },
  "packages": {
    "sympress/example": {
      "required": ["cs", "static-analysis"],
      "planned": ["tests"]
    }
  }
}
```

Rules:

- `required` gates fail `doctor --strict` when they are not configured.
- `required` gates must also be referenced by the package's `composer qa` script.
- `planned` gates are reported but do not fail the job.
- Package names from `composer.json` are preferred.
- Relative package directories such as `packages/example` can be used as fallback keys.

This lets the repository enforce `composer qa` everywhere while migrating static analysis and tests package by package.
