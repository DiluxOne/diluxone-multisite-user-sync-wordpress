# Development

How to run the plugin from source, what tools you need, and the day-to-day commands you'll use.

For contribution rules see [`CONTRIBUTING.md`](../CONTRIBUTING.md). For multisite-specific notes see [`multisite-development.md`](multisite-development.md). For the test-and-quality stack see [`testing-and-quality.md`](testing-and-quality.md). For releases see [`release.md`](release.md).

## What you need

| Tool | Why |
| --- | --- |
| **Docker** | Runs `wp-env` and the PHP toolchain (PHPCS, PHPStan, Psalm, PHPUnit, WP-CLI) without forcing you to install matching PHP extensions on the host. |
| **Node.js 18+** and **npm** | Boots `wp-env` (`make env`). |
| **`make`** | Optional but recommended — wraps every common task behind a short target. See `make help`. |
| **`gh`** (GitHub CLI) | Optional — convenient for working with issues, PRs, and CI logs. |

You do **not** need PHP installed on the host. Every PHP-based command runs inside an official Docker image, mounted as your host UID so `vendor/` doesn't end up root-owned.

## First run

```bash
git clone https://github.com/soydiloreto/wpm-user-sync.git
cd wpm-user-sync
make install     # composer install — pulls dev tooling into vendor/
make env         # boots wp-env at http://localhost:8888 (multisite!)
```

When `make env` finishes, open <http://localhost:8888/wp-admin/network/>. Log in with `admin` / `password`. The plugin is already mounted at `wp-content/plugins/wpm-user-sync/`. Network-activate it from the **Plugins** screen, then visit **WPM User Sync** in the network admin sidebar.

> **Important**: this plugin is multisite-only. The `.wp-env.json` boots a multisite stack by default. If you try to use a single-site WP install for development, the plugin will self-deactivate via `wpmus_check_requirements()`.

For multisite-specific dev workflow (creating sites, simulating sync events, clearing options between runs), see [`multisite-development.md`](multisite-development.md).

## Day-to-day commands

| Command | What it does |
| --- | --- |
| `make help` | List every available target with a one-line description. |
| `make install` | `composer install` — populate `vendor/`. |
| `make env` / `make env-up` | Start `wp-env` (multisite). |
| `make env-down` | Stop `wp-env` (preserves the database). |
| `make env-clean` | Destroy `wp-env` and its volumes. |
| `make lint` | PHPCS with WordPress Coding Standards. |
| `make lint-fix` | PHPCBF — auto-fix the violations PHPCS can repair. |
| `make stan` | PHPStan level 8 (no baseline). |
| `make psalm` | Psalm taint analysis (XSS / SQLi / RCE). |
| `make i18n` | `wp i18n make-pot` — extract translatable strings into `build/wpm-user-sync.pot`. |
| `make test` | Run the unit-test suite. |
| `make check` | Run every quality gate CI runs: lint + stan + psalm + tests. |
| `make release` | `make check` plus a version-alignment dry-run. |
| `make clean` | Wipe caches and build artefacts. |

## Configuration

The default `wp-env` setup is in [`.wp-env.json`](../.wp-env.json):

- **WordPress core**: latest stable.
- **PHP version**: 8.2.
- **Multisite**: yes (`"multisite": true`).
- **Plugin**: this repository, auto-mounted.
- **Debug mode**: `WP_DEBUG`, `WP_DEBUG_LOG`, and `SCRIPT_DEBUG` enabled. `WP_DEBUG_DISPLAY` is off — errors go to `wp-content/debug.log`.

To override any of these on your local machine without committing the changes, create a `.wp-env.override.json` file (already in `.gitignore`).

## Manual install (alternative)

If you'd rather use your own multisite WordPress setup instead of `wp-env`, clone this repo directly into `wp-content/plugins/wpm-user-sync/` of your existing install. The plugin has no build step.

## Docker image overrides

The Makefile uses these images by default:

| Variable | Default | Where it's used |
| --- | --- | --- |
| `COMPOSER_IMAGE` | `composer:2` | `make install`, `make lint`, `make stan`, `make test*` |
| `WP_CLI_IMAGE` | `wordpress:cli` | `make i18n` |
| `PHP_IMAGE` | `php:8.3-cli` | `make psalm` |
| `DOCKER_NET` | `--network host` | `make i18n` |

Pin any of them for byte-for-byte reproducibility. `--network host` is **not** supported on Docker Desktop for macOS or Windows — set `DOCKER_NET=` (empty) on those platforms.

## Note on the PHP requirement vs. the dev tooling

The plugin's runtime minimum is **PHP 7.4** (declared in `Requires PHP:` in `wpm-user-sync.php`). PHPCS's PHPCompatibility ruleset validates that every line of plugin source runs cleanly on 7.4+.

The dev tooling itself (Psalm 5.x, PHPUnit 9.6) needs **PHP 7.4 or newer** to install — same minimum as the plugin. We pin `config.platform.php` in `composer.json` to `7.4.30` so Composer resolves the dev stack against that target.
