<?php
/**
 * WordPress test-suite config for AI Product Schema (DDEV local).
 * Reuses the DDEV `db` database but with a dedicated `wptests_` table prefix,
 * so the test tables stay isolated from the dev site's `wp_` tables (the test
 * suite only drops/creates tables with the configured prefix).
 *
 * @package Internick\AIProductSchema
 */

define( 'ABSPATH', '/var/www/html/wp/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME', 'db' );
define( 'DB_USER', 'db' );
define( 'DB_PASSWORD', 'db' );
define( 'DB_HOST', 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'shopgraph.ddev.site' );
define( 'WP_TESTS_EMAIL', 'admin@example.com' );
define( 'WP_TESTS_TITLE', 'AI Product Schema Tests' );
define( 'WP_PHP_BINARY', 'php' );
