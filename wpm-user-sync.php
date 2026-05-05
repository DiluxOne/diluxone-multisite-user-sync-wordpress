<?php
/**
 * Plugin Name: WPM User Sync
 * Plugin URI: https://pablodiloreto.com/wpm-user-sync/
 * Description: Optimized for Microsoft Azure and Azure App Service (compatible with any host). Configures & automates user synchronization between WordPress sites in a multi-site setup.
 * Author: Pablo Ariel Di Loreto
 * Version: 1.4
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * Author URI: https://pablodiloreto.com/wpm-user-sync/
 * Text Domain: wpm-user-sync
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: true
 *
 * @package WPMUS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';
require_once __DIR__ . '/legacy-deprecated.php';

( new \WPMUS\Plugin( __FILE__ ) )->register();
