# Roadmap

What DiluxOne Multisite User Sync does, what is planned, what it will not do,
and its known limitations. The issue triage answers from this page.

## Free, and only free

Everything the plugin does is free, on wordpress.org, with no paid version or
add-on: the three automatic triggers, the manual actions, background syncs,
the removal record and the filters for developers.

## Which version does what

| Version | What |
| --- | --- |
| 1.5.0 (released) | Object-oriented rewrite, capability checks on every action, no role-sync cascades. |
| 2.0.0 (being written) | The DiluxOne name; PHP 8.0; the security fixes (new-site roles, administrator replication, site administrators); removals respected; super admins and inactive sites skipped; background syncs; filters; translations. See `readme.txt`. |

## Planned, not scheduled

- Working alongside DiluxOne Users+ in the same network: a hand-off so that
  only one of the two plugins manages network membership. It comes back when
  Users+ reads it.

## Will not do

- Copy or duplicate accounts between separate WordPress installs: the plugin
  works inside one network, where there is one list of accounts.
- Change a membership's role outside the role trigger's rules (the manual
  syncs and the new-user and new-site triggers never touch existing
  memberships).
- Add super admins as members, or write to archived, spam or deleted sites.

## Known limitations

- Background syncs run on WP-Cron, which runs when the main site gets a
  visit. On a network with no traffic, or with `DISABLE_WP_CRON` and no system
  cron, a queued sync waits; its progress on Network Sync Actions shows it.
- The role trigger copies one role (the new one); a user with several roles
  on the source site gets that one on the others.
- Accounts created by writing to the database directly (imports, SQL) fire no
  hook; the manual "Sync from scratch" adds them.
- The plugin needs WordPress Multisite; on a single site it deactivates
  itself.
