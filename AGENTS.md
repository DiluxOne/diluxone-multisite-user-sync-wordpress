# AGENTS.md

<!-- dx:org:start (kept in step by DiluxOne/.github, scripts/sync-repos.py; edit it there) -->
## The organisation's rules (the same in every DiluxOne repository)

You are working in a DiluxOne repository. Whoever you work for (the
maintainer, or someone contributing from a fork), these hold here, and the
step-by-step flow with its commands is in
[DiluxOne/.github, `docs/agents.md`](https://github.com/DiluxOne/.github/blob/main/docs/agents.md):

1. **Nothing starts without an accepted issue.** Find the issue, or open one
   with the form that fits (it sets the issue's Type: Bug, Feature, Docs or
   Task), and wait until a maintainer adds the `accepted` label. Never add it
   yourself. The pull request says `Closes #<number>`; CI fails it otherwise.
2. **The pull request's type fits the issue's Type**: `feat` (or any `!`)
   closes a Feature, `fix` a Bug; other types close any accepted issue.
3. **Branch** `<type>/<number>-<short-kebab>`; **commit and title** as a
   Conventional Commit of 72 characters or fewer (CI rejects more than 100);
   bodies are plain paragraphs, never hard-wrapped; no `Claude-Session:`.
4. **Check before the pull request** (`dx check`, or the repository's
   `make pre-pr`) and open it (`dx pr`) only when the person you work for
   says so.
5. **Never** push to `main`, create or move a tag, or approve a release.
6. **Say AI took part** with one line at the end of what you write on GitHub:
   `🤖 AI-generated · <model> (<maker>)` when you wrote it,
   `🤖 AI-assisted · <model> (<maker>)` when a person did with your help.
   Never "Generated with …".
<!-- dx:org:end -->

What follows is this repository's own: what an agent working here (Claude
Code, Codex, Cursor, …) and the person it works with must follow on top of
the organisation's rules above. Humans: the same rules live in
[`CONTRIBUTING.md`](CONTRIBUTING.md) and [`docs/ai.md`](docs/ai.md).

## What this is

DiluxOne Multisite User Sync, a WordPress **network** plugin published on
wordpress.org as `wpm-user-sync` (formerly "WPM User Sync"). It adds a
network's users to its sites: automatically when a user or a site is created
or a role changes, or on demand from Network Admin, with big syncs run in
the background through WP-Cron. `Stable tag:` in `readme.txt` says which
version is on wordpress.org.
Architecture, hard rules and review priorities:
[`docs/architecture.md`](docs/architecture.md). The public API (filters and
actions): [`docs/extending.md`](docs/extending.md).

