<?php
/**
 * PHPUnit bootstrap: load the WordPress test suite with WooCommerce + ShopGraph.
 *
 * @package ShopGraph
 */

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

// Tell wp-phpunit where our test config lives.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . dirname( __DIR__ ) . '/wp-tests-config.php' );
putenv( 'WP_TESTS_CONFIG_FILE_PATH=' . dirname( __DIR__ ) . '/wp-tests-config.php' );

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $_tests_dir . '/includes/functions.php';

// Load WooCommerce and this plugin as must-use for the test run.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';
		require dirname( __DIR__ ) . '/shopgraph.php';
	}
);

// Install WooCommerce tables/roles into the fresh test database.
tests_add_filter(
	'setup_theme',
	static function () {
		if ( ! defined( 'WC_REMOVE_ALL_DATA' ) ) {
			define( 'WC_REMOVE_ALL_DATA', true );
		}
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			wp_roles();
		}
	}
);

require $_tests_dir . '/includes/bootstrap.php';
