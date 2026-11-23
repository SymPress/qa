# Changelog

Release notes per version live in [`docs/releases/`](docs/releases/).

## 0.1.7

- `sympress.preparedSql` checks untyped `$this->wpdb` and untyped receivers of
  `get_var()`, `get_col()`, `get_row()` and `get_results()`. **Consumers may see
  new findings** for dynamic SQL these receivers used to hide; fix them with
  `wpdb::prepare()` or a reviewed local ignore.
- `sympress.containerInjection` allows `ContainerBuilder` in compiler passes,
  extensions and bundles.
- Monorepo discovery requires the package under `<root>/packages/` and a root
  owned by the same user (or root).
- `doctor` no longer counts `@cs:fix` as the `cs` gate.
- The adoption schema ships in installed packages.
- CI: Dependabot updates GitHub Actions pins.

See [docs/releases/0.1.7.md](docs/releases/0.1.7.md).

## Earlier releases

[0.1.6](docs/releases/0.1.6.md), [0.1.5](docs/releases/0.1.5.md),
[0.1.3](docs/releases/0.1.3.md), [0.1.2](docs/releases/0.1.2.md),
[0.1.0](docs/releases/0.1.0.md)
