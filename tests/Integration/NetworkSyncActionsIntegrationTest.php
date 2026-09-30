<?php
/**
 * The network sync actions offer, and honour, the choice to add back
 * people who were removed from a site.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Admin\NetworkSyncActionsPage;
use WPMUS\Config;
use WPMUS\Plugin;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Sync\JobQueue;

final class NetworkSyncActionsIntegrationTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );
		unset( $_REQUEST['_wpnonce'], $_POST['listSites'], $_POST['wpmus_force'] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param string $location Redirect target.
	 * @return string
	 */
	public function stop_redirect( $location ) {
		throw new \LogicException( 'redirect: ' . (string) $location );
	}

	private function page(): NetworkSyncActionsPage {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		return new NetworkSyncActionsPage( new SiteRepository(), $plugin->engine(), new JobQueue() );
	}

	public function test_both_forms_offer_to_add_back_removed_people(): void {
		ob_start();
		$this->page()->render();
		$html = (string) ob_get_clean();

		$this->assertSame( 2, substr_count( $html, 'name="wpmus_force"' ) );
	}

	public function test_ticking_the_box_adds_a_removed_person_back(): void {
		$slug    = 'forced-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
		$user_id = $this->make_user( $slug );
		$blog_id = $this->make_site( $slug . '-site' );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		remove_user_from_blog( $user_id, $blog_id );

		wp_set_current_user( 1 );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Config::NONCE_ACTION );
		$_POST['listSites']   = array( (string) $blog_id );
		$_POST['wpmus_force'] = 'yes';

		try {
			$this->page()->handle_sync_selected();
			$this->fail( 'The handler must redirect when it is done.' );
		} catch ( \LogicException $e ) {
			$this->assertStringContainsString( 'synced=true', $e->getMessage() );
		}

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}
}
