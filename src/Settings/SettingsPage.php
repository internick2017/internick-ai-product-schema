<?php
/**
 * Adds a "ShopGraph" tab under WooCommerce > Settings.
 *
 * Fields use array-style ids (`shopgraph_settings[...]`) so WooCommerce's
 * settings API saves them all into the single `shopgraph_settings` option that
 * Options reads. The tab UI is exercised in the manual DDEV verification (Task 8).
 *
 * @package ShopGraph
 */

namespace ShopGraph\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce settings tab for ShopGraph.
 */
class SettingsPage extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'shopgraph';
		$this->label = __( 'ShopGraph', 'shopgraph' );
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
				'title' => __( 'ShopGraph', 'shopgraph' ),
				'type'  => 'title',
				'desc'  => __( 'Make your WooCommerce products discoverable and purchasable by AI shopping agents.', 'shopgraph' ),
				'id'    => 'shopgraph_options',
			),
			array(
				'title'   => __( 'Product schema (JSON-LD)', 'shopgraph' ),
				'desc'    => __( 'Output complete schema.org Product structured data on product pages.', 'shopgraph' ),
				'id'      => 'shopgraph_settings[enable_schema]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Serve /llms.txt', 'shopgraph' ),
				'desc'    => __( 'Publish a Markdown product index at /llms.txt for AI crawlers.', 'shopgraph' ),
				'id'      => 'shopgraph_settings[enable_llms]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'AI robots.txt directives', 'shopgraph' ),
				'desc'    => __( 'Welcome known AI shopping crawlers in robots.txt and reference /llms.txt.', 'shopgraph' ),
				'id'      => 'shopgraph_settings[enable_robots]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'    => __( 'Schema mode', 'shopgraph' ),
				'desc'     => __( 'Auto enhances the Product schema WooCommerce (or Yoast / Rank Math) already outputs. Standalone suppresses those and prints ShopGraph\'s own complete Product node instead. Neither mode ever outputs a duplicate.', 'shopgraph' ),
				'id'       => 'shopgraph_settings[schema_mode]',
				'type'     => 'select',
				'default'  => 'auto',
				'options'  => array(
					'auto'       => __( 'Auto (coexist with SEO plugins)', 'shopgraph' ),
					'standalone' => __( 'Always standalone', 'shopgraph' ),
				),
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'shopgraph_options',
			),
		);
	}
}
