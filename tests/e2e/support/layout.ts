import { Page, expect } from '@playwright/test';

/**
 * What a screen looks like, asked of the browser.
 *
 * The behaviour specs ask whether the plugin does the right thing; none of
 * them notices a block drawn on top of the one above it, a row of links that
 * runs off the right edge on a laptop, or a coloured box with nothing in it.
 * Those are geometry, and only a browser can see geometry, so this measures
 * it. Not "does it look nice": the handful of things that are never right by
 * accident and never wrong on purpose.
 *
 *   overlap   two blocks side by side in the markup, sharing pixels
 *   overflow  something reaching past the right edge of the plugin's area
 *   blank     a box with a border or a ground and nothing inside it
 *   hidden    an element with the hidden attribute that is still drawn
 *   sideways  the page itself scrolling sideways
 *
 * Every one is a number compared with a number: no baseline image, the
 * finding names the element, and it means the same on every machine, so it
 * runs with the rest of the suite and in CI. The pictures in
 * admin-snapshots.spec.ts are the other half.
 */

/**
 * The widths that matter: a desk, a laptop, WordPress folding its menu to
 * icons (960) and WordPress's phone layout (782).
 */
export const WIDTHS = [1600, 1280, 960, 782] as const;

export interface LayoutFinding {
	kind: 'overlap' | 'overflow' | 'blank' | 'hidden' | 'root';
	where: string;
	detail: string;
}

/** The part of the page the plugin draws: everything under the admin's content area. */
export const ROOT = '#wpbody-content';

