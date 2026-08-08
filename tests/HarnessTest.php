<?php
/**
 * Proves the test harness loads WordPress + WooCommerce + the plugin,
 * and that products can be created/read via the WooCommerce CRUD API.
 *
 * @package Internick\AIProductSchema
 */

class HarnessTest extends WP_UnitTestCase {

	public function test_wp_woocommerce_and_plugin_are_loaded(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce should be loaded' );
		$this->assertTrue( class_exists( \Internick\AIProductSchema\Plugin::class ), 'Internick\AIProductSchema\\Plugin should autoload' );
	}

	public function test_can_create_and_read_a_product_via_crud(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Harness Product' );
		$product->set_regular_price( '9.99' );
		$product->save();

		$this->assertGreaterThan( 0, $product->get_id() );
		$this->assertSame( '9.99', wc_get_product( $product->get_id() )->get_regular_price() );
	}
}
