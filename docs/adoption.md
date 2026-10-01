# QA Adoption

`qa doctor --strict` checks required gates from an adoption file instead of assuming every package must immediately have PHPStan and PHPUnit.

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

The machine-readable schema is
[`qa-adoption.schema.json`](qa-adoption.schema.json). Version `1` is required;
invalid JSON, schema-incompatible data and unsupported versions fail even
without `--strict`.

Rules:

- `required` gates fail `doctor --strict` when they are not configured.
- `required` gates must also be referenced by the package's `composer qa` script.
- `planned` gates are reported but do not fail the job.
- Package names from `composer.json` are preferred.
- Relative package directories such as `packages/example` can be used as fallback keys.

This lets the repository enforce `composer qa` everywhere while migrating static analysis and tests package by package.

## CI enforcement

`qa`, each tool command and `doctor` enable strict behavior when `CI` or
`GITHUB_ACTIONS` is truthy (`1`, `true`, `yes`, `on`, case insensitive).
Explicit `--strict` always enables it. Local non-strict adoption skips remain
visible. CI does not turn planned doctor gates into required gates.

## Typed WordPress SQL checking

Include `vendor/sympress/qa/config/phpstan/wordpress.neon` in the consumer's
PHPStan configuration. It registers `sympress.preparedSql` for query-bearing
methods on typed `wpdb` receivers, including properties, aliases and factories.
Constant SQL and direct typed `wpdb::prepare()` with a constant template pass.
Unknown SQL variables, interpolated templates, value concatenation and another
object's `prepare()` fail. Assigned prepared values are conservatively rejected;
this release does not claim SQL dataflow tracking. Structured insert/update/delete
APIs are unaffected. Vetted dynamic identifiers and explicit raw SQL boundaries
need a local `@phpstan-ignore sympress.preparedSql` with a review reason and
parameterization tests. Never ignore this identifier for a whole repository.

WPCS remains complementary token-based checking; see coding-standards docs for
its receiver-name limitation. The pure library PHPStan profile remains unchanged.