The slug, the text domain, the `wpmus_` prefixes, every stored option and
meta key and the `WPMUS` namespace come from the published plugin and are
permanent. Only the display name changed. The repository folder may be named
otherwise ([`docs/development.md`](docs/development.md#the-repository-name-is-not-the-plugin-slug)).

## Your workflow

Everything a pull request is checked on runs on this machine first, so a
pull request is opened only when the branch is already clean. The steps
around it (the accepted issue, `dx start`, `dx pr`) are the organisation's
flow above.

1. **Set up once.** Docker, Node.js (for `npx`), GNU make and git; no PHP on
   the host. `make install` (dev tools, npm packages, Playwright's Chromium),
   then `make env`: a subdirectory network at http://localhost:8898
   (admin / password) and a second one at :8899 for the integration suite.
2. **Make the change** with its tests at every layer it touches (unit,
   integration on the tests network, end-to-end on the dev network, the
   single-site check when activation changes), the docs that describe it, the
   layout and snapshot baselines when a screen changes, and, when a user
   notices it, one bullet under the newest `= X.Y.Z =` entry of `readme.txt`,
   below its `Unreleased.` line. A new screen or tab goes into
   `tests/e2e/support/screens.ts`; a new feature or state into
   [`tests/e2e/COVERAGE.md`](tests/e2e/COVERAGE.md).
3. **Write the pull request description** in `.git/dx/pr.md` (`dx start`
   writes it, already closing the issue): fill in "📝 What changes" and
   "💡 Why"; it becomes the commit body on `main`.
4. **Run everything:** `make pre-pr REVIEW_ARGS="--title '…' --body-file .git/dx/pr.md"`:
   `make check`, `make test-unit-min` (PHP 8.0), `make i18n-check`,
   `make docs-check`, `make coverage` (the unit, integration and end-to-end
   suites with Xdebug, the single-site check included, failing below the
   coverage floors), Plugin Check and the organisation's local review.
5. **Fix what the review found** (`.git/dx-review/findings.md`) and run step 4
   again until it says **Ready for a pull request**.

## How work reaches `main`

Only through a pull request, squash-merged: its title becomes the commit on
`main` and its description the body. Nobody pushes to `main`, admins
included. The organisation's required pipeline (`org-pull-request.yml` in
DiluxOne/.github) holds every pull request to the rules above; the plugin's
own checks are in [`.github/workflows/plugin-checks.yml`](.github/workflows/plugin-checks.yml).
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`,
`ci`, `chore`, `revert`.

## How a change becomes a release

The whole flow is [`docs/release.md`](docs/release.md). What you must do, and
never do, in a change:

- **The version is never typed.** `main` keeps the last released version in
  its three markers (`Version:` and `WPMUS_VERSION` in `wpm-user-sync.php`,
  `Stable tag:` in `readme.txt`).
- **Write the changelog in the same pull request.** A change a user notices
  adds one bullet under the newest `= X.Y.Z =` entry of `readme.txt`, below
  its first line `Unreleased.`, written for users.
- **The line `Unreleased.` is the release switch.** The pull request that
  removes it is the maintainer's decision to release. Never remove it as part
  of another change.

## Rules you must not break

- **Never** create a GitHub release or touch the wordpress.org SVN. A tag
  `X.Y.Z` publishes the plugin to every network that has it and is permanent.
- **Never** bump the version markers.
- **Never** commit secrets: no `.env*`, no SVN password, no real person's data.
- **PHP 8.0 and WordPress 6.6** are the minimums; the runtime has no Composer
  dependencies and `vendor/` never ships.
- **Never rename** the slug, the text domain, a `wpmus_` option, meta key,
  hook or filter, a page slug, or a legacy function: networks in production
  depend on them. A stored key changes only with a migration.
- **No sync ever duplicates a user**, changes an existing membership's role
  (the role trigger aside), adds a super admin, writes to an archived, spam
  or deleted site or another network's site, or puts back someone an
  administrator removed (unless a person ticks "Also add back").
- **Pulling the network into a site is a network decision:** every screen and
  handler checks `manage_network_options` or `manage_network_users` and a
  nonce before anything else.
- **Every user-facing string** goes through a translation function with the
  `wpm-user-sync` text domain; input is unslashed and sanitized, output
  escaped, SQL prepared.
- **The plugin knows nothing about other plugins.** Integration goes through
  its filters ([`docs/extending.md`](docs/extending.md)).
- **Docs change in the same PR as the behaviour they describe.** That
  includes this file, `docs/architecture.md`, `docs/extending.md`,
  `readme.txt` and `docs/`. A doc that describes something the code no longer
  does is a bug.

## Where the details are

| Question | Read |
| --- | --- |
| How the plugin works, its hard rules, what the review looks for | [`docs/architecture.md`](docs/architecture.md) |
| The filters and actions an add-on or a site builds on | [`docs/extending.md`](docs/extending.md) |
| Local setup, Make targets, ports, repository name vs plugin slug | [`docs/development.md`](docs/development.md) |
| Every quality gate, what runs when, how to run each | [`docs/testing-and-quality.md`](docs/testing-and-quality.md) |
| The end-to-end suite: what has to be up, how it is built | [`tests/e2e/README.md`](tests/e2e/README.md) |
| Which test covers which feature | [`tests/e2e/COVERAGE.md`](tests/e2e/COVERAGE.md) |
| The flow step by step: issue, branch, check, pull request | [DiluxOne/.github, `docs/agents.md`](https://github.com/DiluxOne/.github/blob/main/docs/agents.md) |
| What this plugin adds for contributors | [`CONTRIBUTING.md`](CONTRIBUTING.md) |
| Versions, the changelog switch, development builds, the organisation's setup | [`docs/release.md`](docs/release.md) |
| What is planned and what is not | [`docs/roadmap.md`](docs/roadmap.md) |
| How AI is used here and the rules for AI-assisted work | [`docs/ai.md`](docs/ai.md) |
| The shared workflows, policy and review profiles | [DiluxOne/.github](https://github.com/DiluxOne/.github) |
