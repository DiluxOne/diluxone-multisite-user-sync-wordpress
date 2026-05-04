<?php
/**
 * Unit tests for {@see \WPMUS\Repositories\SiteRepository}.
 *
 * The repository is a thin wrapper over `get_sites()` and
 * `get_blog_option()`. Brain Monkey stubs both so the tests verify
 * that the wrapper passes the right arguments and normalises the
 * return values consistently.
 *
 * @package WPMUS\Tests\Unit\Repositories
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Brain\Monkey\Functions;
use Tests\TestCase;
use WPMUS\Repositories\SiteRepository;

final class SiteRepositoryTest extends TestCase {

	public function test_all_blog_ids_passes_correct_args_and_casts_to_int(): void {
		Functions\expect( 'get_sites' )
			->once()
			->with(
				array(
					'fields'                 => 'ids',
					'number'                 => 0,
					'update_site_meta_cache' => false,
				)
			)
			->andReturn( array( '1', 2, '3' ) );

		$ids = ( new SiteRepository() )->all_blog_ids();

		$this->assertSame( array( 1, 2, 3 ), $ids );
	}

	public function test_all_blog_ids_returns_empty_array_when_no_sites(): void {
		Functions\when( 'get_sites' )->justReturn( array() );

		$this->assertSame( array(), ( new SiteRepository() )->all_blog_ids() );
	}

	public function test_all_sites_returns_full_objects(): void {
		$sites = array(
			new \WP_Site( 1, 'localhost', '/' ),
			new \WP_Site( 2, 'localhost', '/sitio01/' ),
		);
		Functions\expect( 'get_sites' )
			->once()
			->with(
				array(
					'number'                 => 0,
					'update_site_meta_cache' => false,
				)
			)
			->andReturn( $sites );

		$result = ( new SiteRepository() )->all_sites();

		$this->assertCount( 2, $result );
		$this->assertSame( $sites, $result );
	}

	public function test_default_role_for_blog_returns_option_value(): void {
		Functions\expect( 'get_blog_option' )
			->once()
			->with( 5, 'default_role', 'subscriber' )
			->andReturn( 'editor' );

		$this->assertSame( 'editor', ( new SiteRepository() )->default_role_for_blog( 5 ) );
	}

	public function test_default_role_for_blog_falls_back_when_option_returns_empty_string(): void {
		// Some installs persist `default_role => ''`; treat as unset
		// and use the documented fallback.
		Functions\when( 'get_blog_option' )->justReturn( '' );

		$this->assertSame( 'subscriber', ( new SiteRepository() )->default_role_for_blog( 9 ) );
	}

	public function test_default_role_for_blog_falls_back_when_option_returns_non_string(): void {
		// `get_blog_option` can return false on missing options; the
		// repository must coerce non-strings to the documented default.
		Functions\when( 'get_blog_option' )->justReturn( false );

		$this->assertSame( 'subscriber', ( new SiteRepository() )->default_role_for_blog( 7 ) );
	}
}
