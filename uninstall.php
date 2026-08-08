<?php
/**
 * AI Product Schema uninstall: remove everything the plugin stored.
 *
 * Runs when the user deletes the plugin from the Plugins screen.
 *
 * @package Internick\AIProductSchema
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'internick_aips_settings' );
delete_transient( 'internick_aips_llms_txt' );

// AI product attributes stored as product meta.
delete_post_meta_by_key( '_internick_aips_qa' );
delete_post_meta_by_key( '_internick_aips_accessories' );
delete_post_meta_by_key( '_internick_aips_substitutes' );
