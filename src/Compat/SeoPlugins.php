<?php
/**
 * Detects an active SEO plugin that already emits Product schema, so ShopGraph
 * can coexist with it instead of printing a duplicate Product node.
 *
 * @package ShopGraph
 */

namespace ShopGraph\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin detection.
 */
class SeoPlugins {

	/**
	 * Which supported SEO plugin is active.
	 *
	 * @return string|null 'yoast', 'rankmath', or null when none is active.
	 */
	public function active(): ?string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' ) ) {
			return 'rankmath';
		}
		return null;
	}
}
