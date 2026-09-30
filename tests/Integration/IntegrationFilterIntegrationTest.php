<?php
/**
 * DiluxOne Users+ asks, through a filter, who manages network
 * membership. WPM User Sync answers while any automatic trigger is
 * on, and stays silent otherwise. Nothing here depends on Users+.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;

final class IntegrationFilterIntegrationTest extends IntegrationTestCase {

	public function test_it_claims_membership_while_a_trigger_is_on(): void {
		$config = new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' );

		$this->assertSame( '', apply_filters( 'diluxone_users_membership_managed_by', '' ) );

		$config->save_toggles( '', 'yes', '' );
		$this->assertSame( 'WPM User Sync', apply_filters( 'diluxone_users_membership_managed_by', '' ) );

		$config->save_toggles( 'yes', '', '' );
		$this->assertSame( 'WPM User Sync', apply_filters( 'diluxone_users_membership_managed_by', '' ) );

		$config->save_toggles( '', '', '' );
		$this->assertSame( 'Someone else', apply_filters( 'diluxone_users_membership_managed_by', 'Someone else' ) );
	}
}
