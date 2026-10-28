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
   that describes what you changed. A change a user notices adds its
   changelog line in a file of its own, `changelog.d/<issue>.md`: one bullet
   (`* …`), written for users. Never in `readme.txt`, whose changelog the
   release writes; leave its `Unreleased.` line alone
   ([`docs/release.md`](docs/release.md)).
2. Run `make pre-pr`, the one check before a pull request. It needs nothing
   started before it (no `make env`): on a fresh clone it installs the tools,
   clones the organisation's shared scripts from
   [DiluxOne/.github](https://github.com/DiluxOne/.github) into `build/` at
   the ref the workflows call, and runs every check the pull request and the
   merge queue run, one after the other, stopping at the first failure. Its
   suites run on a wp-env network of its own (ports 8902/8903), stopped at
   the end whatever happened. The version markers check needs `gh` logged in;
   without it, that one check says it did not run, and CI runs it.
   Then the local review (`dx check` runs `make pre-pr` and then it; by hand,
   `make review-local`), which checks the branch, the title, the commits and,
   with `REVIEW_ARGS="--body-file .git/dx/pr.md"`, the description as CI will,
   and runs the same Claude review through the Claude Code CLI on your own
   account. The coverage floors are `make coverage`
   ([`docs/testing-and-quality.md`](docs/testing-and-quality.md)).
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
  `make i18n-check`). The catalogues are kept in the form that merges (no
  source references, sorted); the `.mo` are built (`make i18n-mo`), never
  committed.
- **Input unslashed and sanitized, output escaped, SQL prepared.**

The full list, with the architecture and the review priorities:
[`docs/architecture.md`](docs/architecture.md).

## Versions and releases

Nobody types a version: it is computed from the `type:*` labels of what
merged, and `main`'s markers say the last version released. The
changelog is written as the changes merge, one `changelog.d/<issue>.md` per
change; the release pull request writes them into `readme.txt`. The
`Unreleased.` line of the newest `readme.txt` entry is the release switch:
the maintainer removes it when the version is ready and approves the
deployment. Never bump the version or remove that line in your pull
request. The whole flow:
[`docs/release.md`](docs/release.md).

## Licence

Your contributions are licensed under the [GPL-2.0-or-later](LICENSE).
