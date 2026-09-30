# WPM User Sync

> WordPress Multisite plugin that synchronises users across the sites of a network — when a user is created, when a site is created, or when a user's role changes on one site, the plugin propagates the change to the other sites. People an administrator removed from a site stay removed.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## What is this?

`WPM User Sync` (short for "WordPress Multi-Site User Synchronization") is a small admin-only plugin for WordPress Multisite networks. It exposes three independent toggles at network level — sync-on-new-site, sync-on-new-user, sync-on-role-change — and propagates the corresponding events to the sites you select. There are no defaults: nothing happens until a network admin opts each behaviour in.

The plugin is **multisite only**: on a single-site install it self-deactivates and prints a notice asking you to configure WordPress for multisite first.

## For end users

If you just want to **install and use** the plugin on your WordPress Multisite, get it from the official directory:

[wordpress.org/plugins/wpm-user-sync](https://wordpress.org/plugins/wpm-user-sync/)

User-facing documentation (features, installation, FAQ) lives in [`readme.txt`](readme.txt) — that's the version rendered on the wp.org plugin page.

## For developers

This README and the rest of this repository are aimed at developers who want to **contribute, fork, or run the plugin from source**.

## Quick start (developers)

```bash
git clone https://github.com/soydiloreto/wpm-user-sync.git
cd wpm-user-sync
make install     # composer install — populate vendor/
make env         # boots wp-env at http://localhost:8888 (multisite!)
```

When it finishes, open <http://localhost:8888/wp-admin/network/>. Log in with `admin` / `password`. The plugin is already mounted at `wp-content/plugins/wpm-user-sync/` — network-activate it from the **Plugins** screen and you'll find the **WPM User Sync** menu item under network admin.

`make help` lists every available target. For the full setup walkthrough, see [`docs/development.md`](docs/development.md). For multisite-specific dev notes, see [`docs/multisite-development.md`](docs/multisite-development.md).

## Repository layout

| Path | What it contains |
|------|------------------|
| `wpm-user-sync.php` | Plugin header + bootstrap: builds `WPMUS\Plugin`, which registers every hook. |
| `src/` | The plugin, PSR-4 under `WPMUS\`: `Plugin` (wiring), `Config` (toggles), `Sync/` (`SyncEngine`, the background `JobQueue` and `SyncJob`), `Repositories/` (sites and users), `Admin/` (network and site pages). |
| `legacy-deprecated.php` | Deprecated `wpmus_*` functions kept for back-compat. |
| `css/` | Admin styles enqueued via `wpmus_add_css()`. |
| `uninstall.php` | Removes the plugin's data on uninstall: its network options, the background sync queue and its cron event, and the record of removals. **Users, roles and memberships are NOT touched.** |
| `readme.txt` | wp.org plugin page content. |
| `.wordpress-org/` | wp.org listing visuals — banner, icon, screenshots. Uploaded by CI to the SVN `assets/` directory. |
| `tests/` | PHPUnit unit tests (`tests/Unit`) and multisite integration tests on wp-env (`tests/Integration`). |
| `docs/` | Developer documentation. |
| `.github/workflows/` | CI/CD: deploy to wp.org SVN on tag push, plus PR checks. |
| `.distignore` | Paths excluded from the wp.org deploy. |

## Documentation

For developers working on the plugin itself:

- [`CONTRIBUTING.md`](CONTRIBUTING.md) — branch naming, PR workflow, commit conventions, coding rules.
- [`docs/development.md`](docs/development.md) — local dev setup (`wp-env`, Docker, Make targets).
- [`docs/multisite-development.md`](docs/multisite-development.md) — multisite-specific notes: how to test sync flows, how to clear network options between runs, gotchas.
- [`docs/testing-and-quality.md`](docs/testing-and-quality.md) — PHPUnit, PHPCS, PHPStan, Psalm, i18n.
- [`docs/ai-tooling.md`](docs/ai-tooling.md) — what AI tooling the project uses.
- [`docs/ai-policy.md`](docs/ai-policy.md) — rules for contributors using AI agents.
- [`docs/release.md`](docs/release.md) — version bump flow, the `-dev` suffix convention, the wp.org SVN deploy.

## Contributing

Contributions are welcome — bug reports and pull requests. See [`CONTRIBUTING.md`](CONTRIBUTING.md) to get started.

## Reporting security issues

Please do not open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the private reporting process via GitHub Security Advisories.

## About

This plugin is **free and open-source software** under the GPL-2.0-or-later licence. It was created and is currently maintained by **Pablo Diloreto** ([@soydiloreto](https://github.com/soydiloreto)).

## License

GPL-2.0-or-later. See the [WordPress GPL page](https://wordpress.org/about/license/).
