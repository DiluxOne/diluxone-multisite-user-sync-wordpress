<?php
/**
 * What the network screens draw from real state: the progress of the
 * syncs running in the background, the notices that follow an action,
 * and the toggles as they are stored.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Admin\NetworkSyncActionsPage;
use WPMUS\Admin\NetworkSyncOptionsPage;
use WPMUS\Config;
use WPMUS\Notices;
use WPMUS\Plugin;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Sync\JobQueue;
use WPMUS\Sync\SyncJob;

final class AdminScreensIntegrationTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		( new JobQueue() )->clear();
	}

	protected function tearDown(): void {
		( new JobQueue() )->clear();
		unset( $_GET['page'], $_GET['queued'], $_GET['updated'], $_GET['nosynced'] );
		parent::tearDown();
	}

	private function actions_page(): NetworkSyncActionsPage {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );

		return new NetworkSyncActionsPage( new SiteRepository(), $plugin->engine(), new JobQueue() );
	}

	private function render( callable $render ): string {
		ob_start();
		$render();

		return (string) ob_get_clean();
	}

	public function test_with_nothing_queued_the_actions_screen_shows_no_progress_table(): void {
		$html = $this->render( array( $this->actions_page(), 'render' ) );

		$this->assertStringContainsString( 'wpmusSyncNetworkFromScratch', $html );
		$this->assertStringContainsString( 'wpmusSyncNetworkSiteFromScratch', $html );
		$this->assertStringNotContainsString( 'Syncs running in the background', $html );
	}

	public function test_a_queued_sync_is_listed_with_how_far_it_got(): void {
		$job            = new SyncJob( 'manual', null, null, false );
		$job->total     = 8;
		$job->processed = 2;
		( new JobQueue() )->add( $job );

		$html = $this->render( array( $this->actions_page(), 'render' ) );

		$this->assertStringContainsString( 'Syncs running in the background', $html );
		$this->assertStringContainsString( 'Manual sync', $html );
		$this->assertStringContainsString( '25% (2 of 8 user-site pairs)', $html );
	}

	public function test_every_live_site_of_the_network_is_offered_in_the_site_list(): void {
		$blog_id = $this->make_site( 'screens-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 ) );

		$html = $this->render( array( $this->actions_page(), 'render' ) );

		$this->assertStringContainsString( 'name="listSites[]" value="' . $blog_id . '"', $html );
	}

	public function test_the_options_screen_ticks_what_is_stored(): void {
		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( 'yes', '', 'yes' );

		$html = $this->render( array( new NetworkSyncOptionsPage( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) ), 'render' ) );

		$this->assertMatchesRegularExpression( '/name="wpmus_newSiteSync"[^>]*checked/', $html );
		$this->assertDoesNotMatchRegularExpression( '/name="wpmus_newUserSync"[^>]*checked/', $html );
		$this->assertMatchesRegularExpression( '/name="wpmus_setUserRoleSync"[^>]*checked/', $html );
	}

	public function test_the_notice_after_a_queued_sync_shows_on_the_plugins_screens_only(): void {
		$_GET['queued'] = 'true';

		$_GET['page'] = 'wpmus-networksyncactions';
		$this->assertStringContainsString( 'runs in the background in batches', $this->render( array( new Notices(), 'render' ) ) );

		$_GET['page'] = 'some-other-plugin';
		$this->assertSame( '', $this->render( array( new Notices(), 'render' ) ) );
	}

	public function test_the_notice_when_no_site_was_picked_is_a_warning(): void {
		$_GET['page']     = 'wpmus-networksyncactions';
		$_GET['nosynced'] = 'true';

		$html = $this->render( array( new Notices(), 'render' ) );

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'You must select at least one site', $html );
	}
}
