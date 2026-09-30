import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { userInfo } from 'node:os';

/**
 * WP-CLI inside a wp-env container — the suite's other hand.
 *
 * The browser does everything a person does: the forms, the menus, the
 * buttons. What it cannot do is set a network up for a test (a site with a
 * given default role, an archived one, a second super admin), read what the
 * plugin stored, or run WP-CLI's cron: that is what this is for, and nothing
 * a person would do in the dashboard is done here.
 *
 * It shells out to `npx wp-env run`, so it runs from a wp-env project
 * directory: the repository root (where Playwright runs) for the dev network,
 * or the Plugin Check project for the single site.
 */

/** wp-env prints its own progress lines; they are not the answer. */
const NOISE = /^[ℹ✔✖⚠]|^- |^Starting |^Ran `/;

export interface CliOptions {
	/** The site of the network the command runs against. */
	url?: string;
	/** The wp-env project directory; the repository root by default. */
	cwd?: string;
	/** `cli` (the dev site, the default) or `tests-cli`. */
	container?: 'cli' | 'tests-cli';
}

/**
 * The running container of this repository's wp-env service, found by the
 * compose file that mounts this checkout. `docker exec` into it costs a third
 * of `npx wp-env run` (no Node start, no config parsing), and a suite makes
 * hundreds of calls. Null when it cannot be found: the call then goes through
 * `npx wp-env run`, which always works.
 */
const containers = new Map<string, string | null>();

function containerOf(service: string): string | null {
	if (containers.has(service)) {
		return containers.get(service)!;
	}

	let found: string | null = null;

	try {
		const ids = execFileSync('docker', ['ps', '-q', '--filter', `label=com.docker.compose.service=${service}`], { encoding: 'utf8' })
			.split('\n')
			.filter(Boolean);

		for (const id of ids) {
			const file = execFileSync('docker', ['inspect', id, '--format', '{{index .Config.Labels "com.docker.compose.project.config_files"}}'], {
				encoding: 'utf8',
			}).trim();

			if (existsSync(file) && readFileSync(file, 'utf8').includes(`${process.cwd()}:`)) {
				found = id;
				break;
			}
		}
	} catch {
		found = null;
	}

	containers.set(service, found);

	return found;
}

export function wp(args: string[], options: CliOptions = {}): string {
	const service = options.container ?? 'cli';
	const command = ['wp', ...args, ...(options.url ? [`--url=${options.url}`] : [])];
	const direct = options.cwd ? null : containerOf(service);
	const [file, argv, cwd] = direct
		? ['docker', ['exec', '-u', `${userInfo().uid}:${userInfo().gid}`, '-w', '/var/www/html', direct, ...command], process.cwd()]
		: ['npx', ['wp-env', 'run', service, ...command], options.cwd ?? process.cwd()];

	const out = execFileSync(file, argv, {
		cwd,
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
		maxBuffer: 64 * 1024 * 1024,
		env: { ...process.env, WP_ENV_DEBUG: '' },
	});

	return out
		.split('\n')
		.filter((line) => !NOISE.test(line))
		.join('\n')
		.trim();
}

/**
 * PHP run inside WordPress (`wp eval`), whose output is JSON. One call does
 * the work of several WP-CLI commands, which is most of what a test's setup
 * costs.
 */
export function php<T = unknown>(code: string, options: CliOptions = {}): T {
	const out = wp(['eval', `echo wp_json_encode( (function () { ${code} })() );`], options);
	const line = out.split('\n').filter((one) => one.trim() !== '').pop() ?? 'null';

	return JSON.parse(line) as T;
}
