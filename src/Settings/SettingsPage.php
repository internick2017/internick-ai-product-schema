<?php
/**
 * Adds a "AI Product Schema" tab under WooCommerce > Settings.
 *
 * Fields use array-style ids (`internick_aips_settings[...]`) so WooCommerce's
 * settings API saves them all into the single `internick_aips_settings` option that
 * Options reads. The tab UI is exercised in the manual DDEV verification (Task 8).
 *
 * @package Internick\AIProductSchema
 */

namespace Internick\AIProductSchema\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce settings tab for AI Product Schema.
 */
class SettingsPage extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'internick-ai-product-schema';
		$this->label = __( 'AI Product Schema', 'internick-ai-product-schema' );
		parent::__construct();
	}

	/**
	 * Settings fields for the default section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_for_default_section(): array {
		return array(
			array(
				'title' => __( 'AI Product Schema', 'internick-ai-product-schema' ),
				'type'  => 'title',
				'desc'  => __( 'Make your WooCommerce products discoverable and purchasable by AI shopping agents.', 'internick-ai-product-schema' ),
				'id'    => 'internick_aips_options',
			),
			array(
				'title'   => __( 'Product schema (JSON-LD)', 'internick-ai-product-schema' ),
				'desc'    => __( 'Output complete schema.org Product structured data on product pages.', 'internick-ai-product-schema' ),
				'id'      => 'internick_aips_settings[enable_schema]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Serve /llms.txt', 'internick-ai-product-schema' ),
				'desc'    => __( 'Publish a Markdown product index at /llms.txt for AI crawlers.', 'internick-ai-product-schema' ),
				'id'      => 'internick_aips_settings[enable_llms]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'AI robots.txt directives', 'internick-ai-product-schema' ),
				'desc'    => __( 'Welcome known AI shopping crawlers in robots.txt and reference /llms.txt.', 'internick-ai-product-schema' ),
				'id'      => 'internick_aips_settings[enable_robots]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => __( 'Schema mode', 'internick-ai-product-schema' ),
				'desc'     => __( 'Auto enhances the Product schema WooCommerce (or Yoast / Rank Math) already outputs. Standalone suppresses those and prints Internick\AIProductSchema\'s own complete Product node instead. Neither mode ever outputs a duplicate.', 'internick-ai-product-schema' ),
				'id'       => 'internick_aips_settings[schema_mode]',
				'type'     => 'select',
				'default'  => 'auto',
				'options'  => array(
					'auto'       => __( 'Auto (coexist with SEO plugins)', 'internick-ai-product-schema' ),
					'standalone' => __( 'Always standalone', 'internick-ai-product-schema' ),
				),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'internick_aips_options',
			),
		);
	}
}
