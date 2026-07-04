<?php
/**
 * Schema output + coexistence with Yoast / Rank Math (no duplicate Product).
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

	public function test_standalone_when_no_seo_plugin_active(): void {
		$this->assertNull( ( new SeoPlugins() )->active() );
		$this->assertTrue( $this->make_output()->should_output_standalone() );
	}

	public function test_merges_ai_attributes_into_existing_product_node(): void {
		$substitute = new WC_Product_Simple();
		$substitute->set_name( 'Alternative' );
		$substitute->save();

		$product = new WC_Product_Simple();
		$product->set_name( 'Main Product' );
		$product->update_meta_data( '_shopgraph_qa', array( array( 'q' => 'Warranty?', 'a' => '2 years' ) ) );
		$product->update_meta_data( '_shopgraph_substitutes', array( $substitute->get_id() ) );
		$product->save();
		$product = wc_get_product( $product->get_id() );

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

	public function test_appends_product_node_when_seo_graph_has_none(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Lonely Product' );
		$product->set_regular_price( '10' );
		$product->save();
		$product = wc_get_product( $product->get_id() );

		$graph  = array( array( '@type' => 'WebPage', 'name' => 'A page' ) );
		$result = $this->make_output()->inject_into_graph( $graph, $product );

		$product_nodes = array_values(
			array_filter(
				$result,
				static function ( $piece ) {
					return isset( $piece['@type'] ) && 'Product' === $piece['@type'];
				}
			)
		);
		$this->assertCount( 1, $product_nodes );
		$this->assertSame( 'Lonely Product', $product_nodes[0]['name'] );
	}
}
