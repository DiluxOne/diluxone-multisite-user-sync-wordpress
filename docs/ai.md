# AI in this project

How AI is used to build and review the plugin, and the rules for anyone who
contributes with AI. Coding agents read the short, enforceable version in
[`AGENTS.md`](../AGENTS.md).

## How the plugin is built

The maintainer writes most changes with Claude Code, reads every diff, runs
the tests and signs the commit. The rules an agent could break by mistake are
enforced by the tests, the linters and, once the repository is in the DiluxOne
organisation, by CI and the rulesets: `AGENTS.md` is guidance, the checks are
the guarantee. Every pull request carries one sober line saying how it was
made, for example `🤖 AI-assisted · Claude Opus 5.5 (Anthropic)`; that line
lands in the commit on `main`.

This plugin runs across whole networks: one wrong line in a sync is a wrong
role on every site at once. That is why its hard rules
([`architecture.md`](architecture.md#hard-rules)) are tested at every layer,
whoever wrote the change.

## The review on every pull request

In the organisation, every pull request from a branch of the repository is
reviewed by Claude through the shared
[`claude-review`](https://github.com/DiluxOne/.github/blob/main/.github/workflows/claude-review.yml)
workflow, after the conventions and the fast gates pass.

- **What it reads:** the organisation's review profiles (general and
  WordPress plugin), this repository's [`architecture.md`](architecture.md)
  and [`AGENTS.md`](../AGENTS.md), and the diff.
- **What it does:** one inline comment per blocker or major problem, labels
  `risk:*`, `complexity:*` and `type:*` (the type decides the next version),
  one summary comment with what the run cost. The check fails on a blocker.
- **What it cannot do:** lower the risk that
  [`.github/review-policy.yml`](../.github/review-policy.yml) sets from the
  paths (the main file, the uninstaller, the sync engine, the repositories,
  the admin screens and handlers are always high risk), push code, or merge.
- **Who posts:** the `dilux-bot` App. Mention `@dilux-bot` in a thread and it
  answers there.

`make review-local` runs the same review before the pull request exists, on
your own Claude account ([`CONTRIBUTING.md`](../CONTRIBUTING.md)).

Pull requests from forks are not reviewed automatically: no code from outside
the repository runs with the organisation's keys.

## Issues

When an issue opens, Claude classifies it from [`roadmap.md`](roadmap.md), the
README and `readme.txt` (a bug to reproduce, missing information, works as
documented, feature request, usage question, duplicate, vulnerability posted
in public), applies one label and replies once. It never closes an issue,
promises a fix or gives a date. A reproducible bug gets a unit test written
to fail if the bug exists, run on a runner without secrets.

## What is never automated

- **Releases.** Only the maintainer approves a publication; the tag and the
  wordpress.org commit are made by the release job after that approval
  ([`release.md`](release.md)).
- **Merging anything that is not low risk.**
- **Changing the review rules**: always a pull request a human approves.

## Rules for contributing with AI

Use any tool you like. These rules apply the moment you open a pull request.

1. **You sign the commit, you own the code.** "The AI wrote it" is not a
   defence.
2. **You read what you commit**, line by line. If you do not understand a
   generated chunk, do not push it.
3. **The tests pass because the runner says so**, not because a model said
   so. Run `make pre-pr`.
4. **No secrets into AI services**: no `wp-config.php`, SVN passwords, keys or
   database dumps. If you slip, rotate at once.
5. **No prompt injection** in commits, comments, code or docs. A pull request
   that tries to steer a reviewer's tooling is rejected on sight.
6. **Verify what the model invents.** A multisite function or hook that does
   not exist usually fails the build; the rest reaches production networks.
7. **Same standards as hand-written code.** If CI fails, fix the code, not the
   rule.
8. **Say that AI was involved**, in one line at the end of the description:
   `🤖 AI-assisted · <model> (<maker>)`, or `AI-generated` when the model wrote
   it and a person reviewed it. CI rejects "Generated with …" footers and
   `Claude-Session:` trailers.
9. **One good pull request beats twenty speculative ones.**

When an AI-assisted change causes a regression: roll forward with a fix, open
an issue saying why the checks did not catch it, and strengthen the gate that
should have.