export async function layoutFindings(page: Page, root = ROOT): Promise<LayoutFinding[]> {
	await page.evaluate(() => {
		window.scrollTo(0, 0);

		return new Promise<void>((done) => requestAnimationFrame(() => requestAnimationFrame(() => done())));
	});

	return page.evaluate((rootSelector: string): LayoutFinding[] => {
		const found: LayoutFinding[] = [];
		const host = document.querySelector<HTMLElement>(rootSelector);

		if (!host) {
			return [{ kind: 'root', where: rootSelector, detail: 'the page has no such block' }];
		}

		/* A pixel of slack: borders sit on the boundary and browsers round. */
		const slack = 1;

		const label = (el: Element): string => {
			const classes =
				typeof el.className === 'string' && el.className.trim() !== ''
					? `.${el.className.trim().split(/\s+/).slice(0, 3).join('.')}`
					: '';

			return `${el.tagName.toLowerCase()}${el.id !== '' ? `#${el.id}` : ''}${classes}`;
		};

		const trail = (el: Element): string => {
			const parts: string[] = [];

			for (let node: Element | null = el; node && node !== host; node = node.parentElement) {
				parts.unshift(label(node));
			}

			return parts.join(' › ') || label(host);
		};

		const drawn = (el: Element, style: CSSStyleDeclaration): boolean => {
			const rect = el.getBoundingClientRect();

			return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0.5 && rect.height > 0.5;
		};

		/* Blocks only: two inline boxes wrapping across a line overlap and nothing is wrong. */
		const BLOCK = ['block', 'flex', 'grid', 'list-item', 'flow-root', 'table', 'inline-block'];

		const inFlow = (style: CSSStyleDeclaration): boolean =>
			style.position !== 'absolute' && style.position !== 'fixed' && style.float === 'none';

		/* Laid out by rules of their own, or drawn by the browser: measured as one block, not walked. */
		const OPAQUE = new Set(['table', 'svg', 'iframe', 'select', 'textarea', 'input', 'button', 'img']);

		/* WordPress's own furniture inside the content area, not the plugin's. */
		const FOREIGN = '#screen-meta, #screen-meta-links, .clear';

		const paints = (style: CSSStyleDeclaration): boolean => {
			const ground =
				style.backgroundImage !== 'none' ||
				(style.backgroundColor !== 'rgba(0, 0, 0, 0)' && style.backgroundColor !== 'transparent');
			const edge = ['top', 'right', 'bottom', 'left'].some(
				(side) =>
					parseFloat(style.getPropertyValue(`border-${side}-width`)) > 0 &&
					!['rgba(0, 0, 0, 0)', 'transparent'].includes(style.getPropertyValue(`border-${side}-color`))
			);

			return ground || edge;
		};

		const rootBox = host.getBoundingClientRect();
		const stack: Array<{ el: Element; clipped: boolean }> = [{ el: host, clipped: false }];

		while (stack.length > 0) {
			const here = stack.pop()!;
			const kids: Array<{ el: Element; rect: DOMRect }> = [];

			for (const child of Array.from(here.el.children)) {
				if (child.matches(FOREIGN)) {
					continue;
				}

				const style = getComputedStyle(child);

				if (!drawn(child, style)) {
					continue;
				}

				const rect = child.getBoundingClientRect();

				if (child.hasAttribute('hidden')) {
					found.push({ kind: 'hidden', where: trail(child), detail: `has the hidden attribute and is ${Math.round(rect.width)}×${Math.round(rect.height)} on the screen` });
				}

				if (!here.clipped && rect.right > rootBox.right + slack) {
					found.push({ kind: 'overflow', where: trail(child), detail: `reaches ${Math.round(rect.right - rootBox.right)}px past the right edge of ${rootSelector}` });
				}

				if (
					!OPAQUE.has(child.tagName.toLowerCase()) &&
					rect.width >= 48 &&
					rect.height >= 24 &&
					(child.textContent ?? '').trim() === '' &&
					child.querySelector('img, svg, canvas, iframe, input, select, textarea, button') === null &&
					paints(style)
				) {
					found.push({ kind: 'blank', where: trail(child), detail: `${Math.round(rect.width)}×${Math.round(rect.height)} with a border or a ground and nothing inside it` });
				}

				if (BLOCK.includes(style.display) && inFlow(style)) {
					kids.push({ el: child, rect });
				}

				if (!OPAQUE.has(child.tagName.toLowerCase())) {
					const scrolls = ['auto', 'scroll', 'hidden'].includes(style.overflowX);

					stack.push({ el: child, clipped: here.clipped || scrolls });
				}
			}

			for (let i = 0; i < kids.length; i++) {
				for (let j = i + 1; j < kids.length; j++) {
					const a = kids[i].rect;
					const b = kids[j].rect;
					const across = Math.min(a.right, b.right) - Math.max(a.left, b.left);
					const down = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);

					if (across > slack && down > slack) {
						found.push({
							kind: 'overlap',
							where: `${trail(kids[i].el)}  ✕  ${trail(kids[j].el)}`,
							detail: `sharing ${Math.round(across)}×${Math.round(down)}px inside ${label(here.el)}`,
						});
					}
				}
			}
		}

		return found;
	}, root);
}

/** How far the page as a whole scrolls sideways. Nothing may. */
export async function sidewaysScroll(page: Page): Promise<number> {
	return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

export function readFindings(findings: LayoutFinding[]): string {
	return findings.map((one) => `  [${one.kind}] ${one.where}\n      ${one.detail}`).join('\n');
}

/** Measures the loaded screen at every width and complains about all of it at once. */
export async function expectSoundLayout(page: Page, widths: readonly number[] = WIDTHS): Promise<void> {
	const wrong: string[] = [];

	for (const width of widths) {
		await page.setViewportSize({ width, height: 1000 });

		const findings = await layoutFindings(page);
		const sideways = await sidewaysScroll(page);

		if (sideways > 1) {
			findings.push({ kind: 'overflow', where: 'the page itself', detail: `scrolls ${sideways}px sideways` });
		}

		if (findings.length > 0) {
			wrong.push(`at ${width}px:\n${readFindings(findings)}`);
		}
	}

	expect(wrong.join('\n\n'), `layout at ${page.url()}`).toBe('');
}
