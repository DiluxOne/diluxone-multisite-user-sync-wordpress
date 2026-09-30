import { test, expect } from '@playwright/test';
import { expectSoundLayout } from '../support/layout';
import { NETWORK_SCREENS, SITE_SCREENS, adminScreens } from '../support/screens';
import { clearKnobs, clearQueue, createSite, createUser, setKnobs } from '../support/network';
import { networkScreen, siteScreen, submit, syncForm } from '../support/ui';

/**
 * The layout invariants of every screen, at every width that matters: no
 * overlap, nothing past the right edge, no empty painted box, no sideways
 * scroll (support/layout.ts says why each). No baseline images, so it runs
 * with the rest of the suite and in CI.
 */

test.describe('The registry is complete', () => {
	test('every screen in the network menu and the site menu is in support/screens.ts', async ({ page }) => {
		const slugsIn = async (menu: string): Promise<string[]> =>
			(await page.locator(`${menu} a[href*="page=wpmus-"]`).evaluateAll((links) =>
				links.map((link) => new URL((link as HTMLAnchorElement).href).searchParams.get('page') ?? '')
			)).filter((slug, index, all) => all.indexOf(slug) === index);

		await page.goto(networkScreen('wpmus-networkhome'));
		expect((await slugsIn('#toplevel_page_wpmus-networkhome')).sort()).toEqual(Object.keys(NETWORK_SCREENS).sort());

		await page.goto(siteScreen('wpmus-sitehome'));
		expect((await slugsIn('#toplevel_page_wpmus-sitehome')).sort()).toEqual(Object.keys(SITE_SCREENS).sort());
	});

	test('every tab of every screen is in support/screens.ts', async ({ page }) => {
		for (const screen of adminScreens().filter((one) => one.tab === '' || one.tab === 'welcome')) {
			await page.goto(screen.url);
			const tabs = await page
				.locator('.nav-tab-wrapper a.nav-tab')
				.evaluateAll((links) => links.map((link) => new URL((link as HTMLAnchorElement).href).searchParams.get('tab') ?? ''));
			const registry = { ...NETWORK_SCREENS, ...SITE_SCREENS }[screen.page];

			expect(tabs.length === 0 ? [''] : tabs, screen.name).toEqual(registry);
		}
	});
});

test.describe('Every screen holds its layout', () => {
	for (const screen of adminScreens()) {
		test(screen.name, async ({ page }) => {
			await page.goto(screen.url);
			await expectSoundLayout(page);
		});
	}
});

test.describe('The states a screen can be in hold it too', () => {
	test.afterEach(() => {
		clearKnobs();
		clearQueue();
	});

	test('Network Sync Actions with a sync running in the background and its notice', async ({ page }) => {
		const site = createSite('layout-queue');
		createUser('layout-queue');
		setKnobs({ inline_limit: 0, hold_cron: true });

		await page.goto(networkScreen('wpmus-networksyncactions'));
		const form = syncForm(page, 'wpmusSyncNetworkSiteFromScratch');
		await form.locator(`input[name="listSites[]"][value="${site.id}"]`).check();
		await submit(page, form);

		await expect(page.locator('#wpbody-content table.widefat')).toBeVisible();
		await expectSoundLayout(page);
	});

	test('Network Sync Options right after a save', async ({ page }) => {
		await page.goto(networkScreen('wpmus-networksyncoptions'));
		await submit(page, page.locator('form[action*="wpmusSaveGlobalConfig"]'));

		await expect(page.locator('#message.notice')).toBeVisible();
		await expectSoundLayout(page);
	});
});
