import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

/**
 * Where the suites point, read from .wp-env.json rather than typed: the ports
 * of this repository's stacks are chosen so they do not clash with the other
 * DiluxOne plugins on the same machine, and a test that hardcodes one is a
 * test that talks to somebody else's WordPress the day the port moves.
 */
const config = JSON.parse(readFileSync('.wp-env.json', 'utf8')) as { port?: number; testsPort?: number };

/** The dev network: Network Admin, the sites, the triggers. */
export const DEV_URL = (process.env.WP_BASE_URL ?? `http://localhost:${config.port ?? 8888}`).replace(/\/$/, '');

/**
 * A plain single site with the built plugin mounted, for the one check that
 * needs a WordPress that is not a network (`make test-e2e-single`: the tests
 * site of the Plugin Check environment). Unset, the project does not exist.
 */
export const SINGLE_URL = process.env.WPMUS_SINGLE_URL?.replace(/\/$/, '');

/** The wp-env project directory of that single site, for its WP-CLI. */
export const SINGLE_ENV = process.env.WPMUS_SINGLE_ENV;

/** The super admin the setup signs in as, kept for every spec. */
export const ADMIN_STATE = 'build/e2e-admin.json';
export const ADMIN_USER = process.env.WP_USER ?? 'admin';
export const ADMIN_PASS = process.env.WP_PASS ?? 'password';

/**
 * The plugin's folder inside the dev container. wp-env mounts the checkout
 * under its own directory name, which is where Playwright runs from; the
 * repository may be renamed, the slug may not.
 */
export const PLUGIN_DIR = basename(process.cwd());

/** What the state files the setup and the teardown share are called. */
export const STATE_FILE = 'build/e2e-network-state.json';
