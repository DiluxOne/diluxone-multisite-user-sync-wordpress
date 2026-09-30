import { test as teardown } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { php } from './support/cli';
import { STATE_FILE } from './support/env';
import { clearKnobs, sweep } from './support/network';

/**
 * Leaves the network as the setup found it: the users and sites the suite
 * made are deleted, the knobs are gone, and the toggles and the queue are
 * what they were before the run.
 */
teardown('put the network back', async () => {
	sweep();
	clearKnobs();

	if (!existsSync(STATE_FILE)) {
		return;
	}

	const previous = JSON.stringify(JSON.parse(readFileSync(STATE_FILE, 'utf8')));

	php(`
		$previous = json_decode( '${previous.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}', true );
		foreach ( $previous as $key => $value ) {
			if ( null === $value ) {
				delete_site_option( $key );
			} else {
				update_site_option( $key, $value );
			}
		}
		delete_site_option( 'wpmus_sync_lock' );
		if ( empty( $previous['wpmus_sync_jobs'] ) ) {
			switch_to_blog( get_main_site_id() );
			wp_clear_scheduled_hook( 'wpmus_process_sync_queue' );
			restore_current_blog();
		}
		return true;
	`);
});
