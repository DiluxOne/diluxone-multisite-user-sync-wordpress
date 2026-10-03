import { Browser, BrowserContext, Page, expect } from '@playwright/test';
import { DEV_URL } from './env';
import type { User } from './network';

/**
 * The plugin's screens, by the page slug the menu registers, and what a test
 * does on them. Tests name screens by slug and read the markup, never a
 * translated label: the dev network may be in any of the plugin's locales.
 */

/** A screen of Network Admin. */
export function networkScreen(page: string, tab = ''): string {
	return `${DEV_URL}/wp-admin/network/admin.php?page=${page}${tab ? `&tab=${tab}` : ''}`;
}

/** A screen of one site's dashboard (the main site's when `siteUrl` is left out). */
export function siteScreen(page: string, tab = '', siteUrl = `${DEV_URL}/`): string {
	return `${siteUrl}wp-admin/admin.php?page=${page}${tab ? `&tab=${tab}` : ''}`;
}

/** The notice the plugin prints after one of its own redirects. */
export function notice(page: Page) {
	return page.locator('#message.notice');
}

/** The form of a network sync action, by the admin-post action it posts to. */
export function syncForm(page: Page, action: 'wpmusSyncNetworkFromScratch' | 'wpmusSyncNetworkSiteFromScratch') {
	return page.locator(`form[action*="action=${action}"]`);
}

/** Submits a form's own submit button and waits for the redirect back. */
export async function submit(page: Page, form: ReturnType<Page['locator']>): Promise<void> {
	await Promise.all([page.waitForLoadState('load'), form.locator('input[type="submit"], button[type="submit"]').first().click()]);
	await page.waitForLoadState('load');
}

/**
 * Signs in on the login screen `page` is on. The screen runs its own script
 * as it loads, and a field filled before it settles can end up empty, so the
 * form is posted only once both fields hold what was typed; a post the
 * browser refused (a field empty after all) is tried again.
 */
export async function signIn(page: Page, login: string, password: string, landing: RegExp = /wp-admin|\/$/): Promise<void> {
	await page.waitForLoadState('load');
	await expect(async () => {
		await page.locator('#user_login').fill(login);
		await page.locator('#user_pass').fill(password);
		await expect(page.locator('#user_login')).toHaveValue(login, { timeout: 1_000 });
		await expect(page.locator('#user_pass')).toHaveValue(password, { timeout: 1_000 });
		await Promise.all([page.waitForURL(landing, { timeout: 10_000 }), page.locator('#wp-submit').click()]);
	}).toPass({ timeout: 60_000 });
}

/**
 * A browser of another person, signed in with their own password: a site
 * administrator who is not a super admin, say. Its own context, so the super
 * admin session of the spec is left alone.
 */
export async function signedInAs(browser: Browser, user: User, siteUrl = `${DEV_URL}/`): Promise<{ context: BrowserContext; page: Page }> {
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
	const page = await context.newPage();

	await page.goto(`${siteUrl}wp-login.php`);
	await signIn(page, user.login, user.password);
	await expect(page.locator('#wpadminbar')).toBeVisible();

	return { context, page };
}
