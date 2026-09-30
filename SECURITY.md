# Security Policy

## Reporting a vulnerability

**Please do not open a public GitHub issue or wordpress.org forum thread to report a security vulnerability.** This plugin decides who is a member of which site, with which role, across whole networks: a public report before a fix puts every one of them at risk.

Report it privately instead, from this repository's **Security** tab › **Report a vulnerability** (GitHub's private vulnerability reporting). That opens a confidential advisory where you and the maintainers discuss the issue, coordinate a fix and agree on the disclosure. Nothing is public until both sides decide it is ready.

## What to include

- **What the vulnerability is** and its impact (who gains which role where, which data leaks).
- **Steps to reproduce**: the network type (subdirectory, subdomain), the toggles on, the smallest case.
- **Affected versions** of the plugin, WordPress and PHP.
- **Any proof of concept** you used.
- **Your proposed severity** and why, and a mitigation if you have one.

## What to expect

| Stage | Target time |
| --- | --- |
| Acknowledgement | within 72 hours |
| Triage and severity | within 7 days |
| Fix or mitigation | depends on severity, communicated after triage |
| Public disclosure (advisory and release) | coordinated with the reporter |

Reporters are credited in the published advisory unless they prefer otherwise.

## Supported versions

Security fixes are released against the **latest version published on wordpress.org**. Older versions are not patched separately: please update before reporting.

## Out of scope

- Issues that need physical access to the server, or an already-compromised super admin account.
- Something a super admin can already do by design (a super admin manages every membership of the network).
- Best-practice deviations without a concrete attack path.

Thanks for helping keep DiluxOne Multisite User Sync and the networks that use it safe.
