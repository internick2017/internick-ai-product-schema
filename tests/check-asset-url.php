<?php
/**
 * Debug helper: print the URL WordPress computes for the enqueued JS, to catch
 * symlinked-plugin plugins_url() issues in the DDEV dev env.
 * Run: ddev exec wp eval-file tests/check-asset-url.php --path=wp
 *
 * @package Internick\AIProductSchema
 */

WP_CLI::line( 'INTERNICK_AIPS_FILE: ' . ( defined( 'INTERNICK_AIPS_FILE' ) ? INTERNICK_AIPS_FILE : '(undefined)' ) );
if ( defined( 'INTERNICK_AIPS_FILE' ) ) {
	WP_CLI::line( 'plugins_url:    ' . plugins_url( 'assets/js/product-fields.js', INTERNICK_AIPS_FILE ) );
}
