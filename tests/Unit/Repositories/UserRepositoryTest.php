<?php
/**
 * Unit tests for {@see \WPMUS\Repositories\UserRepository}.
 *
 * Brain Monkey stubs the WP user-management functions used by the
 * repository so we can verify the right arguments are passed and the
 * right return-value coercions are applied.
 *
 * @package WPMUS\Tests\Unit\Repositories
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Brain\Monkey\Functions;
use Tests\TestCase;
use WPMUS\Repositories\UserRepository;

final class UserRepositoryTest extends TestCase {

	public function test_all_network_users_passes_blog_id_zero(): void {
		// `blog_id => 0` is the legacy idiom that asks WordPress for
		// every user across the network. The refactor must keep this
		// arg so the same dataset is returned.
		Functions\expect( 'get_users' )
			->once()
			->with( array( 'blog_id' => 0 ) )
			->andReturn( array( new \WP_User( 1 ), new \WP_User( 2 ) ) );

		$users = ( new UserRepository() )->all_network_users();

		$this->assertCount( 2, $users );
	}

	public function test_is_member_of_returns_bool(): void {
		Functions\expect( 'is_user_member_of_blog' )
			->once()
			->with( 5, 7 )
			->andReturn( true );

		$this->assertTrue( ( new UserRepository() )->is_member_of( 5, 7 ) );
	}

	public function test_is_member_of_coerces_non_bool_truthy_to_true(): void {
		// `is_user_member_of_blog` historically returned 1/0 in some WP
		// versions. The repository's bool cast must normalise that.
		Functions\when( 'is_user_member_of_blog' )->justReturn( 1 );

		$this->assertTrue( ( new UserRepository() )->is_member_of( 5, 7 ) );
	}

	public function test_add_to_blog_passes_args_through(): void {
		Functions\expect( 'add_user_to_blog' )
			->once()
			->with( 7, 5, 'editor' )
			->andReturn( true );

		$result = ( new UserRepository() )->add_to_blog( 7, 5, 'editor' );

		$this->assertTrue( $result );
	}

	public function test_find_by_login_returns_wp_user_when_found(): void {
		$expected = new \WP_User( 12 );
		Functions\expect( 'get_user_by' )
			->once()
			->with( 'login', 'pablito' )
			->andReturn( $expected );

		$user = ( new UserRepository() )->find_by_login( 'pablito' );

		$this->assertSame( $expected, $user );
	}

	public function test_find_by_login_returns_null_when_not_found(): void {
		// `get_user_by` returns false (not null) when no match exists.
		// The repository normalises that to null so the caller can
		// pattern-match without `=== false` checks.
		Functions\when( 'get_user_by' )->justReturn( false );

		$this->assertNull( ( new UserRepository() )->find_by_login( 'nobody' ) );
	}

	public function test_has_legacy_msum_caps_true_for_string_true(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 5, 'msum_has_caps', true )
			->andReturn( 'true' );

		$this->assertTrue( ( new UserRepository() )->has_legacy_msum_caps( 5 ) );
	}

	public function test_has_legacy_msum_caps_false_for_anything_else(): void {
		// MSUM (the legacy "Multisite User Manager" plugin this code
		// originally interoperated with) wrote the flag as the literal
		// string 'true'. Anything else — bool true, '1', '', missing —
		// must NOT skip the new-user sync.
		Functions\when( 'get_user_meta' )->justReturn( '' );
		$this->assertFalse( ( new UserRepository() )->has_legacy_msum_caps( 5 ) );

		Functions\when( 'get_user_meta' )->justReturn( true );
		$this->assertFalse( ( new UserRepository() )->has_legacy_msum_caps( 5 ) );

		Functions\when( 'get_user_meta' )->justReturn( '1' );
		$this->assertFalse( ( new UserRepository() )->has_legacy_msum_caps( 5 ) );
	}
}
