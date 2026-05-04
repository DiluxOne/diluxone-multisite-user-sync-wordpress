<?php
/**
 * Enqueues the plugin's admin stylesheet. The CSS file lives at
 * `css/wpmus_styles.css` (legacy filename retained for back-compat
 * with any third-party `wp_dequeue_style( 'wpmus_styles' )` calls).
 *
 * @package WPMUS
 */

declare(strict_types=1);

namespace WPMUS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	private string $plugin_file;

	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	public function enqueue_admin_styles(): void {
		wp_enqueue_style(
			'wpmus_styles',
			plugins_url( 'css/wpmus_styles.css', $this->plugin_file ),
			array(),
			'1.5.0'
		);
	}
}
