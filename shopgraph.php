<?php
/**
 * Plugin Name:       ShopGraph for WooCommerce
 * Description:        Make your WooCommerce products discoverable and purchasable by AI shopping agents (complete Product schema, AI attributes, llms.txt).
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Nick Granados
 * Author URI:        https://nickgranados.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shopgraph
 * WC requires at least: 8.0
 *
 * @package ShopGraph
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'ShopGraph requires WooCommerce to be installed and active.', 'shopgraph' ) . '</p></div>';
				}
			);
			return;
		}
		\ShopGraph\Plugin::instance()->boot( __FILE__ );
	}
);
