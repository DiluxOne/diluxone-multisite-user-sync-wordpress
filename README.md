# DiluxOne Multisite User Sync

> A WordPress Multisite plugin that adds the network's users to its sites: automatically when a user or a site is created or a role changes, or on demand from Network Admin. Formerly *WPM User Sync*; wordpress.org slug `wpm-user-sync`.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## For people who run a network

Install it from the official directory, [wordpress.org/plugins/wpm-user-sync](https://wordpress.org/plugins/wpm-user-sync/), and network-activate it. What it does, how to set it up and the FAQ are in [`readme.txt`](readme.txt), the page wordpress.org shows. Usage questions go to the [support forum](https://wordpress.org/support/plugin/wpm-user-sync/).

In short:

- **Three triggers**, each on or off: a new user joins every site; a new site gets every user; a role changed on one site is copied to the user's other sites.
- **Manual syncs** from Network Admin (every site, or the sites you tick) and from a site's dashboard (that site only, super admins only).
- **Safe by default**: each site's default role, never a copied one; super admins and archived, spam or deleted sites left alone; people removed from a site stay removed; administrator never copied; only super admins see the plugin.
- **Big networks**: a sync too large for one request runs in the background through WP-Cron, with its progress on screen.

## For developers

```bash
git clone https://github.com/DiluxOne/diluxone-multisite-user-sync-wordpress.git
cd <its folder>
make install     # composer, npm, Playwright's Chromium
make env         # a subdirectory network at http://localhost:8898 (admin / password)
make check       # lint, PHPStan, Psalm, unit tests
```

`make help` lists every target. The filters and actions the plugin offers are in [`docs/extending.md`](docs/extending.md).

| Read | For |
| --- | --- |
| [`AGENTS.md`](AGENTS.md) | The rules a coding agent (and anyone) follows here, short. |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Issues, branches, pull requests, what CI enforces. |
| [`docs/architecture.md`](docs/architecture.md) | How it works, the hard rules, what the review looks for. |
| [`docs/extending.md`](docs/extending.md) | Filters, actions, what is stored, backwards compatibility. |
| [`docs/development.md`](docs/development.md) | Local setup, the networks and their ports, every Make target. |
| [`docs/testing-and-quality.md`](docs/testing-and-quality.md) | Every quality gate and how to run it. |
| [`tests/e2e/COVERAGE.md`](tests/e2e/COVERAGE.md) | Which test covers which feature, at each layer. |
| [`docs/release.md`](docs/release.md) | Versions, the changelog switch, the move into the DiluxOne organisation. |
| [`docs/roadmap.md`](docs/roadmap.md) | What is planned and what is not. |
| [`docs/ai.md`](docs/ai.md) | How AI is used here and the rules for AI-assisted work. |

## Repository layout

| Path | What |
| --- | --- |
| `wpm-user-sync.php` | Headers, `WPMUS_VERSION`, bootstrap. |
| `src/` | The plugin, PSR-4 under `WPMUS\`: `Plugin`, `Config`, `RequirementsChecker`, `Sync/` (engine, queue, job), `Repositories/`, `Admin/`. |
| `legacy-deprecated.php` | The 1.4 function names, deprecated. |
| `css/` | The admin stylesheet, loaded on the plugin's screens only. |
| `languages/` | The `.pot` and eight complete locales (`.po`/`.mo`). |
| `uninstall.php` | Removes the plugin's data; never users or memberships. |
| `readme.txt`, `.wordpress-org/` | The wordpress.org page and its banner, icon and screenshots. |
| `tests/` | Unit (`Unit/`), integration on a network (`Integration/`), end-to-end with Playwright (`e2e/`). |
| `.github/` | The plugin's workflow callers, its review policy and suppressions; the rest comes from the DiluxOne organisation. |

## Security

Do not open a public issue for a vulnerability: report it privately from this repository's **Security** tab › **Report a vulnerability**, as the organisation's [security policy](https://github.com/DiluxOne/.github/blob/main/SECURITY.md) says.

## Licence

GPL-2.0-or-later ([LICENSE](LICENSE)). Created and maintained by Pablo Ariel Di Loreto, part of the [DiluxOne plugins for WordPress](https://diluxone.com/plugins-wordpress).
