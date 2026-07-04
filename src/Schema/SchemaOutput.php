<?php
/**
 * Decides how the Product JSON-LD reaches the page.
 *
 * - No SEO plugin: print a standalone <script type="application/ld+json"> on
 *   product pages via wp_footer.
 * - Yoast / Rank Math active: hook their schema graph, find the existing Product
 *   node and merge ShopGraph's AI attributes into it (never a duplicate Product).
 *   If the SEO plugin emits no Product node, append a complete one.
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
			add_action( 'wp_footer', array( $this, 'print_standalone' ) );
			return;
		}

		$active = $this->seo->active();
		if ( 'yoast' === $active ) {
			add_filter( 'wpseo_schema_graph', array( $this, 'filter_yoast_graph' ), 30, 2 );
			return;
		}

		if ( 'rankmath' === $active ) {
			add_filter( 'rank_math/json_ld', array( $this, 'filter_rankmath_jsonld' ), 99, 2 );
		}
	}

	/**
	 * Whether ShopGraph is responsible for printing a standalone Product node.
	 *
	 * True when schema output is enabled and either the user forced standalone
	 * mode or no supported SEO plugin is present.
	 *
	 * @return bool
	 */
	public function should_output_standalone(): bool {
		if ( ! Options::enabled( 'enable_schema' ) ) {
			return false;
		}
		if ( 'standalone' === Options::get( 'schema_mode', 'auto' ) ) {
			return true;
		}
		return null === $this->seo->active();
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
		echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
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
	 * Merge AI attributes into the first Product node of a schema graph, or
	 * append a complete Product node when the graph has none.
	 *
	 * Pure and key-preserving, so it is safe for both Yoast's list-style graph
	 * and Rank Math's associative graph, and easy to unit test.
	 *
	 * @param array<int|string, mixed> $graph   Schema graph pieces.
	 * @param \WC_Product              $product Current product.
	 * @return array<int|string, mixed>
	 */
	public function inject_into_graph( array $graph, \WC_Product $product ): array {
		$found_key = null;
		foreach ( $graph as $key => $piece ) {
			if ( is_array( $piece ) && $this->is_product_piece( $piece ) ) {
				$found_key = $key;
				break;
			}
		}

		if ( null !== $found_key ) {
			$ai = $this->builder->ai_attributes( $product );
			if ( array() !== $ai ) {
				$graph[ $found_key ] = array_merge( $graph[ $found_key ], $ai );
			}
			return $graph;
		}

		// SEO plugin emitted no Product node: add our complete one.
		$graph[] = $this->builder->build( $product );
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
