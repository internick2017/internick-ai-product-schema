<?php
/**
 * AI product attribute fields: save + read via the WooCommerce CRUD API.
 *
 * @package Internick\AIProductSchema
 */

use Internick\AIProductSchema\ProductFields\Fields;

class FieldsTest extends WP_UnitTestCase {

	public function test_saves_and_reads_ai_attributes_via_crud(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'AI Attr Product' );
		$product->update_meta_data( '_internick_aips_qa', array( array( 'q' => 'Waterproof?', 'a' => 'Yes, IP68.' ) ) );
		$product->update_meta_data( '_internick_aips_accessories', array( 12, 34 ) );
		$product->update_meta_data( '_internick_aips_substitutes', array( 123, 456 ) );
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

	private function make_saved_product(): WC_Product {
		$product = new WC_Product_Simple();
		$product->set_name( 'Save Target' );
		$product->save();
		return $product;
	}

	private function post_payload(): array {
		return array(
			'internick_aips_fields_nonce' => wp_create_nonce( 'internick_aips_save_fields' ),
			'internick_aips_q'            => array( 'Waterproof?', '', 'Warranty?' ),
			'internick_aips_a'            => array( 'Yes.', 'Orphan answer', '2 years' ),
			'internick_aips_accessories'  => array( '12', 'abc', '0', '34' ),
			'internick_aips_substitutes'  => array( '56' ),
		);
	}

	public function test_save_persists_sanitized_fields_from_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product = $this->make_saved_product();
		$_POST   = $this->post_payload();

		( new Fields() )->save( $product );
		$product->save();
		$reloaded = wc_get_product( $product->get_id() );

		// Empty-question row is dropped (its orphan answer is discarded too);
		// the following pair does NOT shift onto the wrong answer.
		$this->assertSame(
			array(
				array( 'q' => 'Waterproof?', 'a' => 'Yes.' ),
				array( 'q' => 'Warranty?', 'a' => '2 years' ),
			),
			Fields::get_qa( $reloaded )
		);

		// Non-numeric and zero IDs are absint-ed away.
		$this->assertSame( array( 12, 34 ), Fields::get_accessories( $reloaded ) );
		$this->assertSame( array( 56 ), Fields::get_substitutes( $reloaded ) );

		$_POST = array();
	}

	public function test_save_ignores_request_without_valid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$product = $this->make_saved_product();

		$_POST                           = $this->post_payload();
		$_POST['internick_aips_fields_nonce'] = 'invalid';

		( new Fields() )->save( $product );
		$product->save();

		$this->assertSame( array(), Fields::get_qa( wc_get_product( $product->get_id() ) ) );

		$_POST = array();
	}

	public function test_save_requires_edit_product_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$product = $this->make_saved_product();
		$_POST   = $this->post_payload();

		( new Fields() )->save( $product );
		$product->save();

		$this->assertSame( array(), Fields::get_qa( wc_get_product( $product->get_id() ) ) );

		$_POST = array();
		wp_set_current_user( 0 );
	}
}
