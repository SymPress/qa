# SymPress QA agent contract

## Purpose and boundaries

This package owns the shared `qa` executable and the meaning of the organization-wide Composer QA commands. Keep gradual adoption available by default, but never make strict verification report success when a required tool or configuration is unavailable.

## Read first

- `src/Runner/ToolRunner.php`: tool discovery, skip policy, subprocess execution and exit codes.
- `src/Command/QaCommand.php`: ordered, fail-fast aggregate gate.
- `src/Command/DoctorCommand.php`: adoption policy and strict diagnostics.
- `docs/adoption.md` and `qa-adoption.json`: required-versus-planned gate contract.

## Verification

- Fast: `composer tests` for behavior-only changes.
- Full: `composer qa`; this repository deliberately runs every gate in strict mode.
- A changed command, adoption format, skip rule or process path requires a focused regression test.

## Invariants

- Invoke tools through argv arrays, never composed shell strings.
- Propagate the real subprocess exit code and stop the aggregate gate on first failure.
- Non-strict skips must be visibly labelled `SKIP`; strict skips must fail.
- Invalid explicit adoption data is an error, not a fallback to defaults.
- Keep standalone and monorepo tool lookup behavior compatible.

## Cross-repository impact

Most SymPress Composer packages consume this package through `composer qa`, and `sympress/workflows` assumes that command contract. Treat command names and default behavior as public API. Validate a representative consumer before a breaking release.

## Definition of done

Tests cover the changed success, skip and failure paths; `composer qa` passes without skipped gates; documentation and the adoption schema match the accepted input.
