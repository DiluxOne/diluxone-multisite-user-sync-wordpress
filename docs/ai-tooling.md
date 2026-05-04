# AI tooling

What AI-powered tools the project uses, what they're configured to do, and what they cost.

For our **policy** on how AI may be used by contributors and maintainers see [`ai-policy.md`](ai-policy.md). This document only describes the tooling itself.

## GitHub Copilot Code Review

Every PR to this repository is automatically reviewed by GitHub Copilot. Copilot leaves inline comments on lines it finds suspicious; the maintainer either addresses them or marks the threads resolved with a justification.

**Configuration** lives in [`.github/copilot-instructions.md`](../.github/copilot-instructions.md). That file is read on every review and tells Copilot:

- The plugin's scope in one screen — what it does (multisite user synchronisation), what it doesn't do.
- The architecture quick-reference: each PHP file's role, the three sync triggers, the network/site admin split.
- Hard rules to enforce (multisite specifics, security/capability/nonce/escape, WP conventions, PHP 7.4 baseline, prefix discipline).
- Style rules **not** to comment on (Yoda conditions, the legacy procedural style being refactored, the planned removal of globals).
- What to actively look for (missing capability checks, missing nonces, missing escaping, unpaired `switch_to_blog`, PHP 8.0+ syntax breaking the 7.4 contract).

**Required reviewer.** [`.github/CODEOWNERS`](../.github/CODEOWNERS) assigns the maintainer as a reviewer on every PR.

**Cost.** Copilot Code Review is a *premium feature*. Each review session consumes **one premium request** from the maintainer's monthly Copilot allowance. The project doesn't pay for this directly — it comes out of the maintainer's personal Copilot subscription. See <https://docs.github.com/copilot/concepts/billing/copilot-requests> for the current quota tables.

**Model.** GitHub describes Copilot Code Review as "a purpose-built product that uses a carefully tuned mix of models". They don't disclose the specific model and don't allow switching.

## What is **not** automated

- **Code generation / patching.** Copilot Code Review **comments** but never pushes code on its own.
- **Automatic merging.** No bot has the right to merge a PR. Branch protection requires explicit human action via `gh pr merge` (or the GitHub UI).
- **Auto-applied review fixes.** Copilot offers "apply suggestion" buttons in its review comments. The maintainer ignores them — every fix that lands on `main` was deliberately written and reviewed by a human, even if a model proposed the wording.

## Why we use it

The project is one maintainer. A second pair of eyes catches things a tired human will miss at 1 a.m., and Copilot Code Review is good at the boring catches: missed escaping, missed prefix, copy-paste mistake in a return type, off-by-one in a sprintf placeholder, subtle multisite issues like a `switch_to_blog` without `restore_current_blog`.

It also creates an audit trail. Every PR carries a public record of what was flagged and what was decided about each flag.
