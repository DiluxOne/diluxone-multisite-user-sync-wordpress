<?php
/**
 * Unit tests for {@see \WPMUS\Assets}: the stylesheet loads on the
 * plugin's own screens only.
 *
 * @package WPMUS\Tests\Unit
 */

declare(strict_types=1);

namespace Tests\Unit;

use Brain\Monkey\Functions;
use Tests\TestCase;
use WPMUS\Assets;

final class AssetsTest extends TestCase {

	public function test_the_stylesheet_loads_on_the_plugins_screens(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.test/wp-content/plugins/wpm-user-sync/css/wpmus_styles.css' );
		Functions\expect( 'wp_enqueue_style' )->once();

		( new Assets( '/path/to/wpm-user-sync.php' ) )->enqueue_admin_styles( 'toplevel_page_wpmus-networkhome' );
	}

	public function test_the_stylesheet_stays_off_every_other_screen(): void {
		Functions\when( 'plugins_url' )->justReturn( 'https://example.test/x.css' );
		Functions\expect( 'wp_enqueue_style' )->never();

		( new Assets( '/path/to/wpm-user-sync.php' ) )->enqueue_admin_styles( 'users.php' );
	}
}
