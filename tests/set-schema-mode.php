<?php
/**
 * Test helper: set ShopGraph schema_mode. Pass the mode after --.
 *   ddev exec wp eval-file tests/set-schema-mode.php standalone --path=wp
 *
 * @package ShopGraph
 */

$mode  = isset( $args[0] ) ? $args[0] : 'auto';
$valid = array( 'auto', 'standalone' );
if ( ! in_array( $mode, $valid, true ) ) {
	WP_CLI::error( 'mode must be auto|standalone' );
}

$opts                = (array) get_option( 'shopgraph_settings', array() );
$opts['schema_mode'] = $mode;
update_option( 'shopgraph_settings', $opts );

WP_CLI::success( 'schema_mode = ' . $mode );
