<?php
/**
 * AI product attribute fields: save + read via the WooCommerce CRUD API.
 *
 * @package ShopGraph
 */

use ShopGraph\ProductFields\Fields;

class FieldsTest extends WP_UnitTestCase {

	public function test_saves_and_reads_ai_attributes_via_crud(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'AI Attr Product' );
		$product->update_meta_data( '_shopgraph_qa', array( array( 'q' => 'Waterproof?', 'a' => 'Yes, IP68.' ) ) );
		$product->update_meta_data( '_shopgraph_accessories', array( 12, 34 ) );
		$product->update_meta_data( '_shopgraph_substitutes', array( 123, 456 ) );
		$product->save();

		$reloaded = wc_get_product( $product->get_id() );

		$this->assertSame( 'Waterproof?', Fields::get_qa( $reloaded )[0]['q'] );
		$this->assertSame( 'Yes, IP68.', Fields::get_qa( $reloaded )[0]['a'] );
		$this->assertSame( array( 12, 34 ), Fields::get_accessories( $reloaded ) );
		$this->assertSame( array( 123, 456 ), Fields::get_substitutes( $reloaded ) );
	}

	public function test_getters_default_to_empty_arrays_when_unset(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'No Attrs Product' );
		$product->save();

		$reloaded = wc_get_product( $product->get_id() );

		$this->assertSame( array(), Fields::get_qa( $reloaded ) );
		$this->assertSame( array(), Fields::get_accessories( $reloaded ) );
		$this->assertSame( array(), Fields::get_substitutes( $reloaded ) );
	}
}
