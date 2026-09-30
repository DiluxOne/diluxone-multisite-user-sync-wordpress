import { test, expect } from '@playwright/test';
import { php, wp } from '../support/cli';
import { PLUGIN_DIR } from '../support/env';
import { clearKnobs, clearQueue, createSite, createUser, cronScheduled, memberships, queue, removedFrom, setKnobs, setToggles, toggles } from '../support/network';
import { networkScreen, submit, syncForm } from '../support/ui';

/**
 * Uninstalling removes what the plugin stored and nothing else.
 *
 * The plugin is uninstalled the way WordPress does it (deactivated on the
 * network, then its uninstall.php run by `wp plugin uninstall`), but with
 * --skip-delete: on the dev site the plugin's folder is this repository, and
 * deleting it from the Plugins screen would delete the checkout. Everything
 * the Delete button does besides removing the files is what runs here.
 */

test('uninstalling removes the toggles, the queue, its cron event and the removals, and keeps every membership', async ({ page }) => {
	clearQueue();
	const site = createSite('uninstall');
	const member = createUser('uninstall-member', { [site.id]: 'author' });
	const removed = createUser('uninstall-removed', { [site.id]: 'subscriber' });
	php(`remove_user_from_blog( ${removed.id}, ${site.id} ); return true;`);

	// Something of each kind the plugin stores: the toggles on, a sync queued.
	setToggles({ newSite: true, newUser: true, role: true });
	setKnobs({ inline_limit: 0, hold_cron: true });
	await page.goto(networkScreen('wpmus-networksyncactions'));
	const form = syncForm(page, 'wpmusSyncNetworkSiteFromScratch');
	await form.locator(`input[name="listSites[]"][value="${site.id}"]`).check();
	await submit(page, form);
	expect(queue()).toHaveLength(1);
	expect(cronScheduled()).toBe(true);
	expect(removedFrom(removed.id)).toEqual([site.id]);

	try {
		wp(['plugin', 'deactivate', PLUGIN_DIR, '--network']);
		wp(['plugin', 'uninstall', PLUGIN_DIR, '--skip-delete']);

		expect(
			php(`return array_map( function ( $key ) { return get_site_option( $key, null ); }, array( 'wpmus_newSiteSync', 'wpmus_newUserSync', 'wpmus_setUserRoleSync', 'wpmus_sync_jobs', 'wpmus_sync_lock' ) );`)
		).toEqual([null, null, null, null, null]);
		expect(cronScheduled(), 'the cron event is gone').toBe(false);
		expect(removedFrom(removed.id), 'the removal record is gone').toEqual([]);
		expect(memberships(member.id)[site.id], 'memberships stay').toBe('author');
	} finally {
		wp(['plugin', 'activate', PLUGIN_DIR, '--network']);
		clearKnobs();
	}

	// Back on, it starts from nothing: every toggle off, no queue.
	await page.goto(networkScreen('wpmus-networksyncoptions'));
	for (const name of ['wpmus_newSiteSync', 'wpmus_newUserSync', 'wpmus_setUserRoleSync']) {
		await expect(page.locator(`input[name="${name}"]`)).not.toBeChecked();
	}
	expect(toggles()).toEqual({ newSite: false, newUser: false, role: false });
	await page.goto(networkScreen('wpmus-networksyncactions'));
	await expect(page.locator('#wpbody-content table.widefat')).toHaveCount(0);
});
