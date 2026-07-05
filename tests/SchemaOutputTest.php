<?php
/**
 * Schema output + coexistence with WooCommerce Core / Yoast / Rank Math:
 * merge-only, never a duplicate Product node.
 *
 * @package ShopGraph
 */

use ShopGraph\Compat\SeoPlugins;
use ShopGraph\Schema\ProductSchema;
use ShopGraph\Schema\SchemaOutput;

class SchemaOutputTest extends WP_UnitTestCase {

	private function make_output(): SchemaOutput {
		return new SchemaOutput( new ProductSchema(), new SeoPlugins() );
	}

	private function make_product_with_ai_attrs(): WC_Product {
		$substitute = new WC_Product_Simple();
		$substitute->set_name( 'Alternative' );
		$substitute->save();

		$product = new WC_Product_Simple();
		$product->set_name( 'Main Product' );
		$product->update_meta_data( '_shopgraph_qa', array( array( 'q' => 'Warranty?', 'a' => '2 years' ) ) );
		$product->update_meta_data( '_shopgraph_substitutes', array( $substitute->get_id() ) );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	public function test_auto_mode_does_not_output_standalone(): void {
		// In auto mode ShopGraph enhances the existing Product node (WooCommerce
		// Core / SEO plugin) instead of printing a second, standalone one.
		$this->assertNull( ( new SeoPlugins() )->active() );
		$this->assertFalse( $this->make_output()->should_output_standalone() );
	}

	public function test_merges_ai_attributes_into_woocommerce_product_markup(): void {
		$product = $this->make_product_with_ai_attrs();

		$markup = array(
			'@type' => 'Product',
			'name'  => 'Main Product',
		);
		$result = $this->make_output()->filter_wc_product( $markup, $product );

		// AI attributes merged into WooCommerce's own node (no second node).
		$this->assertSame( 'FAQPage', $result['subjectOf']['@type'] );
		$this->assertSame( 'Alternative', $result['isSimilarTo'][0]['name'] );
		$this->assertSame( 'Main Product', $result['name'] );
	}

	public function test_merges_ai_attributes_into_existing_product_node(): void {
		$product = $this->make_product_with_ai_attrs();

		$graph = array(
			array(
				'@type' => 'WebPage',
				'name'  => 'A page',
			),
			array(
				'@type' => 'Product',
				'name'  => 'Main Product',
			),
		);

		$result = $this->make_output()->inject_into_graph( $graph, $product );

		// Exactly one Product node (no duplicate).
		$product_nodes = array_filter(
			$result,
			static function ( $piece ) {
				return isset( $piece['@type'] ) && 'Product' === $piece['@type'];
			}
		);
		$this->assertCount( 1, $product_nodes );

		// AI attributes merged into the existing node.
		$this->assertSame( 'FAQPage', $result[1]['subjectOf']['@type'] );
		$this->assertSame( 'Alternative', $result[1]['isSimilarTo'][0]['name'] );
	}

	public function test_graph_without_product_node_is_left_unchanged(): void {
		// MERGE-ONLY: free Yoast (no WooCommerce SEO addon) emits a graph with no
		// Product piece while WooCommerce Core still prints its own (enhanced)
		// node. Appending here would create a duplicate Product on the page.
		$product = $this->make_product_with_ai_attrs();

		$graph  = array(
			array(
				'@type' => 'WebPage',
				'name'  => 'A page',
			),
		);
		$result = $this->make_output()->inject_into_graph( $graph, $product );

		$this->assertSame( $graph, $result );
	}

	public function test_merges_into_rankmath_style_associative_graph(): void {
		$product = $this->make_product_with_ai_attrs();

		$graph = array(
			'breadcrumb'   => array( '@type' => 'BreadcrumbList' ),
			'richSnippet'  => array(
				'@type' => 'Product',
				'name'  => 'Main Product',
			),
		);

		$result = $this->make_output()->inject_into_graph( $graph, $product );

		// String keys preserved, AI attributes merged into the Product piece.
		$this->assertSame( array( 'breadcrumb', 'richSnippet' ), array_keys( $result ) );
		$this->assertSame( 'FAQPage', $result['richSnippet']['subjectOf']['@type'] );
	}

	public function test_recognizes_array_type_product_pieces(): void {
		$product = $this->make_product_with_ai_attrs();

		$graph = array(
			array(
				'@type' => array( 'Product', 'IndividualProduct' ),
				'name'  => 'Main Product',
			),
		);

		$result = $this->make_output()->inject_into_graph( $graph, $product );

		$this->assertSame( 'FAQPage', $result[0]['subjectOf']['@type'] );
	}

	public function test_strip_product_pieces_removes_products_and_reindexes_lists(): void {
		$graph = array(
			array( '@type' => 'WebPage', 'name' => 'A page' ),
			array( '@type' => 'Product', 'name' => 'P1' ),
			array( '@type' => 'BreadcrumbList' ),
		);

		$result = $this->make_output()->strip_product_pieces( $graph );

		$this->assertCount( 2, $result );
		// Re-indexed: still a JSON list, no numeric gaps.
		$this->assertSame( array( 0, 1 ), array_keys( $result ) );
		$this->assertSame( 'WebPage', $result[0]['@type'] );
		$this->assertSame( 'BreadcrumbList', $result[1]['@type'] );
	}

	public function test_strip_product_pieces_preserves_associative_keys(): void {
		$graph = array(
			'breadcrumb'  => array( '@type' => 'BreadcrumbList' ),
			'richSnippet' => array( '@type' => 'Product', 'name' => 'P1' ),
		);

		$result = $this->make_output()->strip_product_pieces( $graph );

		$this->assertSame( array( 'breadcrumb' ), array_keys( $result ) );
	}
}
