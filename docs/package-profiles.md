# Package Profiles

Suggested adoption profiles:

| Profile | Typical packages | Gates |
| --- | --- | --- |
| `library` | Contracts, ORM, Twig, Kernel-adjacent packages | PHPCS, PHPStan, PHPUnit when tests exist |
| `wordpress-plugin` | Consent, Mailer, Booking, Category Image, KPI Dashboard | PHPCS with WordPress profile, PHPStan with WordPress extension, PHPUnit optional |
| `wordpress-muplugin` | Event Dispatcher, Migration, Monolog, Profiler, WP-CLI Console | PHPCS with boundary rules, PHPStan where bootstraps exist, PHPUnit when tests exist |
| `wordpress-theme` | Brianvarskonst Theme | PHPCS with WordPress/template rules, PHPUnit optional |
| `tooling` | Coding Standards, Asset Compiler, Maker Bundle, QA | PHPCS, PHPStan, PHPUnit or fixture tests as needed |

Frontend gates are intentionally out of scope for this package. They should live in a dedicated frontend QA library.
