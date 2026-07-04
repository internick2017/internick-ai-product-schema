<?php
/**
 * Live verification helper: confirms ShopGraph hooks are registered and
 * produce output on the running site. Run:
 *   ddev exec wp eval-file tests/verify-live.php --path=wp
 *
 * @package ShopGraph
 */

WP_CLI::line( 'robots_txt filter registered at priority: ' . var_export( has_filter( 'robots_txt' ), true ) );
WP_CLI::line( '--- robots.txt output (via do_robots filter) ---' );
WP_CLI::line( apply_filters( 'robots_txt', "User-agent: *\nDisallow:\n", true ) );
WP_CLI::line( '--- end robots.txt ---' );

WP_CLI::line( '' );
WP_CLI::line( 'wp_footer has actions: ' . var_export( has_action( 'wp_footer' ), true ) );
WP_CLI::line( 'template_redirect has actions: ' . var_export( has_action( 'template_redirect' ), true ) );
