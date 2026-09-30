<?php
/**
 * The per-site "sync this site" action pulls every account on the
 * network into one site, so it belongs to whoever manages the
 * network's users, not to every site administrator.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Admin\SiteSyncActionsPage;
use WPMUS\Config;
use WPMUS\Plugin;

final class SiteSyncCapabilityIntegrationTest extends IntegrationTestCase {

	/** @var callable|null */
	private $die_handler;

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'wp_die_handler', array( $this, 'die_handler' ) );
		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'wp_die_handler', array( $this, 'die_handler' ) );
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		unset( $_REQUEST['_wpnonce'], $_POST['_wpnonce'] );
		if ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @return callable
	 */
	public function die_handler() {
		return static function ( $message ): void {
			throw new \RuntimeException( 'wp_die: ' . ( is_string( $message ) ? $message : 'error' ) );
		};
	}

	/**
	 * @param string $location Redirect target.
	 * @return string
	 */
	public function stop_redirect( $location ) {
		throw new \LogicException( 'redirect: ' . (string) $location );
	}

	private function page(): SiteSyncActionsPage {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		return new SiteSyncActionsPage( $plugin->engine() );
	}

	public function test_a_site_administrator_cannot_pull_the_network_into_their_site(): void {
		$slug     = 'siteadmin-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
		$admin_id = $this->make_user( $slug );
		$blog_id  = $this->make_site( $slug . '-site', $admin_id );
		$other_id = $this->make_user( $slug . '-bystander' );

		switch_to_blog( $blog_id );
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ), 'Pre-condition: a site administrator.' );
		$this->assertFalse( is_super_admin( $admin_id ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Config::NONCE_ACTION );

		try {
			$this->page()->handle_sync_current_site();
			$this->fail( 'The handler must stop a site administrator.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'wp_die:', $e->getMessage() );
		} catch ( \LogicException $e ) {
			$this->fail( 'The handler ran the sync for a site administrator: ' . $e->getMessage() );
		}

		$this->assertFalse( $this->is_member( $other_id, $blog_id ), 'Nobody was pulled into the site.' );
	}

	public function test_a_super_admin_can_run_it(): void {
		$slug    = 'superrun-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
		$blog_id = $this->make_site( $slug . '-site' );
		$user_id = $this->make_user( $slug . '-person' );

		switch_to_blog( $blog_id );
		wp_set_current_user( 1 );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Config::NONCE_ACTION );

		try {
			$this->page()->handle_sync_current_site();
			$this->fail( 'The handler must redirect when it is done.' );
		} catch ( \LogicException $e ) {
			$this->assertStringContainsString( 'wpmus-sitesyncactions', $e->getMessage() );
		}

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}
}
