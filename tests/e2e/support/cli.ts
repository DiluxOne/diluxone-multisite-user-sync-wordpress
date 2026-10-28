import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { userInfo } from 'node:os';
import { basename, dirname, resolve } from 'node:path';
import { DEV_ENV } from './env';

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
 * directory: the dev network's (the repository root, where Playwright runs,
 * or WPMUS_ENV's), or the Plugin Check project for the single site.
 */

/** wp-env prints its own progress lines; they are not the answer. */
const NOISE = /^[ℹ✔✖⚠]|^- |^Starting |^Ran `/;

export interface CliOptions {
	/** The site of the network the command runs against. */
	url?: string;
	/** The wp-env project directory; the dev network's by default (env.ts, DEV_ENV). */
	cwd?: string;
	/** `cli` (the dev site, the default) or `tests-cli`. */
	container?: 'cli' | 'tests-cli';
}

/**
 * The running container of a wp-env project's service. `docker exec` into it
 * costs a third of `npx wp-env run` (no Node start, no config parsing), and a
 * suite makes hundreds of calls. Null when it cannot be found: the call then
 * goes through `npx wp-env run`, which always works.
 *
 * The project is matched exactly, never by what it mounts: `make env` and
 * `make pre-pr` both mount this checkout. wp-env keeps a project's compose
 * file in a directory named after the MD5 of its .wp-env.json's path, the
 * whole hash or, since wp-env 11, `wp-env-<dir>-<first 8 of it>`.
 */
const containers = new Map<string, string | null>();

function containerOf(service: string, project: string): string | null {
	const key = `${project}\0${service}`;

	if (containers.has(key)) {
		return containers.get(key)!;
	}

	const hash = createHash('md5').update(resolve(project, '.wp-env.json')).digest('hex');
	let found: string | null = null;

	try {
		const ids = execFileSync('docker', ['ps', '-q', '--filter', `label=com.docker.compose.service=${service}`], { encoding: 'utf8' })
			.split('\n')
			.filter(Boolean);

		for (const id of ids) {
			const file = execFileSync('docker', ['inspect', id, '--format', '{{index .Config.Labels "com.docker.compose.project.config_files"}}'], {
				encoding: 'utf8',
			}).trim();
			const dir = basename(dirname(file));

			if (dir === hash || dir.endsWith(`-${hash.slice(0, 8)}`)) {
				found = id;
				break;
			}
		}
	} catch {
		found = null;
	}

	containers.set(key, found);

	return found;
}

export function wp(args: string[], options: CliOptions = {}): string {
	const service = options.container ?? 'cli';
	const command = ['wp', ...args, ...(options.url ? [`--url=${options.url}`] : [])];
	const project = options.cwd ?? DEV_ENV;
	const direct = containerOf(service, project);
	const [file, argv, cwd] = direct
		? ['docker', ['exec', '-u', `${userInfo().uid}:${userInfo().gid}`, '-w', '/var/www/html', direct, ...command], process.cwd()]
		: ['npx', ['wp-env', 'run', service, ...command], project];

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
