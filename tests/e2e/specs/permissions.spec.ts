import { test, expect } from '@playwright/test';
import { DEV_URL } from '../support/env';
import { createSite, createUser, memberships } from '../support/network';
import { networkScreen, notice, signedInAs, siteScreen, submit } from '../support/ui';

/**
 * Who sees what. Pulling every account of the network into a site is the
 * network's decision, so the site screens belong to super admins (the
 * `manage_network_users` capability); a site's own administrator does not see
 * the plugin at all and cannot run its action.
 */

test.describe('A site administrator who is not a super admin', () => {
	test('has no plugin menu, and the site screens refuse them', async ({ browser }) => {
		const site = createSite('perm-site');
		const siteAdmin = createUser('perm-admin', { [site.id]: 'administrator' });
		const { context, page } = await signedInAs(browser, siteAdmin, site.url);

		await page.goto(site.admin);
		await expect(page.locator('#adminmenu')).toBeVisible();
		await expect(page.locator('#toplevel_page_wpmus-sitehome')).toHaveCount(0);

		for (const screen of ['wpmus-sitehome', 'wpmus-sitesyncactions']) {
			const response = await page.goto(siteScreen(screen, '', site.url));
			expect(response?.status(), `${screen} is not theirs`).toBe(403);
		}

		await context.close();
	});

	test('cannot reach Network Admin\'s screens', async ({ browser }) => {
		const site = createSite('perm-net');
		const siteAdmin = createUser('perm-net-admin', { [site.id]: 'administrator' });
		const { context, page } = await signedInAs(browser, siteAdmin, site.url);

		for (const screen of ['wpmus-networkhome', 'wpmus-networksyncoptions', 'wpmus-networksyncactions']) {
			await page.goto(networkScreen(screen));
			await expect(page.locator('#toplevel_page_wpmus-networkhome'), `${screen} is not theirs`).toHaveCount(0);
			await expect(page.locator('input[name="wpmus_newSiteSync"], form[action*="wpmusSync"]')).toHaveCount(0);
		}

		await context.close();
	});

	test('cannot run the site sync, even posting to it directly', async ({ browser }) => {
		const site = createSite('perm-post');
		const siteAdmin = createUser('perm-post-admin', { [site.id]: 'administrator' });
		const outsider = createUser('perm-outsider');
		const { context, page } = await signedInAs(browser, siteAdmin, site.url);

		const response = await page.request.post(`${site.admin}admin.php?action=wpmusSyncSiteSiteFromScratch`, {
			form: { wpmus_blogid: String(site.id) },
		});

		expect(response.status()).toBe(403);
		expect(memberships(outsider.id)[site.id], 'nobody was pulled into the site').toBeUndefined();

		await context.close();
	});
});

test.describe('A super admin on a site\'s dashboard', () => {
	test('sees the site menu and its home\'s three tabs', async ({ page }) => {
		const site = createSite('perm-home');

		await page.goto(siteScreen('wpmus-sitehome', '', site.url));
		const menu = page.locator('#toplevel_page_wpmus-sitehome');
		await expect(menu).toBeVisible();
		await expect(menu.locator('a[href*="page=wpmus-sitesyncactions"]')).toHaveCount(1);

		for (const tab of ['welcome', 'concepts', 'about']) {
			await page.goto(siteScreen('wpmus-sitehome', tab, site.url));
			await expect(page.locator(`.nav-tab-active[href*="tab=${tab}"]`)).toBeVisible();
		}
	});

	test('"Sync from scratch" fills that site with its default role, and no other', async ({ page }) => {
		const site = createSite('perm-sync', { defaultRole: 'author' });
		const other = createSite('perm-other');
		const person = createUser('perm-person');

		await page.goto(siteScreen('wpmus-sitesyncactions', '', site.url));
		await submit(page, page.locator('form[action*="wpmusSyncSiteSiteFromScratch"]'));

		await expect(notice(page)).toHaveClass(/updated/);
		expect(page.url()).toContain(`${DEV_URL}/${site.slug}/wp-admin/`);
		const mine = memberships(person.id);
		expect(mine[site.id]).toBe('author');
		expect(mine[other.id]).toBeUndefined();
	});
});
