# Contributing to WPM User Sync

Thanks for your interest in contributing. This document covers how to report issues, submit pull requests, and what to expect from the review process.

## Reporting bugs and requesting features

Open a [new issue](https://github.com/soydiloreto/wpm-user-sync/issues/new). Useful kinds of issue:

- **Bug report** — something is broken or behaves unexpectedly.
- **Feature request** — you'd like the plugin to do something it doesn't do today.
- **Multisite scenario** — a specific multisite topology (subdomain, subdirectory, mapped domains) where sync behaves differently than expected.

For **end-user support questions** please use the [wp.org support forum](https://wordpress.org/support/plugin/wpm-user-sync/) instead.

For **security vulnerabilities**, see [SECURITY.md](SECURITY.md). Do not open a public issue.

## Pull request workflow

1. **Fork** the repository and clone your fork locally.
2. **Branch** from `main` using a descriptive name following the convention below.
3. **Commit** your changes (see commit message conventions below).
4. **Push** the branch to your fork.
5. **Open a pull request** against `main`. Explain *why* the change matters, not just *what* it does.
6. **Wait for CI to pass.** All required checks must be green before review.
7. **Address review feedback** by pushing additional commits to the same branch (we squash on merge, so commit count doesn't matter).

### Branch naming

Use one of these prefixes, followed by a short kebab-case description:

| Prefix | Used for |
|--------|----------|
| `feat/` | New user-visible functionality |
| `fix/` | Bug fixes |
| `chore/` | Maintenance tasks, version bumps, no behavior change |
| `docs/` | Documentation-only changes |
| `ci/` | CI/CD configuration changes |
| `refactor/` | Internal restructure with no behavior change |
| `style/` | Code style / linter / formatting changes |
| `test/` | Test-only additions or changes |

Examples: `fix/sync-loop-on-new-blog`, `refactor/oop-architecture`, `docs/multisite-readme`.

### Commit messages

We follow [Conventional Commits](https://www.conventionalcommits.org/). The first line is `<type>(<optional-scope>): <subject>`, where type is one of `feat`, `fix`, `chore`, `docs`, `ci`, `refactor`, `style`, `test`. Examples:

```
feat(sync): propagate role removal across network
fix(sync): avoid double-sync when wpmu_new_user fires after wp_login
chore: bump to 1.5.0
```

The body explains the *why* — context, motivation, alternatives considered.

## Coding conventions

The project enforces a strict quality stack on every PR. Run `make check` locally before pushing.

| Gate | Tool | Make target |
| --- | --- | --- |
| Code style | PHP_CodeSniffer + WordPress Coding Standards | `make lint` (auto-fix: `make lint-fix`) |
| Static analysis | PHPStan level 8, no baseline | `make stan` |
| Security taint analysis | Psalm in taint-only mode (XSS, SQLi, RCE) | `make psalm` |
| i18n | `wp i18n make-pot` + warning-grep | `make i18n` |
| Unit tests | PHPUnit | `make test` |

See [`docs/testing-and-quality.md`](docs/testing-and-quality.md) for what each layer enforces.

A few hard rules the linters can't fully express:

- **PHP 7.4+** is the minimum supported version. Don't use 8.0+ syntax (named arguments, match expressions, constructor promotion, nullsafe `?->`, enums, readonly, intersection types, first-class callable syntax) without a 7.4 fallback.
- **Multisite-only.** This plugin runs on multisite and self-deactivates on single-site installs. Don't add code that assumes single-site context.
- **Use `*_site_option()` for network-level config**, not `*_option()`. Use `get_sites()` to enumerate, never the deprecated `wp_get_sites()`.
- **`switch_to_blog($id)` MUST be paired with `restore_current_blog()`.** Cross-site sync code that switches context must restore on every code path, including early returns and exceptions.
- **No hard production dependencies on Composer packages.** The plugin must run on a fresh WordPress install with no extra setup. `composer install` produces only dev tooling — `vendor/` never ships to wp.org.
- **All plugin globals are prefixed `wpmus_`** (legacy) or `WPMUS\` (new OOP code from PR 2 onward). Enforced by PHPCS.

## Versioning

We follow [Semantic Versioning](https://semver.org/) for the plugin's public version (`MAJOR.MINOR.PATCH`):

- **PATCH** (1.4.0 → 1.4.1): bug fixes only, no behavior change beyond the fix itself.
- **MINOR** (1.4.x → 1.5.0): new user-visible functionality, backwards-compatible.
- **MAJOR** (1.x → 2.0): backwards-incompatible changes (rare).

Repository-only changes (this CONTRIBUTING.md, CI workflows, dev tooling, etc.) **do not** trigger a version bump — they are excluded from the wp.org deploy via [`.distignore`](.distignore) and are invisible to end users.

### `-dev` suffix on `main`

The `Version:` header in `wpm-user-sync.php` carries a **`-dev` suffix on `main`** to signal that the working tree is in active development and not a tagged release.

| Where | Looks like | Means |
|---|---|---|
| `main` between releases | `Version: 1.5.0-dev` | "Working towards 1.5.0; this is NOT a release" |
| Final release commit | `Version: 1.5.0` | "This commit IS the 1.5.0 release; tag it" |
| Tag (e.g. `1.5.0`) | snapshot of the release commit | What ends up at wp.org and on user sites |
| `main` after release | `Version: 1.6.0-dev` | "Now working towards 1.6.0" |

The `Stable tag:` in `readme.txt` does NOT carry the suffix — it always holds the **last published release version**.

Accepted pre-release suffixes are `-dev`, `-alpha`, `-beta`, `-rc` (optionally followed by `.N`).

## Releasing (maintainers only)

See [`docs/release.md`](docs/release.md) for the full release flow.

## AI-assisted contributions

If you use Copilot, Claude, GPT, Cursor, or any other AI tool to help write code, read [`docs/ai-policy.md`](docs/ai-policy.md) before opening a PR.

## License

By contributing, you agree that your contributions will be licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
