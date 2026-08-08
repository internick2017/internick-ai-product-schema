<?php
/**
 * Typed accessor over the single `internick_aips_settings` array option.
 *
 * @package Internick\AIProductSchema
 */

namespace Internick\AIProductSchema\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads plugin settings with sensible defaults.
 */
class Options {

	public const OPTION = 'internick_aips_settings';

	/**
	 * Default values (checkboxes use WooCommerce's 'yes'/'no' convention).
	 *
	 * @var array<string, string>
	 */
	private const DEFAULTS = array(
		'enable_schema' => 'yes',
		'enable_llms'   => 'yes',
		'enable_robots' => 'yes',
		'schema_mode'   => 'auto', // auto | standalone
	);

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * Get one setting, falling back to $default (then the built-in default).
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is absent.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Whether a boolean feature toggle is on. Absent/unknown keys default to on.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function enabled( string $key ): bool {
		$value = self::get( $key, 'yes' );
		return ! in_array( $value, array( 'no', false, 0, '0', '' ), true );
	}
}
