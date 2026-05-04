<?php
/**
 * Registers the per-site admin menu and its single subpage, and
 * delegates each page's rendering to the matching page class.
 *
 * @package WPMUS\Admin
 */

declare(strict_types=1);

namespace WPMUS\Admin;

use WPMUS\View\Header;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteMenu {

	private Header $header;
	private SiteHomePage $home;
	private SiteSyncActionsPage $actions;

	public function __construct( Header $header, SiteHomePage $home, SiteSyncActionsPage $actions ) {
		$this->header  = $header;
		$this->home    = $home;
		$this->actions = $actions;
	}

	public function register(): void {
		add_menu_page(
			__( 'WPM User Sync', 'wpm-user-sync' ),
			__( 'WPM User Sync', 'wpm-user-sync' ),
			'manage_options',
			'wpmus-sitehome',
			array( $this, 'render_home' ),
			'dashicons-admin-generic',
			100
		);

		add_submenu_page(
			'wpmus-sitehome',
			__( 'Site Sync Actions', 'wpm-user-sync' ),
			__( 'Site Sync Actions', 'wpm-user-sync' ),
			'manage_options',
			'wpmus-sitesyncactions',
			array( $this, 'render_actions' )
		);
	}

	public function render_home(): void {
		$this->header->render();
		$this->home->render();
	}

	public function render_actions(): void {
		$this->header->render();
		$this->actions->render();
	}
}
