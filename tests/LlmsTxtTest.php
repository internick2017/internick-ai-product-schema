<?php
/**
 * /llms.txt builder: store summary + product index for AI crawlers.
 *
 * @package ShopGraph
 */

use ShopGraph\Llms\LlmsTxt;

class LlmsTxtTest extends WP_UnitTestCase {

	public function test_render_includes_site_name_and_products(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Indexable Widget' );
		$product->set_regular_price( '9.99' );
		$product->set_status( 'publish' );
		$product->save();

		$out = ( new LlmsTxt() )->render();

		$this->assertStringContainsString( '# ' . get_bloginfo( 'name' ), $out );
		$this->assertStringContainsString( '## Products', $out );
		$this->assertStringContainsString( 'Indexable Widget', $out );
		$this->assertStringContainsString( get_permalink( $product->get_id() ), $out );
	}

	public function test_render_omits_non_published_products(): void {
		$draft = new WC_Product_Simple();
		$draft->set_name( 'Secret Draft Product' );
		$draft->set_status( 'draft' );
		$draft->save();

		$out = ( new LlmsTxt() )->render();

		$this->assertStringNotContainsString( 'Secret Draft Product', $out );
	}

	public function test_render_escapes_markdown_delimiters_in_names(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Widget [Pro] (2nd gen)' );
		$product->set_regular_price( '5' );
		$product->set_status( 'publish' );
		$product->save();

		$out = ( new LlmsTxt() )->render();

		$this->assertStringContainsString( '- [Widget \[Pro\] (2nd gen)](', $out );
	}

	public function test_cache_invalidation_hooks_are_registered(): void {
		// Plugin::boot() ran in the test bootstrap, so register() has hooked these.
		$callback = array( LlmsTxt::class, 'clear_cache' );
		$this->assertNotFalse( has_action( 'woocommerce_new_product', $callback ) );
		$this->assertNotFalse( has_action( 'woocommerce_update_product', $callback ) );
		$this->assertNotFalse( has_action( 'woocommerce_delete_product', $callback ) );
		$this->assertNotFalse( has_action( 'woocommerce_trash_product', $callback ) );
	}

	public function test_product_update_clears_cached_body(): void {
		set_transient( 'shopgraph_llms_txt', 'stale body', HOUR_IN_SECONDS );

		$product = new WC_Product_Simple();
		$product->set_name( 'Cache Buster' );
		$product->set_regular_price( '1' );
		$product->set_status( 'publish' );
		$product->save(); // fires woocommerce_new_product / update hooks

		$this->assertFalse( get_transient( 'shopgraph_llms_txt' ) );
	}

	public function test_settings_change_clears_cached_body(): void {
		// Seed the option first so the change below goes through update_option.
		update_option( 'shopgraph_settings', array( 'enable_llms' => 'yes' ) );
		set_transient( 'shopgraph_llms_txt', 'stale body', HOUR_IN_SECONDS );

		update_option( 'shopgraph_settings', array( 'enable_llms' => 'no' ) );

		$this->assertFalse( get_transient( 'shopgraph_llms_txt' ) );

		delete_option( 'shopgraph_settings' );
	}
}
