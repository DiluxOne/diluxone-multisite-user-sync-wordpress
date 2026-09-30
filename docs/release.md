# Releases: how a change becomes a version

For everyone who touches this repository: outside contributors, maintainers
and coding agents. End users get the plugin from
[wordpress.org/plugins/wpm-user-sync](https://wordpress.org/plugins/wpm-user-sync/)
and never need this page. The short, enforceable version of these rules is
in [`AGENTS.md`](../AGENTS.md).

## Where things stand

The plugin is published on wordpress.org as `wpm-user-sync`; the last release
is **1.5.0**, and `main`'s three version markers say so. It is moving into
the DiluxOne organisation on GitHub, and from that move on it is released by
the organisation's pipeline, as every DiluxOne plugin is. Until then **nothing
is released**: the workflows in [`.github/workflows/`](../.github/workflows/)
call reusable workflows, secrets and GitHub Apps that exist only inside the
organisation, and they do not run here. What the move takes is in
[Moving into DiluxOne](#moving-into-diluxone) below.

The next version is being written under `= 2.0.0 =` in `readme.txt`, with its
`Unreleased.` line in place.

## Who does what

| | Outside contributor | Maintainer | The pipeline |
| --- | --- | --- | --- |
| Writes the change and its changelog bullet | yes, in the pull request | yes | never |
| Decides the type of the change (`type:*` label) | no | can override with a label | the Claude review, from the diff |
| Computes the next version | no | no | yes, from the labels |
| Decides that a version is ready | no | yes, by removing one line in `readme.txt` | never |
| Approves the publication | no | yes, in the `wordpress-org` environment | never |
| Sets the version markers | never | yes, in the release pull request | checks them on every pull request and before deploying |
| Deploys, tags, creates the release | never | never | yes, after the approval |

Nobody types a version into a file by hand, nobody touches SVN, and the tag
is created by the job.

## The flow

1. **A pull request merges into `main`**, squash-merged, green
   ([`CONTRIBUTING.md`](../CONTRIBUTING.md)). The Claude review labelled it
   `type:*` from the diff (a maintainer's label wins).
2. **The push to `main` runs [`Release`](../.github/workflows/release.yml).**
   It computes the next version from the `type:*` labels merged since the last
   `X.Y.Z` tag (`type:breaking` → major, `type:feat` → minor, `type:fix` or
   `type:perf` → patch; a maintainer's `version:*` label wins).
3. **A development build is published**, every time: the shipped tree stamped
   `<next>-dev.<N>`, as the one **Development build** pre-release (tag `dev`,
   asset `wpm-user-sync.zip`), replaced on every push. `make dist` builds the
   same tree locally.
4. **The readme says whether the version is ready.** The newest entry under
   `== Changelog ==` is headed `= X.Y.Z =` and, while the version is being
   built, its first line is exactly `Unreleased.`. With that line in place the
   run ends green as **Not ready** and nothing waits for anyone.
5. **The maintainer removes the `Unreleased.` line in a pull request** that
   also sets the three version markers to the version the labels give
   (`scripts/release-markers.sh prepare` in DiluxOne/.github does both). That
   is the release decision.
6. **The maintainer approves the deployment** in the `wordpress-org`
   environment.
7. **The job publishes** from the approved commit: checks the markers,
   validates the changelog, commits to the wordpress.org SVN (trunk, the tag
   and `.wordpress-org/` as the listing assets), creates the tag `X.Y.Z` and
   the GitHub release. wordpress.org holds an update for a few hours before
   networks receive it.

## Versions

Always three numbers, `X.Y.Z`:

| Part | When | Label |
| --- | --- | --- |
| **Major** | A big new capability, or anything that breaks something that worked | `version:major`, or `type:breaking` |
| **Minor** | Additions and improvements, compatible | `type:feat` |
| **Patch** | Bug fixes, security fixes, performance | `type:fix`, `type:perf` |

Breaking means a network or a developer has to change something: a new
minimum PHP or WordPress beyond what a minor announced, a removed setting, a
renamed hook, filter or stored key. `docs`, `test`, `ci`, `chore`, `build`,
`refactor`, `style` release nothing on their own.

The three version markers are the `Version:` header and the `WPMUS_VERSION`
constant in `wpm-user-sync.php`, and `Stable tag:` in `readme.txt`. They
always name a real version: the last one released (1.5.0 today), or, in the
release pull request and on `main` after it, the one being released.

**2.0.0.** The entry is headed `= 2.0.0 =`, a major version: besides the fixes and the new filters it raises Requires PHP from 7.4 to 8.0, so a site still on 7.4 stops receiving updates, and the automatic triggers behave differently (no sync on every sign-in, removals respected, roles never copied from the main site). The Upgrade Notice says so.

## The changelog is the release switch

```
= 2.0.0 =
Unreleased.

* The plugin is now called DiluxOne Multisite User Sync…
* Security: the new-site trigger gave each new site's members…
```

- **Write the notes as the changes merge**: a pull request that changes what a
  user sees adds its bullet under `Unreleased.`, written for users.
- **Never remove the `Unreleased.` line as part of another change.** The pull
  request that removes it contains nothing else but the version markers.
- **After a release**, the next change that deserves a bullet opens the next
  entry, `= X.Y.Z =` with `Unreleased.`, above the released one.

## Moving into DiluxOne

The repository already carries everything the organisation's adoption guide
asks for ([DiluxOne/.github, "Adopt it in a new repository"](https://github.com/DiluxOne/.github#adopt-it-in-a-new-repository)):
the callers in `.github/workflows/` (`pull-request.yml`,
`pull-request-edited.yml`, `pull-request-comments.yml`, `issues.yml`,
`release.yml`, `svn-auth-check.yml`) with `slug: wpm-user-sync`,
`main-file: wpm-user-sync.php` and `version-constant: WPMUS_VERSION`;
`.github/review-policy.yml`; `AGENTS.md`, `docs/architecture.md` and
`docs/roadmap.md`; the pull request template, CODEOWNERS and Dependabot. What
the move itself takes, in order:

1. **Transfer the repository** into DiluxOne (GitHub keeps redirects from the
   old URL). Choose its name then; nothing here depends on it
   ([`development.md`](development.md#the-repository-name-is-not-the-plugin-slug)).
2. **Settings:** squash merge only, auto-merge allowed, delete the branch on
   merge, squash title from the PR title and body from the PR body;
   Dependabot alerts and updates; private vulnerability reporting (SECURITY.md
   points to it).
3. **Ruleset on `main`:** changes only through a pull request, linear history,
   conversations resolved, required checks green and up to date:
   `conventions / Conventions (branch, title, commits)`,
   `conventions / Docs (links and names)`, `review / Claude review`, and every
   `checks / …` and `tests / …` job.
4. **Two rulesets on tags `X.Y.Z`:** creation for administrators and the
   `dilux-release` App only; nobody deletes or moves them.
5. **Apps:** install `dilux-bot` and `dilux-release` on the repository; add
   `dilux-release` to the tag-creation ruleset's bypass list.
6. **Environment `wordpress-org`:** deployment policy tag `*.*.*` plus branch
   `main`; required reviewers (the maintainers who may publish); environment
   secrets `SVN_USERNAME`, `SVN_PASSWORD` (the SVN password of the account that
   owns `wpm-user-sync`, not its login password) and
   `DILUX_RELEASE_PRIVATE_KEY`; environment variable
   `DILUX_RELEASE_CLIENT_ID`. Organisation secrets (`ANTHROPIC_API_KEY`,
   `DILUX_BOT_PRIVATE_KEY`, `DILUX_BOT_CLIENT_ID`) are inherited.
7. **Prove the credentials:** run **SVN credentials check** from the Actions
   tab.
8. **Rehearse:** `release.yml` starts with `dry-run: true`. The first push to
   `main` rehearses everything but the SVN commit, the tag and the release;
   when a rehearsal approved in the environment looks right, a pull request of
   its own sets `dry-run: false`.
9. **Fill in what could not be known before the move:** the repository's
   links in [`README.md`](../README.md) and `readme.txt` ("The source… on
   GitHub"), and, if the old display name should stop coming back, the
   `retired-names` of both pull-request workflows.

The previous release workflow (`deploy.yml`, a tag pushed by hand) is gone;
there is no way to publish from this repository until the steps above are
done.

## Release tags are permanent

Every `X.Y.Z` tag is the record of what went to every network: nobody deletes
or moves one. A deploy that failed before SVN is fixed and re-run; a wrong
release is fixed by the next patch. There is no undo on wordpress.org: to
roll back, ship `X.Y.Z+1` with the earlier code.
