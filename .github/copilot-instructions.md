# Copilot custom instructions — WPM User Sync

This file is the project-wide context for GitHub Copilot (Code Review,
Chat, Coding Agent, and any other surface that reads
`.github/copilot-instructions.md`). It encodes the conventions and
review priorities specific to this codebase.

When you review a pull request, follow these rules. When in doubt,
prefer the project's existing patterns over textbook WordPress
patterns.

---

## What this repo is

A **WordPress Multisite-only** plugin (`Network: true` in the plugin
header). It synchronises users across the sites of a multisite
network: when a user is created at network level, when a user logs
in for the first time, or when a user's role changes on one site,
the plugin propagates the change to the other configured sites.

The plugin runs **only** on multisite installs — it auto-deactivates
on a single-site install via `wpmus_check_requirements()`. That
deactivation behaviour is part of the contract; if a PR loosens it,
flag it.

GPL-2.0-or-later. The maintainer is Pablo Diloreto
([@soydiloreto](https://github.com/soydiloreto)).

---

## Architecture quick-reference

PR 1 ships the dev-experience stack on top of the legacy procedural
code. The OOP refactor lands in **PR 2** (planned `WPMUS\` PSR-4
namespace, classes for Config / SyncEngine / NetworkAdmin /
SiteAdmin / Repositories). Until that lands, the architecture is:

| Path | Contains |
|------|----------|
| `wpm-user-sync.php` | Plugin header + bootstrap. Registers all the WordPress hooks via global function names. |
| `core/wpmus-variables.php` | Reads site options and exposes them as globals (`$wpmus_newSiteSync`, `$wpmus_newUserSync`, `$wpmus_setUserRoleSync`). Reads plugin metadata via `get_plugin_data()`. |
| `core/wpmus-functions.php` | Core sync logic: `wpmus_sync_newsite`, `wpmus_sync_newuser`, `wpmus_maybesync_newuser`, `wpmus_sync_newrole`, requirements check, init hooks, CSS enqueue. |
| `network-admin/*.php` | Network-level admin pages: home, sections, sync options form, sync actions (run sync from scratch). Uses tabs hooked via `do_action('wpmus_network_home_tabs')`. |
| `site-admin/*.php` | Per-site admin pages: home, sections, sync actions. |
| `css/wpmus_styles.css` | Admin styles enqueued in `wpmus_add_css()`. |
| `uninstall.php` | Removes the three site options on plugin uninstall. Users and roles are NOT touched. |
| `readme.txt` | wp.org plugin page content. |

Hook registration in `wpm-user-sync.php` is conditional on the three
site options:

- `wpmus_newSiteSync == 'yes'` → hooks `wpmu_new_blog → wpmus_sync_newsite`
- `wpmus_newUserSync == 'yes'` → hooks `wpmu_new_user`, `wp_login`, `social_connect_login`
- `wpmus_setUserRoleSync == 'yes'` → hooks `set_user_role`

Plus `network_admin_edit_*` and `admin_action_*` actions for the form
submissions.

---

## Hard rules to enforce

Flag a PR if any of these are violated.

### Multisite

- **Network:true must stay.** The plugin header declares this; never
  remove it. Multisite-only is a deliberate scope choice.
- **Use `get_site_option()` / `update_site_option()` / `delete_site_option()`,
  NOT `get_option()`.** Network-level configuration lives in the
  network options table, not in any individual site's options. Site-
  level user changes are propagated via the sync functions, not stored
  per site.
- **Use `get_sites()` to iterate sites,** not the deprecated
  `wp_get_sites()` (which has been removed in modern WP). The current
  code uses `get_sites()`; keep it that way.
- **Be explicit about the current site.** When sync code switches blog
  context to perform user role changes on another site, it must
  `switch_to_blog($id)` and pair with `restore_current_blog()`. Failing
  to restore leaks state into the rest of the request.
- **`wpmu_new_blog` is the legacy hook; modern WP also fires `wp_initialize_site`.**
  The current code uses `wpmu_new_blog` for back-compat. If a PR adds
  `wp_initialize_site` handling, that's fine — both should coexist
  during the transition. Don't remove the legacy hook without
  documentation.

### Security

- **Capability checks on every admin handler.** Network-level handlers
  must check `current_user_can('manage_network')` (or
  `manage_network_options`). Site-level handlers must check
  `current_user_can('manage_options')`. A handler that mutates state
  without a capability check is a critical bug.
- **Nonces on every form submission.** The code uses
  `wp_nonce_field()` / `check_admin_referer()` patterns. New forms
  MUST follow the same pattern. Missing or invalidly-scoped nonces
  are a CSRF vulnerability.
- **`$wpdb->prepare()` for every dynamic SQL.** No string concatenation
  in queries. The existing `get_sites()` returns a structured array;
  if any new code crafts SQL by hand, it must use `prepare()`.
- **Escape all output.** Every echo of dynamic value must go through
  `esc_html`, `esc_attr`, `esc_url`, or `wp_kses` with an explicit
  allowed-tags list. Static literal HTML is fine; nothing dynamic
  may be unescaped.
- **Every PHP file under `core/`, `network-admin/`, `site-admin/`,
  and the root must start with `if ( ! defined( 'ABSPATH' ) ) { exit; }`**
  to prevent direct file access. `index.php` is the silence-is-golden
  decoy and `uninstall.php` uses the `WP_UNINSTALL_PLUGIN` guard
  instead — those two are exceptions.

### WordPress conventions

- **Hooks-only side effects.** Output and mutation happen through
  registered WordPress hooks. No code that mutates global state at
  file scope.
- **Internationalisation.** Text domain is `wpm-user-sync`. Every
  user-visible string passes through `__()`, `_e()`, `esc_html__()`,
  or `esc_attr__()`. `load_plugin_textdomain()` is called on
  `plugins_loaded` — don't move it to a different hook.
- **PHP 7.4 minimum.** The `Requires PHP:` header says 7.4 and the CI
  matrix runs 7.4–8.3. PHPCompatibilityWP enforces this. Don't use
  PHP 8.0+ syntax (named arguments, match expressions, constructor
  promotion, nullsafe `?->`, enums, readonly, intersection types,
  first-class callable syntax) without a PHP 7.4 fallback.
- **Deterministic prefix.** Every global function and every defined
  global variable starts with `wpmus_` (legacy) or `WPMUS\` (new
  OOP code from PR 2 onward). PHPCS enforces this via
  `WordPress.NamingConventions.PrefixAllGlobals`.

### Style

- **Modern conditions.** No Yoda. `$x === 5`, not `5 === $x`. The
  PHPCS config has the Yoda sniff explicitly disabled.
- **`__DIR__` over `dirname(__FILE__)`** in any new code. The legacy
  bootstrap uses `dirname(__FILE__)`; the linter-cleanup PR will
  modernise it. New PRs should not add fresh `dirname(__FILE__)`
  calls.
- **`strict_types=1` in any new file added by the OOP refactor.**
  Legacy procedural files are exempt only because adding it now
  would surface dozens of pre-existing type-coercion bugs at once;
  they will be migrated in the cleanup PR.

### Translator comments

- If a PR adds a `sprintf` with a placeholder inside a translation
  function, the `/* translators: %s: ... */` comment MUST be on the
  line **immediately preceding** the translation call. A blank line
  in between makes the comment invisible to gettext. The
  `i18n-validate.yml` workflow runs `wp i18n make-pot` and fails
  the build on any `Warning:` it prints.

---

## Style rules NOT to comment on

Don't waste review comments on these — they are deliberate project
choices:

- **No Yoda conditions.** PHPCS rule already disabled.
- **The legacy procedural style is being refactored.** PRs targeted
  at the OOP refactor (PR 2 in the project plan) will introduce
  classes, autoloading, and DI. Don't comment on "this should be
  a class" against legacy code that hasn't been migrated yet — it
  is on the explicit plan.
- **`global $wpmus_newSiteSync;` etc.** in legacy files. These are
  the legacy state pattern; they will go away in the OOP refactor.
  Flagging them on legacy files is noise.
- **No PHP 7.0–7.3 backward compatibility.** This plugin's minimum
  is 7.4 and that is documented in the `Requires PHP:` header. Don't
  ask for `7.0` compat; this is not hola-simpsons.

---

## What to actively look for in PRs

In priority order:

1. **Missing capability checks** on any new admin/AJAX handler that
   mutates state. Multisite handlers without `manage_network` or
   `manage_options` checks are critical.
2. **Missing nonces** on any new form. CSRF-equivalent.
3. **Missing escaping** on any new output point. Legacy code has
   pre-existing gaps that will be caught in the cleanup PR; new
   code must be clean.
4. **Missing `$wpdb->prepare()`** on any new dynamic SQL.
5. **`switch_to_blog()` without paired `restore_current_blog()`** —
   subtle state leak; very high priority.
6. **PHP 8.0+ syntax** — automatic-reject, breaks the 7.4 contract.
7. **Translator comments** placed on the wrong line (separated by a
   blank line from the translation call).
8. **Missing `Network: true`** if a PR touches the plugin header.

---

## Versioning and the `-dev` suffix

The PHP `Version:` header in `wpm-user-sync.php` carries a `-dev`
suffix on `main` between releases (e.g. `1.5.0-dev`). The
`Stable tag:` in `readme.txt` does not — it always holds the last
published release. The CI version-alignment rule strips the suffix
when comparing, and the deploy workflow enforces strict equality at
tag time. See [`docs/release.md`](../docs/release.md).

Tags are bare SemVer (e.g. `1.5.0`), no `v` prefix, no pre-release
suffixes. The deploy workflow rejects any other format.

---

## Out of scope

- **No new top-level features without an issue.** The plugin's scope
  is "synchronise users across multisite sites". Significant scope
  expansion needs a discussion first.
- **No production dependencies.** The published plugin has zero
  Composer/npm dependencies and that's a hard line. Dev tooling in
  `composer.json` / `package.json` is fine — those never ship to
  wp.org (excluded via `.distignore`).
- **Don't make this not-multisite.** The plugin is multisite-only
  by design. Single-site forks are out of scope.
