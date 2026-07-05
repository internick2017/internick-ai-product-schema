<?php
/**
 * Debug helper: print the URL WordPress computes for the enqueued JS, to catch
 * symlinked-plugin plugins_url() issues in the DDEV dev env.
 * Run: ddev exec wp eval-file tests/check-asset-url.php --path=wp
 *
 * @package ShopGraph
 */

WP_CLI::line( 'SHOPGRAPH_FILE: ' . ( defined( 'SHOPGRAPH_FILE' ) ? SHOPGRAPH_FILE : '(undefined)' ) );
if ( defined( 'SHOPGRAPH_FILE' ) ) {
	WP_CLI::line( 'plugins_url:    ' . plugins_url( 'assets/js/product-fields.js', SHOPGRAPH_FILE ) );
}
