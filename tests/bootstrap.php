<?php
/**
 * PHPUnit bootstrap for the unit-test suite.
 *
 * Loads Composer's autoloader (which resolves `WPMUS\` via PSR-4 from
 * src/ and `Tests\` from tests/) and defines a minimal stand-in for
 * `ABSPATH` so the plugin's `if ( ! defined( 'ABSPATH' ) )` guards
 * don't `exit`. Brain Monkey is loaded per-test through the base
 * {@see \Tests\TestCase} class.
 *
 * For types referenced by the plugin's repositories (`WP_User`,
 * `WP_Site`) we register minimal stub classes so the production code's
 * return-type declarations do not require a full WordPress runtime in
 * the unit suite.
 *
 * @package WPMUS\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/abspath-stub/' );
}

if ( ! defined( 'WPMUS_VERSION' ) ) {
	define( 'WPMUS_VERSION', '0.0.0-test' );
}

require_once __DIR__ . '/Stubs/wp-classes.php';
