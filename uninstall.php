<?php
/**
 * ShopGraph uninstall: remove everything the plugin stored.
 *
 * Runs when the user deletes the plugin from the Plugins screen.
 *
 * @package ShopGraph
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'shopgraph_settings' );
delete_transient( 'shopgraph_llms_txt' );

// AI product attributes stored as product meta.
delete_post_meta_by_key( '_shopgraph_qa' );
delete_post_meta_by_key( '_shopgraph_accessories' );
delete_post_meta_by_key( '_shopgraph_substitutes' );
