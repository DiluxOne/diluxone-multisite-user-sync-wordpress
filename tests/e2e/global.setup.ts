import { test as setup, expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { php } from './support/cli';
import { ADMIN_PASS, ADMIN_STATE, ADMIN_USER, STATE_FILE } from './support/env';
import { clearKnobs, sweep } from './support/network';
import { signIn } from './support/ui';

/**
 * Puts the network into the state every spec assumes, and remembers what it
 * was.
 *
 * The dev network is somebody's development site, not a scratch one: the
 * three toggles and any sync still queued are written down, the toggles are
 * turned off (each spec turns on what it tests), and the teardown puts it all
 * back. What an interrupted run left behind — users and sites named e2e-… —
 * is deleted first, so no spec counts somebody else's leftovers.
 */

setup('remember the network, turn the toggles off, sweep old runs', async () => {
	const previous = php<Record<string, unknown>>(`
		$keep = array();
		foreach ( array( 'wpmus_newSiteSync', 'wpmus_newUserSync', 'wpmus_setUserRoleSync', 'wpmus_sync_jobs' ) as $key ) {
			$keep[ $key ] = get_site_option( $key, null );
		}
		foreach ( array( 'wpmus_newSiteSync', 'wpmus_newUserSync', 'wpmus_setUserRoleSync' ) as $key ) {
			update_site_option( $key, '' );
		}
		delete_site_option( 'wpmus_sync_jobs' );
		delete_site_option( 'wpmus_sync_lock' );
		return $keep;
	`);

	mkdirSync('build', { recursive: true });
	writeFileSync(STATE_FILE, JSON.stringify(previous, null, 2));

	clearKnobs();
	sweep();

	const active = php<boolean>(`
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		foreach ( array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) as $file ) {
			if ( '/wpm-user-sync.php' === substr( $file, -18 ) ) { return true; }
		}
		return false;
	`);

	expect(active, 'the plugin must be network-activated on the dev network').toBe(true);
});

setup('keep a super admin session for the specs', async ({ page, context }) => {
	await page.goto('/wp-login.php');
	await signIn(page, ADMIN_USER, ADMIN_PASS, /wp-admin/);
	await context.storageState({ path: ADMIN_STATE });
});
