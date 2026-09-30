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

	public function test_removed_blog_ids_normalises_the_stored_list(): void {
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 5, UserRepository::META_REMOVED_FROM, true )
			->andReturn( array( '3', 3, 0, 'x', 7 ) );

		$this->assertSame( array( 3, 7 ), ( new UserRepository() )->removed_blog_ids( 5 ) );
	}

	public function test_removed_blog_ids_is_empty_without_a_record(): void {
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$this->assertSame( array(), ( new UserRepository() )->removed_blog_ids( 5 ) );
	}

	public function test_record_removal_appends_once(): void {
		Functions\when( 'get_user_meta' )->justReturn( array( 2 ) );
		Functions\expect( 'update_user_meta' )->once()->with( 5, UserRepository::META_REMOVED_FROM, array( 2, 3 ) );

		( new UserRepository() )->record_removal( 5, 3 );
		( new UserRepository() )->record_removal( 5, 2 );
	}

	public function test_forget_removal_deletes_the_record_when_it_empties(): void {
		Functions\when( 'get_user_meta' )->justReturn( array( 3 ) );
		Functions\expect( 'delete_user_meta' )->once()->with( 5, UserRepository::META_REMOVED_FROM );

		( new UserRepository() )->forget_removal( 5, 3 );
	}
}
