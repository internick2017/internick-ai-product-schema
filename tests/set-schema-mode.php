<?php
/**
 * Test helper: set AI Product Schema schema_mode. Pass the mode after --.
 *   ddev exec wp eval-file tests/set-schema-mode.php standalone --path=wp
 *
 * @package Internick\AIProductSchema
 */

$mode  = isset( $args[0] ) ? $args[0] : 'auto';
$valid = array( 'auto', 'standalone' );
if ( ! in_array( $mode, $valid, true ) ) {
	WP_CLI::error( 'mode must be auto|standalone' );
}

$opts                = (array) get_option( 'internick_aips_settings', array() );
$opts['schema_mode'] = $mode;
update_option( 'internick_aips_settings', $opts );

WP_CLI::success( 'schema_mode = ' . $mode );
