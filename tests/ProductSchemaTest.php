<?php
/**
 * Product JSON-LD builder: schema.org Product from WooCommerce CRUD + AI attributes.
 *
 * @package Internick\AIProductSchema
 */

use Internick\AIProductSchema\Schema\ProductSchema;

class ProductSchemaTest extends WP_UnitTestCase {

	public function test_builds_core_product_and_offer(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Waterproof Speaker' );
		$product->set_regular_price( '19.99' );
		$product->set_sku( 'SKU-1' );
		$product->save();

		$schema = ( new ProductSchema() )->build( wc_get_product( $product->get_id() ) );

		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertSame( 'Product', $schema['@type'] );
		$this->assertSame( 'Waterproof Speaker', $schema['name'] );
		$this->assertSame( 'SKU-1', $schema['sku'] );
		$this->assertSame( 'Offer', $schema['offers']['@type'] );
		$this->assertSame( '19.99', $schema['offers']['price'] );
		$this->assertSame( 'https://schema.org/InStock', $schema['offers']['availability'] );
	}

	public function test_maps_ai_attributes_to_schema_properties(): void {
		$accessory  = new WC_Product_Simple();
		$accessory->set_name( 'Protective Case' );
		$accessory->save();

		$substitute = new WC_Product_Simple();
		$substitute->set_name( 'Rival Phone' );
		$substitute->save();

		$product = new WC_Product_Simple();
		$product->set_name( 'Phone' );
		$product->set_regular_price( '499' );
		$product->update_meta_data( '_internick_aips_qa', array( array( 'q' => 'Color?', 'a' => 'Black' ) ) );
		$product->update_meta_data( '_internick_aips_accessories', array( $accessory->get_id() ) );
		$product->update_meta_data( '_internick_aips_substitutes', array( $substitute->get_id() ) );
		$product->save();

		$schema = ( new ProductSchema() )->build( wc_get_product( $product->get_id() ) );

		// Q&A → subjectOf / FAQPage.
		$this->assertSame( 'FAQPage', $schema['subjectOf']['@type'] );
		$this->assertSame( 'Color?', $schema['subjectOf']['mainEntity'][0]['name'] );
		$this->assertSame( 'Black', $schema['subjectOf']['mainEntity'][0]['acceptedAnswer']['text'] );

		// Substitutes → isSimilarTo ; accessories → isRelatedTo.
		$this->assertSame( 'Rival Phone', $schema['isSimilarTo'][0]['name'] );
		$this->assertSame( 'Protective Case', $schema['isRelatedTo'][0]['name'] );
	}

	public function test_aggregate_rating_omitted_without_reviews(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Unrated' );
		$product->set_regular_price( '5' );
		$product->save();

		$schema = ( new ProductSchema() )->build( wc_get_product( $product->get_id() ) );

		$this->assertArrayNotHasKey( 'aggregateRating', $schema );
	}
}
