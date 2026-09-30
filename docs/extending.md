# Extending

The public API of DiluxOne Multisite User Sync: the filters and actions a
site, a theme or another plugin can build on, what is stored and where, and
the names that stay for backwards compatibility. Everything here is stable:
it is renamed or removed only in a major version, announced in a minor before
it ([`release.md`](release.md#versions)).

The plugin knows nothing about other plugins. A registration, membership or
single-sign-on plugin that needs a say in who is added where does it through
these filters.

## Filters

### `wpmus_excluded_site_ids`

Sites the sync never writes to, for every trigger and action.

```php
apply_filters( 'wpmus_excluded_site_ids', array $excluded, string $context ): int[]
```

| Argument | |
| --- | --- |
| `$excluded` | Blog ids to leave alone. Default: empty. |
| `$context` | `new_site`, `new_user`, `manual` or `role_change`. |

```php
// Keep the private staff site out of every automatic sync, but let a super
// admin fill it on purpose with a manual one.
add_filter( 'wpmus_excluded_site_ids', function ( array $ids, string $context ): array {
	return 'manual' === $context ? $ids : array_merge( $ids, array( 7 ) );
}, 10, 2 );
```

Archived, spam and deleted sites and the sites of other networks are never
synced whatever this returns.

### `wpmus_should_sync_user`

Whether the sync adds one user to one site. Runs only for a membership the
sync is about to create: super admins, existing members and people removed
from the site are already out.

```php
apply_filters( 'wpmus_should_sync_user', bool $sync, int $user_id, int $blog_id, string $context ): bool
```

| Argument | |
| --- | --- |
| `$sync` | `true` to add the user. Default: `true`. |
| `$user_id`, `$blog_id` | The pair. |
| `$context` | `new_site`, `new_user` or `manual`. |

```php
// Only people with a verified e-mail join the other sites.
add_filter( 'wpmus_should_sync_user', function ( bool $sync, int $user_id ): bool {
	return $sync && (bool) get_user_meta( $user_id, 'email_verified', true );
}, 10, 2 );
```

### `wpmus_replicate_role`

Whether a role changed on one site is copied to the user's other sites (Set
User Role Automatic Sync). The role is copied only to sites that define it,
never for a super admin, and never when it did not change.

```php
apply_filters( 'wpmus_replicate_role', bool $replicate, string $role, int $user_id ): bool
```

| Argument | |
| --- | --- |
| `$replicate` | Default: `true` for every role except `administrator`. |
| `$role` | The new role. |
| `$user_id` | The user. |

```php
// Copy "shop_manager" too, but nothing that can manage the site.
add_filter( 'wpmus_replicate_role', function ( bool $replicate, string $role ): bool {
	return $replicate && ! in_array( $role, array( 'administrator', 'editor' ), true );
}, 10, 2 );
```

### `wpmus_sync_inline_limit`

The largest sync, in user-site pairs, that runs in the request that starts
it. A bigger one runs in the background through WP-Cron, with its progress on
Network Sync Actions.

```php
apply_filters( 'wpmus_sync_inline_limit', int $limit, string $context ): int
```

Default `500`; `$context` is `new_site`, `new_user` or `manual`. Lower it on a
host with a short request timeout; `0` sends every sync to the background.

### `wpmus_sync_batch_size`

How many user-site pairs one batch of a background sync processes. Default
`500`, at least `1`.

```php
apply_filters( 'wpmus_sync_batch_size', int $size ): int
```

### `wpmus_sync_time_limit`

How many seconds one WP-Cron run keeps taking batches. Default `20`; `0`
means one batch per run.

```php
apply_filters( 'wpmus_sync_time_limit', int $seconds ): int
```

## Actions

### `wpmus_network_home_tabs` / `wpmus_network_home_contents`

Fired on Network Admin › User Sync, after the built-in tabs (Welcome,
Concepts, About) and after the active tab's body. An add-on prints its own
`<a class="nav-tab">` in the first and its body in the second when its tab is
active.

```php
do_action( 'wpmus_network_home_tabs', string $active_tab );
do_action( 'wpmus_network_home_contents', string $active_tab );
```

### `wpmus_site_home_tabs` / `wpmus_site_home_contents`

The same on a site's dashboard › User Sync (super admins only).

```php
do_action( 'wpmus_site_home_tabs', string $active_tab );
do_action( 'wpmus_site_home_contents', string $active_tab );
```

The active tab is also in the global `$sd_active_tab`, as in 1.4. The built-in
tab slugs are `welcome`, `concepts` and `about`; an unknown `tab` falls back
to `welcome`, so an add-on's tab body renders under `welcome` today.

### `wpmus_process_sync_queue`

The WP-Cron event that works through the background queue, scheduled as a
single event on the main site while syncs are pending. Run it by hand with
`wp cron event run wpmus_process_sync_queue`. Do not schedule it yourself.

## What is stored

| Where | Key | What |
| --- | --- | --- |
| Network option | `wpmus_newSiteSync`, `wpmus_newUserSync`, `wpmus_setUserRoleSync` | `'yes'` or `''`: the three triggers. |
| Network option | `wpmus_sync_jobs` | The background syncs, with their cursor and progress. Deleted when empty. |
| Network option | `wpmus_sync_lock` | The time a cron run took the queue; older than ten minutes counts as abandoned. |
| User meta | `wpmus_removed_from_blogs` | The blog ids an administrator removed the user from. |
| Main site cron | `wpmus_process_sync_queue` | The next run of the queue. |

Uninstalling deletes all of it and nothing else.

## Kept for backwards compatibility

- **Functions** (deprecated since 1.5.0, each dispatching to the engine and
  calling `_deprecated_function()`): `wpmus_sync_newsite( $blog_id )`,
  `wpmus_sync_newuser( $user_id )`, `wpmus_sync_newrole( $user_id, $role )`,
  and `wpmus_maybesync_newuser( $user_login )`, a no-op since the sign-in
  catch-up sync was removed.
- **Screens and actions** keep their 1.4 slugs: `wpmus-networkhome`,
  `wpmus-networksyncoptions`, `wpmus-networksyncactions`, `wpmus-sitehome`,
  `wpmus-sitesyncactions`; `network_admin_edit_wpmusSaveGlobalConfig`,
  `…_wpmusSyncNetworkFromScratch`, `…_wpmusSyncNetworkSiteFromScratch`,
  `admin_action_wpmusSyncSiteSiteFromScratch`.
- **Stylesheet handle** `wpmus_styles` (for `wp_dequeue_style()`).
- **Constant** `WPMUS_VERSION`, the plugin's version.
