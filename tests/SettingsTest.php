<?php
/**
 * Settings option accessor + feature toggles.
 *
 * @package ShopGraph
 */

use ShopGraph\Compat\SeoPlugins;
use ShopGraph\Schema\ProductSchema;
use ShopGraph\Schema\SchemaOutput;
use ShopGraph\Settings\Options;

class SettingsTest extends WP_UnitTestCase {

	private function make_output(): SchemaOutput {
		return new SchemaOutput( new ProductSchema(), new SeoPlugins() );
	}

	public function test_get_returns_stored_value(): void {
		update_option( 'shopgraph_settings', array( 'enable_schema' => false ) );
		$this->assertFalse( Options::get( 'enable_schema', true ) );
	}

	public function test_sensible_defaults_when_unset(): void {
		$this->assertTrue( Options::enabled( 'enable_schema' ) );
		$this->assertTrue( Options::enabled( 'enable_llms' ) );
		$this->assertTrue( Options::enabled( 'enable_robots' ) );
		$this->assertSame( 'auto', Options::get( 'schema_mode', 'auto' ) );
	}

	public function test_disabling_schema_stops_standalone_output(): void {
		$output = $this->make_output();
		$this->assertTrue( $output->should_output_standalone() );

		update_option( 'shopgraph_settings', array( 'enable_schema' => 'no' ) );
		$this->assertFalse( $output->should_output_standalone() );
	}

	public function test_standalone_mode_forces_standalone(): void {
		update_option( 'shopgraph_settings', array( 'schema_mode' => 'standalone' ) );
		$this->assertTrue( $this->make_output()->should_output_standalone() );
	}
}
