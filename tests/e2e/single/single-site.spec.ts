import { test, expect, Page } from '@playwright/test';
import { wp } from '../support/cli';
import { ADMIN_PASS, ADMIN_USER, SINGLE_ENV } from '../support/env';

/**
 * On a WordPress that is not a network the plugin has nothing to do. Activated
 * there, it says it needs WordPress Multisite, deactivates itself, and leaves
 * nothing behind: no menu, no trigger, no stored option.
 *
 * Runs against a plain single site with the BUILT plugin mounted (the tests
 * site of the Plugin Check environment, `make test-e2e-single`), so what is
 * checked is also what ships.
 */

const SLUG = 'wpm-user-sync';

const cli = (args: string[]): string => wp(args, { cwd: SINGLE_ENV, container: 'tests-cli' });

async function signIn(page: Page): Promise<void> {
	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill(ADMIN_USER);
	await page.locator('#user_pass').fill(ADMIN_PASS);
	await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);
}

test.beforeAll(() => {
	expect(cli(['eval', 'echo is_multisite() ? "network" : "single";'])).toBe('single');
	// Wherever an earlier run left it: inactive, so the test activates it the way a person does.
	cli(['plugin', 'deactivate', SLUG]);
	cli(['option', 'delete', 'wpmus_newSiteSync', 'wpmus_newUserSync', 'wpmus_setUserRoleSync']);
});

test('activating it explains that it needs a network, and deactivates it', async ({ page }) => {
	await signIn(page);
	await page.goto('/wp-admin/plugins.php');

	const row = page.locator(`tr[data-slug="${SLUG}"]`);
	await expect(row).toHaveClass(/inactive/);
	await Promise.all([page.waitForLoadState('load'), row.locator('.activate a').click()]);

	// WordPress activates it, and the first admin request after that is the
	// plugin's requirements check: a page that says why, and a way back.
	await expect(page.locator('.wp-die-message')).toContainText('requires WordPress Multisite');
	await expect(page.locator('a[href*="plugins.php"]')).toBeVisible();

	await page.goto('/wp-admin/plugins.php');
	await expect(page.locator(`tr[data-slug="${SLUG}"]`)).toHaveClass(/inactive/);
	expect(cli(['plugin', 'status', SLUG])).toContain('Inactive');
});

test('while it was active it hooked nothing: no menu, no option, and a new user is just a new user', async ({ page }) => {
	// Active, as the moment right after activation: the plugin is loaded on
	// every request until the requirements check turns it off.
	cli(['plugin', 'activate', SLUG]);

	const hooked = cli([
		'eval',
		`$found = array();
		foreach ( array( 'user_register', 'wpmu_new_user', 'wp_initialize_site', 'set_user_role', 'admin_menu', 'network_admin_menu', 'wpmus_process_sync_queue' ) as $hook ) {
			if ( empty( $GLOBALS['wp_filter'][ $hook ] ) ) { continue; }
			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) && 0 === strpos( get_class( $callback['function'][0] ), 'WPMUS' ) ) { $found[] = $hook; }
				}
			}
		}
		echo $found ? implode( ',', $found ) : 'nothing';`,
	]);
	expect(hooked, 'no menu, no trigger and no queue hooked').toBe('nothing');

	const login = `single-${Date.now().toString(36)}`;
	cli(['user', 'create', login, `${login}@example.com`, '--role=subscriber']);
	expect(cli(['option', 'list', '--search=wpmus*', '--format=count'])).toBe('0');
	cli(['user', 'delete', login, '--yes']);

	await signIn(page);
	await expect(page.locator('#toplevel_page_wpmus-sitehome')).toHaveCount(0);
	await expect(page.locator('.wp-die-message')).toContainText('requires WordPress Multisite');
	expect(cli(['plugin', 'status', SLUG])).toContain('Inactive');
});
