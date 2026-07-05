<?php
/**
 * Decides how the Product JSON-LD reaches the page.
 *
 * - Auto mode: WooCommerce Core always emits Product structured data, so the AI
 *   attributes are merged into WC's node; when Yoast / Rank Math are active
 *   their graph's Product node (if any) is enhanced too. Nothing is ever
 *   appended, so there is never a duplicate Product node.
 * - Standalone mode (forced via settings): suppress WC's node AND strip any
 *   Product piece from the SEO plugin's graph, then print ShopGraph's own
 *   complete node on wp_footer.
 *
 * @package ShopGraph
 */

namespace ShopGraph\Schema;

use ShopGraph\Compat\SeoPlugins;
use ShopGraph\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs / merges the Product JSON-LD according to the SEO environment.
 */
class SchemaOutput {

	private ProductSchema $builder;
	private SeoPlugins $seo;

	public function __construct( ProductSchema $builder, SeoPlugins $seo ) {
		$this->builder = $builder;
		$this->seo     = $seo;
	}

	/**
	 * Register output hooks based on the active SEO plugin.
	 */
	public function register(): void {
		if ( ! Options::enabled( 'enable_schema' ) ) {
			return;
		}

		if ( $this->should_output_standalone() ) {
			// Forced standalone: suppress WooCommerce's own Product schema, strip
			// any Product piece from an active SEO plugin's graph, and print
			// ShopGraph's complete node instead (no duplicate Product).
			add_filter( 'woocommerce_structured_data_product', '__return_empty_array', 99 );
			add_filter( 'wpseo_schema_graph', array( $this, 'filter_strip_products' ), 30, 2 );
			add_filter( 'rank_math/json_ld', array( $this, 'filter_strip_products' ), 99, 2 );
			add_action( 'wp_footer', array( $this, 'print_standalone' ) );
			return;
		}

		// Auto mode: enhance whatever Product node is emitted, never add a second.
		// WooCommerce Core always emits Product structured data; merge into it.
		add_filter( 'woocommerce_structured_data_product', array( $this, 'filter_wc_product' ), 20, 2 );

		// When an SEO plugin takes over (and disables WC's schema), enhance its
		// Product node too, so AI attributes are present whichever one is used.
		$active = $this->seo->active();
		if ( 'yoast' === $active ) {
			add_filter( 'wpseo_schema_graph', array( $this, 'filter_yoast_graph' ), 30, 2 );
		} elseif ( 'rankmath' === $active ) {
			add_filter( 'rank_math/json_ld', array( $this, 'filter_rankmath_jsonld' ), 99, 2 );
		}
	}

	/**
	 * Whether ShopGraph should print its own standalone Product node.
	 *
	 * Only in forced "standalone" mode. In auto mode ShopGraph enhances the
	 * existing Product node (WooCommerce Core, Yoast, or Rank Math) instead of
	 * printing a second one.
	 *
	 * @return bool
	 */
	public function should_output_standalone(): bool {
		return Options::enabled( 'enable_schema' ) && 'standalone' === Options::get( 'schema_mode', 'auto' );
	}

	/**
	 * Merge AI attributes into WooCommerce Core's own Product structured data.
	 *
	 * @param mixed $markup  WooCommerce Product markup (array).
	 * @param mixed $product The WC_Product being described.
	 * @return mixed
	 */
	public function filter_wc_product( $markup, $product = null ) {
		if ( ! is_array( $markup ) || ! $product instanceof \WC_Product ) {
			return $markup;
		}
		return array_merge( $markup, $this->builder->ai_attributes( $product ) );
	}

