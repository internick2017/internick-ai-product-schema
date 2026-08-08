<?php
/**
 * Settings option accessor + feature toggles.
 *
 * @package Internick\AIProductSchema
 */

use Internick\AIProductSchema\Compat\SeoPlugins;
use Internick\AIProductSchema\Schema\ProductSchema;
use Internick\AIProductSchema\Schema\SchemaOutput;
use Internick\AIProductSchema\Settings\Options;

class SettingsTest extends WP_UnitTestCase {

	private function make_output(): SchemaOutput {
		return new SchemaOutput( new ProductSchema(), new SeoPlugins() );
	}

	public function test_get_returns_stored_value(): void {
		update_option( 'internick_aips_settings', array( 'enable_schema' => false ) );
		$this->assertFalse( Options::get( 'enable_schema', true ) );
	}

	public function test_sensible_defaults_when_unset(): void {
		$this->assertTrue( Options::enabled( 'enable_schema' ) );
		$this->assertTrue( Options::enabled( 'enable_llms' ) );
		$this->assertTrue( Options::enabled( 'enable_robots' ) );
		$this->assertSame( 'auto', Options::get( 'schema_mode', 'auto' ) );
	}

	public function test_auto_mode_is_not_standalone(): void {
		// Default is auto: enhance the existing Product node, not standalone.
		$this->assertFalse( $this->make_output()->should_output_standalone() );
	}

	public function test_standalone_mode_forces_standalone(): void {
		update_option( 'internick_aips_settings', array( 'schema_mode' => 'standalone' ) );
		$this->assertTrue( $this->make_output()->should_output_standalone() );
	}

	public function test_disabling_schema_overrides_standalone_mode(): void {
		update_option( 'internick_aips_settings', array( 'schema_mode' => 'standalone', 'enable_schema' => 'no' ) );
		$this->assertFalse( $this->make_output()->should_output_standalone() );
	}
}
