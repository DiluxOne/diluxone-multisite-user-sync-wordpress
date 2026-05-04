# Testing & quality

What every quality gate enforces, why, and how to run each one locally.

## Quality stack at a glance

| Layer | Tool | Catches | CI workflow | Make target |
| --- | --- | --- | --- | --- |
| Unit tests | PHPUnit | Logic regressions in pure-PHP units. Currently a smoke placeholder; real coverage lands with the OOP refactor. | `pr-checks.yml` | `make test` |
| Integration tests | PHPUnit on wp-env multisite | Sync flows end-to-end against a real WP runtime. Lands after the OOP refactor. | (added later) | (added later) |
| Coding style | PHP_CodeSniffer + WordPress Coding Standards | Style, naming, escaping, sanitisation, deprecated APIs, prefix discipline. | (added in cleanup PR) | `make lint` |
| Static analysis | PHPStan level 8 + szepeviktor/phpstan-wordpress | Type safety, unreachable code, undefined methods/properties, missing return types. **No baseline.** | (added in cleanup PR) | `make stan` |
| Security taint analysis | Psalm + humanmade/psalm-plugin-wordpress (taint-only mode) | XSS, SQL injection, command injection — user input flowing into dangerous sinks. | (added in cleanup PR) | `make psalm` |
| i18n | `wp i18n make-pot` | Missing translator comments on placeholders, dynamic text domains, conflicting translator hints, concat'd translatable strings. | `i18n-validate.yml` | `make i18n` |
| Plugin Check (wp.org) | wordpress/plugin-check | The same checks the wp.org plugin team runs at submission/review time. | `pr-checks.yml` | (no Make target) |
| PHP syntax matrix | `php -l` on PHP 7.4–8.3 | Hard syntax errors on every supported PHP version. | `pr-checks.yml` | (no Make target) |

> **Why some workflows say "added in cleanup PR"**: PR 1 brings in the *configuration* for PHPCS / PHPStan / Psalm so contributors can run them locally, but the existing legacy code does not yet pass them. The CI workflows that enforce those gates are added in a later PR after the OOP refactor and a dedicated linter cleanup pass. From that PR onward, all three become required status checks on `main`.

Every layer that has a CI workflow today is required to land before a PR can merge (branch protection enforces it).

## Unit tests

Located in [`tests/Unit/`](../tests/Unit/). They run in pure PHP without WordPress.

```bash
make test           # default target → unit tests only
make test-unit      # explicit
```

The current suite is a smoke placeholder (`SmokeTest.php`). Real unit tests for `Config`, `SyncEngine`, repositories etc. land alongside the OOP refactor. The integration suite (added in a later PR) will exercise the full sync flows against a real wp-env multisite.

When you add a unit test, mirror the source path: a new class `WPMUS\SyncEngine` lives in `src/SyncEngine.php` (or wherever the autoload points), and its tests go in `tests/Unit/SyncEngineTest.php`. Don't touch `$_GET`, `$_POST`, the database, the filesystem, or `define()` plugin constants in unit tests — the bootstrap intentionally keeps the WP runtime out.

## PHPCS / WordPress Coding Standards

Configuration: [`phpcs.xml.dist`](../phpcs.xml.dist).

```bash
make lint           # report violations
make lint-fix       # auto-fix what can be auto-fixed (PHPCBF)
```

The ruleset enforces the WordPress Coding Standards plus a small project-specific overlay:

- **Yoda conditions** are off (we use `$x === 5`, not `5 === $x`).
- A few cosmetic comment-formatting rules are relaxed.
- The plugin function-prefix is `wpmus` / `WPMUS` / `wpm_user_sync` (enforced by `WordPress.NamingConventions.PrefixAllGlobals`).
- The text domain is `wpm-user-sync`.
- PHP compatibility is checked against PHP 7.4+ via `PHPCompatibilityWP`.

When PHPCS reports a violation, the rule code is in the right column. Search for it in the config or in [WPCS docs](https://github.com/WordPress/WordPress-Coding-Standards/wiki) before suppressing — most warnings are real bugs.

## PHPStan

Configuration: [`phpstan.neon`](../phpstan.neon).

```bash
make stan
```

We run **level 8 (max strictness) with no baseline** as the target end state. The `szepeviktor/phpstan-wordpress` extension teaches PHPStan about the WordPress API surface so e.g. `get_user_locale()` returns `string`, `get_sites()` returns `WP_Site[]`, and `wp_remote_get()` returns `array|WP_Error`.

`WP_DEBUG` is declared `dynamicConstantNames` so PHPStan doesn't collapse `if ( WP_DEBUG )` into a constant — its runtime value is set per-deployment from `wp-config.php`.

Until the cleanup PR brings violations to 0, `make stan` will print findings on the legacy procedural code. Don't add to a baseline — the project deliberately doesn't have one.

## Psalm taint analysis

Configuration: [`psalm.xml`](../psalm.xml).

```bash
make psalm
```

Psalm here runs in **taint-analysis mode only**. The `humanmade/psalm-plugin-wordpress` plugin teaches it that `esc_html()`, `esc_attr()`, `esc_url()`, `wpdb->prepare()`, `sanitize_*()` are sanitisation barriers, so user-controlled values from `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE` / `$_FILES` / `$_SERVER` only become findings if they reach a dangerous sink (`echo`, `eval`, `exec`, `$wpdb->query()`, `file_put_contents`, `header`, …) without passing through one.

General static type-checking is suppressed in `psalm.xml` — that's PHPStan's job.

## i18n validation

Configuration: [`.github/workflows/i18n-validate.yml`](../.github/workflows/i18n-validate.yml).

```bash
make i18n
```

The Makefile target runs `wp i18n make-pot` and writes the result to `build/wpm-user-sync.pot`. The CI workflow does the same and additionally fails the build if any `Warning:` / `Error:` line appears.

## Plugin Check

CI step in [`pr-checks.yml`](../.github/workflows/pr-checks.yml). Runs the [official WordPress Plugin Check](https://github.com/WordPress/plugin-check-action) action with all categories enabled (`plugin_repo`, `security`, `performance`, `accessibility`, `general`) plus experimental checks. Some codes are explicitly ignored (`hidden_files`, `github_directory`, `unexpected_markdown_file`, `stable_tag_mismatch`) because they false-positive on the GitHub-flat repo layout or on the `-dev` suffix workflow.

If you submit a new version to wp.org, the same checks run there. CI catches them earlier so a wp.org reviewer never has to.

## Running everything at once

```bash
make check     # lint + stan + psalm + tests
make release   # make check + version-alignment dry-run
```

`make release` is what you should run before pushing a release tag.
