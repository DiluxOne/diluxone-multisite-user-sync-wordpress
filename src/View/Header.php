<?php
/**
 * Renders the shared page header that every admin page in the plugin
 * displays at the top.
 *
 * @package WPMUS\View
 */

declare(strict_types=1);

namespace WPMUS\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Header {

	public function render(): void {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'WPM User Sync', 'wpm-user-sync' ); ?></h2>
			<p>
				<?php
				echo wp_kses(
					__( 'Welcome to the <strong>best free user synchronization solution</strong> for WordPress Multisite.', 'wpm-user-sync' ),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
		<?php
	}
}
