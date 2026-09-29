# PHPUnit 13 compatibility

The shared QA package permits PHPUnit 13 alongside the existing PHPUnit 10.5 and 11.5 ranges. Consumers choose their required major version; no existing consumer is forced to upgrade.

Verified on 2026-09-29 with PHP 8.5.9 and PHPUnit 13.3.6: the package's full `composer qa` passed, including strict doctor, coding standards, PHPStan and 15 tests / 68 assertions. The representative `sympress/runtime` consumer also executes its real Composer integration fixtures using PHPUnit 13.

This companion change is required before the runtime package can resolve its QA graph against a published QA revision containing PHPUnit 13 support.
