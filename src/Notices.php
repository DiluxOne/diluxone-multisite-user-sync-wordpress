<?php
/**
 * Renders the dismissible admin notices that follow the plugin's
 * post-action redirects. The redirects append `updated`, `synced`,
 * or `nosynced` to the query string and the matching notice is
 * rendered at the top of the next admin page render.
 *
 * @package WPMUS
 */

declare(strict_types=1);

namespace WPMUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notices {

	public function render(): void {
		// We only react to the presence of a marker in the URL — there
		// is no untrusted data flowing into the rendered HTML. The page
		// param is checked first to avoid emitting notices on unrelated
		// admin screens.
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_notice(
				'updated',
				esc_html__( "Settings updated. You're the best!", 'wpm-user-sync' )
			);
		}

		if ( isset( $_GET['synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_notice(
				'updated',
				esc_html__( "Sync done. You're a champion!", 'wpm-user-sync' )
			);
		}

		if ( isset( $_GET['nosynced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_notice(
				'notice-warning',
				esc_html__( 'Sync did not happen. You must select at least one site!', 'wpm-user-sync' )
			);
		}
	}

	private function render_notice( string $css_class, string $message ): void {
		printf(
			'<div id="message" class="%1$s notice is-dismissible"><p>%2$s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%3$s</span></button></div>',
			esc_attr( $css_class ),
			esc_html( $message ),
			esc_html__( 'Dismiss this notice.', 'wpm-user-sync' )
		);
	}
}
