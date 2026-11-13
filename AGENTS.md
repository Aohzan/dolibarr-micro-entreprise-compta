# AGENTS.md

Dolibarr module for French micro-entreprises: turnover to declare to the URSSAF,
estimated contributions, ceilings and statistics, computed from the customer
payments recorded in Dolibarr. Fork of the unmaintained AutoMicroEntreprise
module.

## Layout

| Path | Content |
| --- | --- |
| `core/modules/modAutoMicroEnt.class.php` | Module descriptor: menus, rights, widget, scheduled job, version (set by `scripts/release.sh`) |
| `class/*calculator*.class.php`, `declarationperiods`, `taxcatalog`, `thresholdcheck` | Pure PHP business rules, unit tested in `tests/Unit` |
| `class/raterepository.class.php`, `paymentrepository.class.php` | All SQL |
| `class/deadlinereminder.class.php` | Scheduled job creating agenda reminders |
| `lib/automicroent.lib.php` | Helpers shared by the pages (settings, labels, formatting) |
| `rates.php`, `declaration.php`, `stats.php`, `admin/setup.php` | Pages |
| `core/boxes/box_automicroent.php` | Home widget |
| `sql/` | Table and migrations, replayed each time the module is enabled |
| `langs/{fr_FR,en_US}/automicroent.lang` | Translations, same keys in both |
| `dev/` | Docker images, first-boot seed, PHPStan bootstrap |
| `tests/e2e` | Playwright tests and README screenshots |

## Conventions

- **Never rename the module identifiers**: directory `automicroent`, rights
  class `automicroent`, table `llx_ame_rates`, lang file
  `automicroent@automicroent`. Installations upgrade in place on them.
- Module number 451050 and rights 451051-451052 come from the block of the
  original editor (451000-451999, depordi.fr, wiki page *List of modules id*).
  Changing them makes every user grant the rights again: only do it in a major
  version, with a block reserved for this fork (needed to publish on
  Dolistore, see `dolistore/README.md`).
- Code, comments and commit messages in English. Every user-facing string goes
  through `$langs->trans()` with a key in both lang files (a unit test checks it).
- Module code must run on PHP 7.2 to 8.5, the range Dolibarr 23 and 24 support:
  no arrow functions, `match`, typed properties or PHP 8 functions outside
  `tests/`. PHPStan checks it.
- Always give `GETPOST()` an explicit check type (`aZ09`, `alphanohtml`, `int`,
  `array`): since Dolibarr 24 an empty one returns an empty string.
- Every action changing data needs a CSRF token (`newToken()` in forms and links)
  and a name starting with `add`, `save`, `update`, `confirm_`... so that Dolibarr
  checks it even in GET.
- Rates: `level` is the category (1-5, 10 for ceilings in euros), `type` the base
  (0 sales, 1 services, 2 all, 3 BIC services, 4 BNC services), `rate` the value
  (NULL: not applicable that year). Keep `first` and `second` at 0 in new records:
  `sql/data.sql` converts legacy records where they are not.
- Schema changes go in `sql/llx_ame_rates.sql` as idempotent `ALTER TABLE`
  statements (Dolibarr ignores "already exists" errors) and data migrations in
  `sql/data.sql`; users disable then enable the module to apply them.
- Catalog rates and ceilings (`class/taxcatalog.class.php`) come with their
  source in a comment.

## Commands

Tools come from `mise.toml` (no version younger than 7 days).

- `mise run lint`: prek hooks (php -l, PHPCS, PHPStan level 5, PHPUnit,
  codespell, yamllint, markdownlint, zizmor). `mise run test`: PHPUnit.
- `mise run dev` then `mise run e2e`: seeded Dolibarr in Docker, Playwright tests.
  `DOLIBARR=previous DOLIBARR_PORT=8081` runs the previous Dolibarr major beside it.
  The seed is deterministic; tests that change data restore it.
- `mise run screenshots`: README images in `docs/screenshots`.

## Releases

Conventional Commits are required (checked by `committed`). Merging to `master`
runs semantic-release: it updates `ChangeLog.md` and the descriptor version, tags
`vX.Y.Z` and publishes `module_automicroent-X.Y.Z.zip`, built by
`scripts/release.sh` from `git archive` (development files are `export-ignore`d
in `.gitattributes`). Use `feat!:` or a `BREAKING CHANGE:` footer when users must
act, for example re-enable the module.
