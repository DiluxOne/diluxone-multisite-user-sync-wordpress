<?php
/**
 * Network Sync Actions page — two manual sync forms (sync everything
 * vs sync selected sites) plus the matching save handlers.
 *
 * @package WPMUS\Admin
 */

declare(strict_types=1);

namespace WPMUS\Admin;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Sync\SyncEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network Sync Actions page — two manual sync forms (sync everything,
 * or sync only selected sites) plus the matching save handlers.
 */
final class NetworkSyncActionsPage {

	private SiteRepository $sites;
	private SyncEngine $engine;

	/**
	 * @param SiteRepository $sites  For listing the sites in the
	 *                               "Sync specific sites" form.
	 * @param SyncEngine     $engine Runs the actual sync work.
	 */
	public function __construct( SiteRepository $sites, SyncEngine $engine ) {
		$this->sites  = $sites;
		$this->engine = $engine;
	}

	/**
	 * Render both manual-sync forms. Output is HTML.
	 */
	public function render(): void {
		$all_sites = $this->sites->all_sites();
		?>
		<div class="wrap">
			<h3><?php esc_html_e( 'Network Sync Actions', 'wpm-user-sync' ); ?></h3>
			<p><?php esc_html_e( 'These actions let you sync users and sites with several options.', 'wpm-user-sync' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Sync from scratch', 'wpm-user-sync' ); ?></th>
					<td>
						<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=wpmusSyncNetworkFromScratch' ) ); ?>">
							<?php wp_nonce_field( Config::NONCE_ACTION ); ?>
							<input type="submit" value="<?php esc_attr_e( 'Sync from scratch', 'wpm-user-sync' ); ?>" class="button" />
						</form>
						<p class="description"><?php esc_html_e( 'Sync every site with every user. Each site receives every user with the default site role. Existing memberships are not modified.', 'wpm-user-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sync specific sites', 'wpm-user-sync' ); ?></th>
					<td>
						<p><?php esc_html_e( 'Select the sites you want to sync users into:', 'wpm-user-sync' ); ?></p>
						<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=wpmusSyncNetworkSiteFromScratch' ) ); ?>">
							<?php wp_nonce_field( Config::NONCE_ACTION ); ?>
							<?php foreach ( $all_sites as $site ) : ?>
								<label>
									<input type="checkbox" name="listSites[]" value="<?php echo esc_attr( (string) $site->blog_id ); ?>" />
									<?php echo esc_html( $site->domain . $site->path ); ?>
								</label><br />
							<?php endforeach; ?>
							<br />
							<input type="submit" value="<?php esc_attr_e( 'Sync selected sites', 'wpm-user-sync' ); ?>" class="button" />
						</form>
						<p class="description"><?php esc_html_e( 'All selected sites will receive every user with the default site role. Existing memberships are not modified.', 'wpm-user-sync' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Hooked on `network_admin_edit_wpmusSyncNetworkFromScratch`.
	 */
	public function handle_sync_all(): void {
		check_admin_referer( Config::NONCE_ACTION );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run a network-wide sync.', 'wpm-user-sync' ), '', array( 'response' => 403 ) );
		}

		$this->engine->sync_all_users_to_all_sites();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'wpmus-networksyncactions',
					'synced' => 'true',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Hooked on `network_admin_edit_wpmusSyncNetworkSiteFromScratch`.
	 */
	public function handle_sync_selected(): void {
		check_admin_referer( Config::NONCE_ACTION );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run a network sync.', 'wpm-user-sync' ), '', array( 'response' => 403 ) );
		}

		$list_sites_raw = isset( $_POST['listSites'] ) && is_array( $_POST['listSites'] )
			? array_map( 'absint', wp_unslash( $_POST['listSites'] ) )
			: array();
		/** @var int[] $blog_ids */
		$blog_ids = array_values( array_filter( $list_sites_raw ) );

		if ( array() === $blog_ids ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'wpmus-networksyncactions',
						'nosynced' => 'true',
					),
					network_admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$this->engine->sync_all_users_to_sites( $blog_ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'wpmus-networksyncactions',
					'synced' => 'true',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
