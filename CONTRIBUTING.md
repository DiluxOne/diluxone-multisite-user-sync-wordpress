# Contributing to DiluxOne Multisite User Sync

The organisation's [contributing guide](https://github.com/DiluxOne/.github/blob/main/CONTRIBUTING.md)
has the rules every DiluxOne repository shares: the accepted issue a change
starts from, branches, titles, commits, the pull request and its review. This
page adds only what is this plugin's own.

Using the plugin (how do I…?, a user did not get a site) belongs in the
[wordpress.org support forum](https://wordpress.org/support/plugin/wpm-user-sync/),
where answers stay public for the next person. What is planned and what is
not is in [`docs/roadmap.md`](docs/roadmap.md).

## Before the pull request

1. Make the change with its tests at every layer it touches (unit,
   integration on a network, end-to-end, and the layout run, the baselines
   and the listing screenshots when a screen changes; see
   [`docs/testing-and-quality.md`](docs/testing-and-quality.md)), add its row
   to [`tests/e2e/COVERAGE.md`](tests/e2e/COVERAGE.md), and update any doc
   that describes what you changed. A change a user notices adds one bullet to
   the newest `= X.Y.Z =` entry of `readme.txt`, under its `Unreleased.` line;
   leave that line alone ([`docs/release.md`](docs/release.md)).
2. Run `make pre-pr` (needs `make env`): `make check` (PHPCS, PHPStan, Psalm,
   unit tests), the unit tests on PHP 8.0, the translations check, the docs
   check, `make coverage` (the unit, integration and end-to-end suites, the
   single-site check included, with their coverage floors), Plugin Check, and
   the local review (`make review-local`), which checks the branch, the title, the
   commits and, with `REVIEW_ARGS="--body-file .git/dx/pr.md"`, the description
   as CI will, and runs the same Claude review through the Claude Code CLI on
   your own account. It clones the organisation's shared scripts from
   [DiluxOne/.github](https://github.com/DiluxOne/.github) into `build/`.
3. If AI took part, the rules for AI-assisted work here are in
   [`docs/ai.md`](docs/ai.md).

On a pull request, besides the organisation's pipeline, CI runs the plugin's
own checks ([`plugin-checks.yml`](.github/workflows/plugin-checks.yml)):
syntax and unit tests on PHP 8.0 to 8.5, PHPCS with the WordPress Coding
Standards, PHPStan level 8, Psalm taint analysis, the review rules of a
WordPress plugin (every suppression listed with its reason in
[`.github/review-suppressions.yml`](.github/review-suppressions.yml)), i18n
extraction, strict Plugin Check on the shipped tree, readme and version
markers, integration tests on a wp-env network and the Playwright suite on a
wp-env network. The Claude review is guided by
[`docs/architecture.md`](docs/architecture.md) and [`AGENTS.md`](AGENTS.md).

## Coding rules the linters cannot express

- **PHP 8.0 and WordPress 6.6** are the minimums; the plugin needs Multisite.
- **No Composer dependencies at runtime.** `vendor/` never ships.
- **Every membership write goes through the sync engine**, with its guards:
  live sites of this network only, never a super admin, the destination's
  default role, removals respected, the re-entrancy flag.
- **Capability and nonce before anything else** in every handler.
- **Published names are permanent**: slug, text domain, options, meta keys,
  hooks, filters, page slugs, the 1.4 functions.
- **Every user-facing string** translatable with the `wpm-user-sync` domain,
  with a `/* translators: */` comment right before a placeholder; after
  changing strings, every locale updated (`make i18n-update`, translate,
  `make i18n-mo`, `make i18n-check`).
- **Input unslashed and sanitized, output escaped, SQL prepared.**

The full list, with the architecture and the review priorities:
[`docs/architecture.md`](docs/architecture.md).

## Versions and releases

Nobody types a version: it is computed from the `type:*` labels of what
merged, and `main`'s markers say the last version released (1.5.0). The
changelog is written as the changes merge, under the `Unreleased.` line of
the newest `readme.txt` entry; the maintainer removes that line when the
version is ready and approves the deployment. Never bump the version or
remove that line in your pull request. The whole flow:
[`docs/release.md`](docs/release.md).

## Licence

Your contributions are licensed under the [GPL-2.0-or-later](LICENSE).
