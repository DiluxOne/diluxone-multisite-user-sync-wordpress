import { test, expect, Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { php } from '../support/cli';
import { DEV_URL } from '../support/env';
import { clearKnobs, clearQueue, setKnobs } from '../support/network';
import { networkScreen, siteScreen, submit, syncForm } from '../support/ui';

/**
 * The pictures the wordpress.org listing shows, taken from the real screens.
 *
 * Not a comparison: it writes .wordpress-org/screenshot-N.png, and the caption
 * under `== Screenshots ==` in readme.txt with the same number is what each
 * one answers to. `make screenshots` runs it; read the diff before committing,
 * because that diff is the shop window.
 *
 * The network is dressed for the picture — three sites with real names and a
 * few people — and undressed after: everything made here is deleted, and the
 * language is put back.
 */

const SITES = ['blog', 'shop', 'help'];
const PEOPLE = ['ana', 'bruno', 'carla', 'diego', 'elena'];

let made: { sites: number[]; users: number[]; language: string; name: string };

test.beforeAll(() => {
	made = php<{ sites: number[]; users: number[]; language: string; name: string }>(`
		$made = array( 'sites' => array(), 'users' => array(), 'language' => (string) get_site_option( 'WPLANG', '' ), 'name' => (string) get_site_option( 'site_name', '' ) );
		update_site_option( 'WPLANG', '' );
		update_site_option( 'site_name', 'Example Network' );
		$network = get_network();
		foreach ( ${JSON.stringify(SITES).replace(/"/g, "'").replace('[', 'array(').replace(']', ')')} as $slug ) {
			if ( ! domain_exists( $network->domain, '/' . $slug . '/' ) ) {
				$made['sites'][] = (int) wpmu_create_blog( $network->domain, '/' . $slug . '/', ucfirst( $slug ), 1 );
			}
		}
		foreach ( ${JSON.stringify(PEOPLE).replace(/"/g, "'").replace('[', 'array(').replace(']', ')')} as $login ) {
			if ( ! username_exists( $login ) ) {
				$made['users'][] = (int) wpmu_create_user( $login, wp_generate_password(), $login . '@example.com' );
			}
		}
		return $made;
	`);
});

test.afterAll(() => {
	clearKnobs();
	php(`
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		foreach ( ${JSON.stringify(made.sites)} as $id ) { wpmu_delete_blog( $id, true ); }
		foreach ( ${JSON.stringify(made.users)} as $id ) { wpmu_delete_user( $id ); }
		delete_site_option( 'wpmus_sync_jobs' );
		switch_to_blog( get_main_site_id() );
		wp_clear_scheduled_hook( 'wpmus_process_sync_queue' );
		restore_current_blog();
		update_site_option( 'WPLANG', base64_decode( '${Buffer.from(made.language).toString('base64')}' ) );
		update_site_option( 'site_name', base64_decode( '${Buffer.from(made.name).toString('base64')}' ) );
		return true;
	`);
});

async function shoot(page: Page, number: number): Promise<void> {
	mkdirSync('.wordpress-org', { recursive: true });
	await page.evaluate(() => document.fonts.ready.then(() => undefined));
	await page.mouse.move(0, 0);
	await page.screenshot({ path: `.wordpress-org/screenshot-${number}.png`, clip: { x: 0, y: 0, width: 1280, height: 800 } });
}

test('1. Network Admin › User Sync: the home and the first steps', async ({ page }) => {
	await page.goto(networkScreen('wpmus-networkhome'));
	await shoot(page, 1);
});

test('2. Network Sync Options: the three automatic triggers', async ({ page }) => {
	await page.goto(networkScreen('wpmus-networksyncoptions'));
	await page.locator('input[name="wpmus_newSiteSync"]').check();
	await page.locator('input[name="wpmus_newUserSync"]').check();
	await shoot(page, 2);
});

test('3. Network Sync Actions: every site, or the sites you pick', async ({ page }) => {
	await page.goto(networkScreen('wpmus-networksyncactions'));
	await shoot(page, 3);
});

test('4. A large sync running in the background, with its progress', async ({ page }) => {
	clearQueue();
	setKnobs({ inline_limit: 1, hold_cron: true });
	await page.goto(networkScreen('wpmus-networksyncactions'));
	await submit(page, syncForm(page, 'wpmusSyncNetworkFromScratch'));
	await expect(page.locator('#wpbody-content table.widefat')).toBeVisible();
	await shoot(page, 4);
});

test('5. A site\'s dashboard, for super admins: the site\'s own home', async ({ page }) => {
	await page.goto(siteScreen('wpmus-sitehome', '', `${DEV_URL}/blog/`));
	await shoot(page, 5);
});

test('6. Site Sync Actions: every network user into this one site', async ({ page }) => {
	await page.goto(siteScreen('wpmus-sitesyncactions', '', `${DEV_URL}/blog/`));
	await shoot(page, 6);
});
