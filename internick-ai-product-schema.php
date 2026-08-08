<?php
/**
 * Plugin Name:       Internick - AI Product Schema
 * Description:        Make your WooCommerce products discoverable and purchasable by AI shopping agents (complete Product schema, AI attributes, llms.txt).
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Nick Granados
 * Author URI:        https://nickgranados.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       internick-ai-product-schema
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 *
 * @package Internick\AIProductSchema
 */

defined( 'ABSPATH' ) || exit;

define( 'INTERNICK_AIPS_FILE', __FILE__ );
define( 'INTERNICK_AIPS_VERSION', '0.1.0' );

/**
 * Autoload AI Product Schema classes (PSR-4). The plugin has no runtime Composer
 * dependencies, so a tiny SPL autoloader keeps the shipped plugin free of a
 * vendor/ directory.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Internick\\AIProductSchema\\';
		$len    = strlen( $prefix );
		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

/**
 * Register the /llms.txt rewrite rule on activation, then flush so the route
 * resolves immediately. Flush again on deactivation to clean up.
 */
register_activation_hook(
	__FILE__,
	static function () {
		( new \Internick\AIProductSchema\Llms\LlmsTxt() )->add_rewrite_rule();
		flush_rewrite_rules();
	}
);
register_deactivation_hook(
	__FILE__,
	static function () {
		// The rule was re-registered on this request's init, so remove it from
		// the compiled set first or the flush would just write it back.
		global $wp_rewrite;
		unset( $wp_rewrite->extra_rules_top['^llms\.txt$'] );
		flush_rewrite_rules();
	}
);

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 * https://developer.woocommerce.com/docs/features/high-performance-order-storage/
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded, only if WooCommerce is active.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'AI Product Schema requires WooCommerce to be installed and active.', 'internick-ai-product-schema' ) . '</p></div>';
				}
			);
			return;
		}
		\Internick\AIProductSchema\Plugin::instance()->boot( __FILE__ );
	}
);
