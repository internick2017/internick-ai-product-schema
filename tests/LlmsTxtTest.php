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
}
