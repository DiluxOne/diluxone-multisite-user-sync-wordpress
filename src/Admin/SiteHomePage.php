<?php
/**
 * Site (per-blog) home page — three tabs: Welcome, Concepts, About.
 * Mirrors the structure of {@see NetworkHomePage} with copy adapted
 * for site administrators (who have no options to set, only an
 * action button).
 *
 * @package WPMUS\Admin
 */

declare(strict_types=1);

namespace WPMUS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteHomePage {

	public function render(): void {
		$active_tab = $this->active_tab();
		?>
		<h2 class="nav-tab-wrapper">
			<?php $this->render_tab( 'welcome', __( 'Welcome', 'wpm-user-sync' ), $active_tab ); ?>
			<?php $this->render_tab( 'concepts', __( 'Concepts', 'wpm-user-sync' ), $active_tab ); ?>
			<?php $this->render_tab( 'about', __( 'About', 'wpm-user-sync' ), $active_tab ); ?>
		</h2>
		<?php

		switch ( $active_tab ) {
			case 'concepts':
				$this->render_concepts();
				break;
			case 'about':
				$this->render_about();
				break;
			case 'welcome':
			default:
				$this->render_welcome();
				break;
		}
	}

	private function active_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'welcome', 'concepts', 'about' ), true ) ? $tab : 'welcome';
	}

	private function render_tab( string $slug, string $label, string $active_tab ): void {
		$url     = admin_url( 'admin.php?page=wpmus-sitehome&tab=' . $slug );
		$classes = 'nav-tab' . ( $slug === $active_tab ? ' nav-tab-active' : '' );
		printf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $classes ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private function render_welcome(): void {
		$concepts_url = admin_url( 'admin.php?page=wpmus-sitehome&tab=concepts' );
		$actions_url  = admin_url( 'admin.php?page=wpmus-sitesyncactions' );
		$about_url    = admin_url( 'admin.php?page=wpmus-sitehome&tab=about' );
		?>
		<h3><?php esc_html_e( 'Welcome to Site "WPM User Sync" Plugin', 'wpm-user-sync' ); ?></h3>
		<p><?php esc_html_e( 'Thank you for choosing WPM User Sync (which actually means "WordPress Multi-Site User Synchronization").', 'wpm-user-sync' ); ?></p>
		<p><?php esc_html_e( 'If you are new to this plugin we recommend you check the basic synchronization concepts. If this is your first time using the plugin, you can also do your first full sync.', 'wpm-user-sync' ); ?></p>

		<a href="<?php echo esc_url( $concepts_url ); ?>" class="cuadrado"><?php esc_html_e( '1. Review basic concepts', 'wpm-user-sync' ); ?></a>
		<a href="<?php echo esc_url( $actions_url ); ?>" class="cuadrado"><?php esc_html_e( '2. Complete the initial Users Sync', 'wpm-user-sync' ); ?></a>
		<a href="<?php echo esc_url( $about_url ); ?>" class="cuadrado"><?php esc_html_e( '3. Meet the Authors & Support Us', 'wpm-user-sync' ); ?></a>
		<?php
	}

	private function render_concepts(): void {
		?>
		<h3><?php esc_html_e( 'Site User Sync Concepts', 'wpm-user-sync' ); ?></h3>
		<p><?php esc_html_e( 'WPM User Sync has some simple but important concepts. Knowing all of them will help you get a better experience with the tool.', 'wpm-user-sync' ); ?></p>

		<h4><?php esc_html_e( 'What exactly does this plugin do?', 'wpm-user-sync' ); ?></h4>
		<p><?php esc_html_e( 'WPM User Sync enables user synchronization in your WordPress Multisite — see the Network admin Concepts tab for the full explanation. As a site admin you have one action available: sync every network user into this site.', 'wpm-user-sync' ); ?></p>

		<h4><?php esc_html_e( 'What can a site administrator configure?', 'wpm-user-sync' ); ?></h4>
		<p><?php esc_html_e( 'Nothing. All triggers and toggles are configured at the network level by the network administrator. As a site admin you can run the manual "sync from scratch" action: it adds every network user to this site with the default site role. Existing memberships are not modified.', 'wpm-user-sync' ); ?></p>
		<?php
	}

	private function render_about(): void {
		?>
		<h3><?php esc_html_e( 'About', 'wpm-user-sync' ); ?></h3>
		<p><?php esc_html_e( 'This plugin was developed by Pablo Ariel Di Loreto:', 'wpm-user-sync' ); ?></p>
		<ul>
			<li>- <a href="https://www.linkedin.com/in/pablodiloreto/" target="_blank" rel="noopener"><?php esc_html_e( 'LinkedIn Contact', 'wpm-user-sync' ); ?></a>.</li>
			<li>- <a href="https://pablodiloreto.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Personal Blog', 'wpm-user-sync' ); ?></a>.</li>
			<li>- <a href="https://pablodiloreto.com/wpm-user-sync/" target="_blank" rel="noopener"><?php esc_html_e( 'Plugin Homepage', 'wpm-user-sync' ); ?></a>.</li>
		</ul>
		<?php
	}
}
