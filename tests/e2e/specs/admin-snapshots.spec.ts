import { test, expect, Page } from '@playwright/test';
import { php } from '../support/cli';
import { adminScreens } from '../support/screens';

/**
 * A picture of every screen, compared with the picture from last time.
 *
 * The layout measurements know the rules a screen must not break; they do not
 * know what it is supposed to look like. A heading two sizes too big, a line
 * nobody asked for, a box whose ground went grey keep every rule and are still
 * wrong, and only yesterday's picture catches them.
 *
 * What is photographed is the admin's content area, not the window: the admin
 * bar, the menu badges and the footer's version change on their own. The
 * window, the pixel ratio and the motion are pinned in the `visual` project
 * (playwright.config.ts). A baseline is one machine's font rendering, so this
 * is opt-in: `make test-visual` compares, `make test-visual-update` retakes,
 * and the diff in the commit is the review.
 */

/** Painted over before the picture: the box stays, only its content is forgiven. */
const MOVES_BY_ITSELF = [
	// When a background sync started.
	'#wpbody-content table.widefat td:nth-child(2)',
];

function pictureOf(name: string): string {
	return `${name.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '')}.png`;
}

async function settled(page: Page): Promise<void> {
	await page.evaluate(() => document.fonts.ready.then(() => undefined));
	await page.evaluate(() => {
		window.scrollTo(0, 0);

		return new Promise<void>((done) => requestAnimationFrame(() => requestAnimationFrame(() => done())));
	});
}

/*
 * The pictures are of the plugin in English, the language its strings are
 * written in; the dev network may be in another. Said for the length of the
 * run and put back after.
 */
let language: { network: string; site: string };

test.beforeAll(() => {
	language = php<{ network: string; site: string }>(`
		$keep = array( 'network' => (string) get_site_option( 'WPLANG', '' ), 'site' => (string) get_option( 'WPLANG', '' ) );
		update_site_option( 'WPLANG', '' );
		update_option( 'WPLANG', '' );
		return $keep;
	`);
});

test.afterAll(() => {
	php(`update_site_option( 'WPLANG', base64_decode( '${Buffer.from(language.network).toString('base64')}' ) ); update_option( 'WPLANG', base64_decode( '${Buffer.from(language.site).toString('base64')}' ) ); return true;`);
});

test.describe('Every screen looks like it did', () => {
	for (const screen of adminScreens()) {
		test(screen.name, async ({ page }) => {
			await page.goto(screen.url);
			await settled(page);

			await expect(page.locator('#wpbody-content')).toHaveScreenshot(pictureOf(screen.name), {
				mask: MOVES_BY_ITSELF.map((one) => page.locator(one)),
			});
		});
	}
});
