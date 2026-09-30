import { networkScreen, siteScreen } from './ui';

/**
 * Every screen the plugin has, and every tab on it.
 *
 * In `support/` because three suites walk the same list: the layout spec
 * measures each screen, the snapshot spec photographs each one, the listing
 * spec picks its pictures from it. That the list is complete is not left to
 * trust: the layout spec reads the plugin's menu and each tab strip and fails
 * on a screen or a tab that is not written down here.
 *
 * A screen without tabs is written with one empty tab.
 */

/** Network Admin › User Sync. */
export const NETWORK_SCREENS: Record<string, string[]> = {
	'wpmus-networkhome': ['welcome', 'concepts', 'about'],
	'wpmus-networksyncoptions': [''],
	'wpmus-networksyncactions': [''],
};

/** A site's dashboard › User Sync (super admins only). */
export const SITE_SCREENS: Record<string, string[]> = {
	'wpmus-sitehome': ['welcome', 'concepts', 'about'],
	'wpmus-sitesyncactions': [''],
};

export interface AdminScreen {
	/** How a test names it: where, the slug and the tab, never a translated title. */
	name: string;
	page: string;
	tab: string;
	url: string;
}

function flatten(where: 'network' | 'site', screens: Record<string, string[]>): AdminScreen[] {
	const list: AdminScreen[] = [];

	for (const [page, tabs] of Object.entries(screens)) {
		for (const tab of tabs) {
			list.push({
				name: [where, page, tab].filter(Boolean).join(' › '),
				page,
				tab,
				url: where === 'network' ? networkScreen(page, tab) : siteScreen(page, tab),
			});
		}
	}

	return list;
}

/** Every screen and tab, Network Admin first, in menu order. */
export function adminScreens(): AdminScreen[] {
	return [...flatten('network', NETWORK_SCREENS), ...flatten('site', SITE_SCREENS)];
}
