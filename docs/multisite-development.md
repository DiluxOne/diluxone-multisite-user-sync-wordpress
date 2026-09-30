# Multisite development notes

Specifics for working on `wpm-user-sync` — a plugin that, by design, only runs on WordPress Multisite. Read this before you start poking around.

## The local stack is a multisite

[`.wp-env.json`](../.wp-env.json) declares `"multisite": true`, so when you run `make env` you get a **subdirectory multisite** install at `http://localhost:8888`:

| URL | Purpose |
| --- | --- |
| `http://localhost:8888/` | Primary site (blog ID 1). |
| `http://localhost:8888/wp-admin/network/` | Network admin (where this plugin's main UI lives). |
| `http://localhost:8888/wp-admin/` | Per-site admin for blog ID 1 (where this plugin's site-level UI lives). |
| `http://localhost:8888/<slug>/` | Each additional site you create (`wp site create --slug=second --title="Second"`). |

The multisite is **subdirectory** mode (not subdomain). Subdomain-mode network testing requires DNS rewriting and isn't supported by the default `wp-env` boot. If you need subdomain-mode coverage, fall back to the manual install approach in [`development.md`](development.md).

## Create test sites

Use WP-CLI through `wp-env` to spin up sites quickly:

```bash
npx wp-env run cli wp site create --slug=alpha --title="Alpha" --email=admin@example.com
npx wp-env run cli wp site create --slug=beta  --title="Beta"  --email=admin@example.com
npx wp-env run cli wp site list
```

`wp site list` gives you the blog IDs you'll then reference in the plugin's network-admin UI when you configure which sites should be sync targets.

## Plugin activation in multisite

The plugin is **`Network: true`** in its header, which means it can be either:

| Activation | Effect |
| --- | --- |
| **Network-activated** (Network Admin → Plugins → Network Activate) | Active on every site. The network-admin menu appears. This is the intended state for production use. |
| **Per-site activated** | Allowed but not recommended — sync triggers may not fire as expected because the network-level options the plugin reads still come from the network options table. |

For dev, always network-activate.

## Triggering the three sync events manually

The plugin only acts when one of three events fires *and* the matching network option is `'yes'`. Test each event in isolation:

### `wpmus_newSiteSync`: when a new site is created

```bash
# Toggle the option (network admin UI does the same)
npx wp-env run cli wp site option update wpmus_newSiteSync yes
# Now create a site — wp_initialize_site fires and the plugin adds every
# network user to it with the new site's default role.
npx wp-env run cli wp site create --slug=trigger-test --title="Trigger Test" --email=admin@example.com
```

### `wpmus_newUserSync`: when a user is created

```bash
npx wp-env run cli wp site option update wpmus_newUserSync yes
# Adding a user fires user_register (and wpmu_new_user when it goes
# through wpmu_create_user); the plugin runs the sync once per user.
npx wp-env run cli wp user create newperson newperson@example.com --role=subscriber
```

Signing in does nothing: the old sign-in catch-up, which put people back on sites they had been removed from, is gone.

### `wpmus_setUserRoleSync`: when a role changes

```bash
npx wp-env run cli wp site option update wpmus_setUserRoleSync yes
# Change a user's role on one site — set_user_role fires
npx wp-env run cli wp user set-role newperson editor --url=http://localhost:8888/alpha/
```

After each of these, check the other sites to confirm propagation. The role is copied only to sites the user already belongs to and that define it; `administrator` is never copied unless the `wpmus_replicate_role` filter allows it.

### Removals, exclusions and big syncs

- Removing a user from a site (the site's Users screen, `remove_user_from_blog()`) is recorded in the user meta `wpmus_removed_from_blogs`; no automatic sync, and no manual one unless forced, adds them back. Adding them back yourself clears the record.
- Super admins, archived/spam/deleted sites and other networks' sites are never synced. `wpmus_excluded_site_ids` and `wpmus_should_sync_user` exclude more.
- A sync of more than 500 user-site pairs (`wpmus_sync_inline_limit`) is queued in the network option `wpmus_sync_jobs` and processed by the `wpmus_process_sync_queue` cron event on the main site. To drive it by hand: `npx wp-env run cli wp cron event run wpmus_process_sync_queue`.

## Resetting state between test runs

The plugin stores its three toggles as **site options** (network-level), not regular options:

```bash
npx wp-env run cli wp site option delete wpmus_newSiteSync
npx wp-env run cli wp site option delete wpmus_newUserSync
npx wp-env run cli wp site option delete wpmus_setUserRoleSync
```

For users, deleting them at the network level removes them from every site:

```bash
npx wp-env run cli wp user delete newperson --network --yes
```

The plugin's `uninstall.php` deletes its network options (the three toggles, the sync queue and its lock), the queue's cron event and the `wpmus_removed_from_blogs` user meta when the plugin is fully removed (Plugins → Delete on the Network Admin → Plugins screen). It deliberately does **not** touch users, roles or memberships — uninstalling the plugin should never have user-management side effects.

## Switching blog context safely

Cross-site sync requires switching context with `switch_to_blog( $blog_id )`. **Every** call must be paired with `restore_current_blog()`, including on early returns and exceptions. Look for this pattern in the existing code:

```php
switch_to_blog( $target_blog_id );
try {
    // sync work
} finally {
    restore_current_blog();
}
```

If you write new code that switches context, follow the `try/finally` pattern (or the manual equivalent — restore on every code path).

## Things multisite specifically breaks

Common gotchas to watch for when developing or reviewing PRs:

1. **`get_option()` vs `get_site_option()`** — the plugin's three toggles are network-level. `get_option()` reads the *current site's* options table, which means you'd get the toggle from blog 1 only. Always use `get_site_option()` for network-level config.
2. **`get_current_blog_id()`** changes inside `switch_to_blog`. Code that decides "what's the current blog" before switching, then uses that decision after switching, is buggy. Capture before, use after restore.
3. **The "current network" is not always 1.** Use `get_current_network_id()` for any cross-network operation. The plugin syncs only the sites of the current network.
4. **`is_multisite()` returns `false` on activation if the plugin is activated before multisite is configured.** That's the failure case `wpmus_check_requirements()` exists to handle. Don't gate the plugin's bootstrap on `is_multisite()` alone — the requirements check at `admin_init` is what enforces the contract.
5. **`wpmu_new_blog` is deprecated since WordPress 5.1; the plugin uses `wp_initialize_site`** at priority 11, after core populates the site at 10. Listening on both would run the sync twice.
6. **WP-Cron events are stored per site.** The sync queue's event is scheduled on the main site; code that schedules it must switch there first.

## Tests

The unit-test suite is intentionally light — the plugin is heavily WP-coupled, so most behaviour is exercised via the integration suite that boots a real (mini) WP runtime. See [`testing-and-quality.md`](testing-and-quality.md) for the layered test strategy and how to run each layer.
