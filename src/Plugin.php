<?php
/**
 * Plugin bootstrap / service wiring.
 *
 * @package ShopGraph
 */

namespace ShopGraph;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin container. Wires the feature services on boot.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	/** Absolute path to the main plugin file. */
	public string $file = '';

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	/**
	 * Boot the plugin: store the main file and register feature services.
	 *
	 * Services are wired in the tasks that follow:
	 *   ( new ProductFields\Fields() )->register();
	 *   ( new Schema\SchemaOutput( new Schema\ProductSchema(), new Compat\SeoPlugins() ) )->register();
	 *   ( new Llms\LlmsTxt() )->register();
	 *   ( new Llms\RobotsTxt() )->register();
	 *   ( new Settings\SettingsPage() )->register();
	 */
	public function boot( string $file ): void {
		$this->file = $file;

		( new ProductFields\Fields() )->register();
		( new Schema\SchemaOutput( new Schema\ProductSchema(), new Compat\SeoPlugins() ) )->register();
	}
}
