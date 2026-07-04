<?php
/**
 * AI product attribute fields for the classic product editor.
 *
 * Adds an "AI Attributes" tab to the WooCommerce product data metabox where a
 * shop owner can describe a product for AI shopping agents: a repeatable Q&A
 * list, plus compatible accessories and substitute products. All data is stored
 * on the product via the WooCommerce CRUD API (never direct post meta / SQL).
 *
 * @package ShopGraph
 */

namespace ShopGraph\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the AI Attributes product tab and reads/writes its meta via CRUD.
 */
class Fields {

	public const META_QA          = '_shopgraph_qa';
	public const META_ACCESSORIES = '_shopgraph_accessories';
	public const META_SUBSTITUTES = '_shopgraph_substitutes';

	/**
	 * Hook the tab, panel, and save routine into the product editor.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
	}

	/**
	 * Add the "AI Attributes" tab to the product data metabox.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array
	 */
	public function add_tab( array $tabs ): array {
		$tabs['shopgraph'] = array(
			'label'    => __( 'AI Attributes', 'shopgraph' ),
			'target'   => 'shopgraph_product_data',
			'priority' => 65,
			'class'    => array(),
		);
		return $tabs;
	}

	/**
	 * Render the AI Attributes panel (Q&A repeater + accessory/substitute pickers).
	 */
	public function render_panel(): void {
		global $post;

		$product = wc_get_product( $post->ID );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$qa           = self::get_qa( $product );
		$accessories  = self::get_accessories( $product );
		$substitutes  = self::get_substitutes( $product );

		echo '<div id="shopgraph_product_data" class="panel woocommerce_options_panel">';
		wp_nonce_field( 'shopgraph_save_fields', 'shopgraph_fields_nonce' );

		echo '<div class="options_group">';
		echo '<p class="form-field"><label>' . esc_html__( 'Product Q&amp;A', 'shopgraph' ) . '</label><span class="description">'
			. esc_html__( 'Common questions and answers about this product, surfaced to AI shopping agents.', 'shopgraph' )
			. '</span></p>';

		echo '<div class="shopgraph-qa-rows">';
		// Always render at least one blank row so the field is usable when empty.
		$rows = empty( $qa ) ? array( array( 'q' => '', 'a' => '' ) ) : $qa;
		foreach ( $rows as $row ) {
			$this->render_qa_row( (string) ( $row['q'] ?? '' ), (string) ( $row['a'] ?? '' ) );
		}
		echo '</div>';
		echo '<p class="form-field"><button type="button" class="button shopgraph-add-qa">'
			. esc_html__( 'Add Q&amp;A row', 'shopgraph' ) . '</button></p>';
		echo '</div>';

		echo '<div class="options_group">';
		$this->render_product_select(
			'shopgraph_accessories',
			__( 'Compatible accessories', 'shopgraph' ),
			__( 'Products that work together with this one (accessories or spare parts).', 'shopgraph' ),
			$accessories
		);
		$this->render_product_select(
			'shopgraph_substitutes',
			__( 'Substitute products', 'shopgraph' ),
			__( 'Similar products an agent can suggest when this one is unavailable.', 'shopgraph' ),
			$substitutes
		);
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render a single Q&A input row.
	 *
	 * @param string $q Question.
	 * @param string $a Answer.
	 */
	private function render_qa_row( string $q, string $a ): void {
		echo '<p class="form-field shopgraph-qa-row">';
		echo '<input type="text" name="shopgraph_q[]" class="short" style="width:32%" placeholder="'
			. esc_attr__( 'Question', 'shopgraph' ) . '" value="' . esc_attr( $q ) . '" /> ';
		echo '<input type="text" name="shopgraph_a[]" style="width:52%" placeholder="'
			. esc_attr__( 'Answer', 'shopgraph' ) . '" value="' . esc_attr( $a ) . '" /> ';
		echo '<button type="button" class="button shopgraph-remove-qa">' . esc_html__( 'Remove', 'shopgraph' ) . '</button>';
		echo '</p>';
	}

	/**
	 * Render a multi-select of products backed by the WooCommerce product search.
	 *
	 * @param string $name    Field name (also used for the input id).
	 * @param string $label   Field label.
	 * @param string $desc    Field description.
	 * @param int[]  $selected Currently selected product IDs.
	 */
	private function render_product_select( string $name, string $label, string $desc, array $selected ): void {
		echo '<p class="form-field"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		echo '<select class="wc-product-search" multiple="multiple" style="width:50%" id="' . esc_attr( $name ) . '" name="'
			. esc_attr( $name ) . '[]" data-placeholder="' . esc_attr__( 'Search for a product&hellip;', 'shopgraph' )
			. '" data-action="woocommerce_json_search_products_and_variations">';
		foreach ( $selected as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product instanceof \WC_Product ) {
				echo '<option value="' . esc_attr( (string) $product_id ) . '" selected="selected">'
					. esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select> <span class="description">' . esc_html( $desc ) . '</span></p>';
	}

	/**
	 * Persist the AI attributes onto the product object via CRUD.
	 *
	 * Runs on `woocommerce_admin_process_product_object`, which passes the
	 * product; WooCommerce calls `$product->save()` afterwards.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save( \WC_Product $product ): void {
		if (
			! isset( $_POST['shopgraph_fields_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shopgraph_fields_nonce'] ) ), 'shopgraph_save_fields' )
		) {
			return;
		}

		$questions = isset( $_POST['shopgraph_q'] ) ? (array) wp_unslash( $_POST['shopgraph_q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$answers   = isset( $_POST['shopgraph_a'] ) ? (array) wp_unslash( $_POST['shopgraph_a'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$qa = array();
		foreach ( $questions as $i => $question ) {
			$q = sanitize_text_field( $question );
			$a = sanitize_textarea_field( $answers[ $i ] ?? '' );
			if ( '' !== $q ) {
				$qa[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
		$product->update_meta_data( self::META_QA, $qa );

		$accessories = isset( $_POST['shopgraph_accessories'] ) ? (array) wp_unslash( $_POST['shopgraph_accessories'] ) : array();
		$substitutes = isset( $_POST['shopgraph_substitutes'] ) ? (array) wp_unslash( $_POST['shopgraph_substitutes'] ) : array();

		$product->update_meta_data( self::META_ACCESSORIES, array_values( array_filter( array_map( 'absint', $accessories ) ) ) );
		$product->update_meta_data( self::META_SUBSTITUTES, array_values( array_filter( array_map( 'absint', $substitutes ) ) ) );
	}

	/**
	 * Read the product Q&A list.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<int, array{q: string, a: string}>
	 */
	public static function get_qa( \WC_Product $product ): array {
		$raw = $product->get_meta( self::META_QA );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Read the compatible accessory product IDs.
	 *
	 * @param \WC_Product $product Product.
	 * @return int[]
	 */
	public static function get_accessories( \WC_Product $product ): array {
		return self::read_id_list( $product, self::META_ACCESSORIES );
	}

	/**
	 * Read the substitute product IDs.
	 *
	 * @param \WC_Product $product Product.
	 * @return int[]
	 */
	public static function get_substitutes( \WC_Product $product ): array {
		return self::read_id_list( $product, self::META_SUBSTITUTES );
	}

	/**
	 * Read a stored list of product IDs, tolerating the unset ('' ) case.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $key     Meta key.
	 * @return int[]
	 */
	private static function read_id_list( \WC_Product $product, string $key ): array {
		$raw = $product->get_meta( $key );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_map( 'absint', $raw ) );
	}
}
