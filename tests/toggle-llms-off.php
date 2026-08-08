<?php
/**
 * Test helper: disable the llms.txt feature via the settings option.
 * Run: ddev exec wp eval-file tests/toggle-llms-off.php --path=wp
 *
 * @package Internick\AIProductSchema
 */

update_option( 'internick_aips_settings', array( 'enable_llms' => 'no' ) );
WP_CLI::success( 'enable_llms = no' );