	/**
	 * Print a standalone Product JSON-LD script on single product pages.
	 */
	public function print_standalone(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$schema = $this->builder->build( $product );
		// JSON_HEX_TAG hardens against any future "</script>" in values.
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * Standalone-mode filter for SEO plugin graphs: remove their Product pieces
	 * so ShopGraph's standalone node is the only Product on the page.
	 *
	 * @param mixed $graph  Schema graph (array of pieces).
	 * @param mixed $unused Filter's second arg (Yoast context / Rank Math JsonLD).
	 * @return mixed
	 */
	public function filter_strip_products( $graph, $unused = null ) {
		if ( ! is_array( $graph ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $graph;
		}
		return $this->strip_product_pieces( $graph );
	}

	/**
	 * Remove every Product piece from a schema graph (pure).
	 *
	 * List-style graphs (Yoast) are re-indexed so they still JSON-encode as an
	 * array; associative graphs (Rank Math) keep their string keys.
	 *
	 * @param array<int|string, mixed> $graph Schema graph pieces.
	 * @return array<int|string, mixed>
	 */
	public function strip_product_pieces( array $graph ): array {
		$was_list = array_keys( $graph ) === range( 0, count( $graph ) - 1 );

		foreach ( $graph as $key => $piece ) {
			if ( is_array( $piece ) && $this->is_product_piece( $piece ) ) {
				unset( $graph[ $key ] );
			}
		}

		return $was_list ? array_values( $graph ) : $graph;
	}

	/**
	 * Yoast graph filter: merge AI attributes into the graph's Product node.
	 *
	 * @param mixed $graph   Schema graph (array of pieces).
	 * @param mixed $context Yoast context object (unused).
	 * @return mixed
	 */
	public function filter_yoast_graph( $graph, $context = null ) {
		return $this->maybe_inject( $graph );
	}

	/**
	 * Rank Math json_ld filter: merge AI attributes into its Product node.
	 *
	 * @param mixed $data   JSON-LD data (array of pieces).
	 * @param mixed $jsonld Rank Math JsonLD instance (unused).
	 * @return mixed
	 */
	public function filter_rankmath_jsonld( $data, $jsonld = null ) {
		return $this->maybe_inject( $data );
	}

	/**
	 * Guarded entry used by both SEO filters: only act on single product pages.
	 *
	 * @param mixed $graph Schema graph.
	 * @return mixed
	 */
	private function maybe_inject( $graph ) {
		if ( ! is_array( $graph ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $graph;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof \WC_Product ) {
			return $graph;
		}
		return $this->inject_into_graph( $graph, $product );
	}

	/**
	 * Merge AI attributes into the first Product node of a schema graph.
	 *
	 * MERGE-ONLY by design: if the SEO plugin's graph has no Product node (e.g.
	 * free Yoast without the WooCommerce SEO addon), the graph is returned
	 * unchanged — WooCommerce Core's own Product node (already enhanced via the
	 * woocommerce_structured_data_product filter) covers the page, and appending
	 * one here would create the duplicate this plugin promises to avoid.
	 *
	 * Pure and key-preserving, so it is safe for both Yoast's list-style graph
	 * and Rank Math's associative graph, and easy to unit test.
	 *
	 * @param array<int|string, mixed> $graph   Schema graph pieces.
	 * @param \WC_Product              $product Current product.
	 * @return array<int|string, mixed>
	 */
	public function inject_into_graph( array $graph, \WC_Product $product ): array {
		foreach ( $graph as $key => $piece ) {
			if ( is_array( $piece ) && $this->is_product_piece( $piece ) ) {
				$ai = $this->builder->ai_attributes( $product );
				if ( array() !== $ai ) {
					$graph[ $key ] = array_merge( $graph[ $key ], $ai );
				}
				return $graph;
			}
		}

		return $graph;
	}

	/**
	 * Whether a schema piece is a Product node (handles array @type too).
	 *
	 * @param array<string, mixed> $piece Schema piece.
	 * @return bool
	 */
	private function is_product_piece( array $piece ): bool {
		if ( ! isset( $piece['@type'] ) ) {
			return false;
		}
		if ( is_array( $piece['@type'] ) ) {
			return in_array( 'Product', $piece['@type'], true );
		}
		return 'Product' === $piece['@type'];
	}
}
