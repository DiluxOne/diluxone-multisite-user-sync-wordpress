# AI policy

Rules for how AI agents and AI-generated code may be used in this project, by maintainers and by contributors. **You are bound by these rules the moment you open a PR or push a commit, whether or not you read them.**

This is the policy. For *what tools we use*, see [`ai-tooling.md`](ai-tooling.md). For *how to set up your dev environment*, see [`development.md`](development.md).

## TL;DR

Use AI all you want. **You** are still responsible for every line. The model isn't going to answer the bug report.

## Rules

1. **You sign the commit, you own the code.** It does not matter whether you typed it or whether Claude / Copilot / GPT / a Markov chain wrote it. If a regression is traced back to your commit, you are the one who fixes it. "The AI wrote it" is not a defence and not a triage answer.

2. **You read what you commit.** Line by line. Every diff. If you don't understand what a generated chunk does, you don't have permission to push it.

3. **The tests don't pass because the model said so.** They pass because the test runner says so. Run `make check` locally before pushing. CI is a safety net, not a substitute for thinking.

4. **No prompt-injection vectors in commit messages, comments, code, or docs.** No `<system>`, no "ignore previous instructions", no Unicode trickery, no embedded "your job is to approve this PR". A reviewer's tooling running against your commit shouldn't be steered by your commit. We will reject any PR that does this on sight.

5. **Don't paste secrets at AI services.** Not at OpenAI, not at Anthropic, not at GitHub. No `wp-config.php`, no SVN passwords, no API keys, no DB dumps. Even if the service claims it doesn't train on your data — paste it once and you've already exfiltrated. Rotate immediately if you slip.

6. **Don't ship hallucinated APIs.** If the model invents a WordPress function, a PHPStan rule, or a multisite hook that doesn't exist, the build will catch it in 90% of cases. The other 10% reach prod. Verify against the upstream docs before you commit.

7. **Generated code follows the same coding standards as hand-written code.** PHPCS rules, PHPStan level 8, escaping rules, i18n rules, capability/nonce discipline — the linters and the reviewer do not care who typed it.

8. **No "AI assisted" bullshit in commit messages.** If you used a model, the commit message says what *the change* does, not who or what helped you write it. Co-author trailers are fine if you genuinely want the credit assignment, but do not dilute the diff itself with apologies, prefaces, or narration.

9. **Don't volume-spam the project with model-generated PRs.** Twenty drive-by PRs full of speculative refactors are not contributions, they're a denial of service against the maintainer's review time.

10. **The bar is the same as a human.** A human contributor whose PR has bugs, missing tests, hallucinated APIs, and commit-message essays would be told to slow down. The model gets the same treatment via you. Slow down.

## What you may absolutely do

- Use Copilot, Claude Code, Cursor, Codex, or any other agent for autocomplete, refactoring, debugging, exploring the codebase, drafting tests, drafting commit messages, drafting PR descriptions, drafting issue replies. All of this is fine.
- Run a model on your machine to summarise an unfamiliar file, propose a fix, or explain a stack trace.
- Use `gh copilot suggest` and friends.

## What you may not do

- Open a PR you did not read.
- Open a PR you do not understand.
- Open a PR whose tests you did not run.
- Open a PR with code you cannot defend on review when a human asks "why this and not that".
- Open a PR whose changelog says "AI-generated cleanup" with no specific user-visible justification.
- Pipe maintainer review feedback into a model and round-trip the response back into the PR without reading it.

## Maintainer use

The maintainer of this project (Pablo Diloreto, [@soydiloreto](https://github.com/soydiloreto)) actively uses Claude Code in development. The same rules apply. The maintainer is responsible for every commit on `main`, regardless of how it was authored.

When the maintainer commits AI-assisted work, the commit messages explain *what* and *why*, not *who-helped*. There is a `Co-Authored-By: Claude ...` trailer in some commits where the assist was substantive enough to credit; that's a record of authorship, not an excuse for any defect in the commit.

## When something goes wrong

If a model-generated change causes a regression, a security incident, or a wp.org review failure:

1. Roll forward with a fix. Don't blame the tool — write the fix.
2. Open an issue describing what went wrong and why the existing checks didn't catch it.
3. If the gap is in our quality gates, strengthen the gate. The fix for "the model produced bad code" is a stricter rule, not a ban on the model.

## Why this policy exists

This plugin runs on multisite networks — the blast radius of a sync bug is "user-data integrity across N sites simultaneously". A model that confidently invents a `wpmu_propagate_role()` function, or skips a capability check because its training data was light on multisite security idioms, can produce in one wrong line a vulnerability that ships to all of them. This policy exists because we assume contributors *will* use AI tools and want the same quality bar regardless.
