<?php
/**
 * Serves `/llms.txt`: a Markdown store summary + product index for AI crawlers,
 * following the llms.txt spec (https://llmstxt.org/).
 *
 * @package ShopGraph
 */

namespace ShopGraph\Llms;

use ShopGraph\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the /llms.txt route and builds its content.
 */
class LlmsTxt {

	private const QUERY_VAR    = 'shopgraph_llms';
	private const CACHE_KEY    = 'shopgraph_llms_txt';
	private const MAX_PRODUCTS = 200;

	/**
	 * Hook the rewrite rule, query var, and request handler.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		// Priority 0 so we serve /llms.txt before WordPress' canonical redirect
		// (priority 10) can 301 it to /llms.txt/ (the llms.txt spec wants the
		// exact path, with no trailing slash).
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Register the `^llms\.txt$` rewrite rule.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Whitelist our query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve the llms.txt body (text/plain) when the query var is present.
	 */
	public function maybe_serve(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! Options::enabled( 'enable_llms' ) ) {
			return;
		}

		$body = get_transient( self::CACHE_KEY );
		if ( false === $body ) {
			$body = $this->render();
			set_transient( self::CACHE_KEY, $body, HOUR_IN_SECONDS );
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			status_header( 200 );
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text response, not HTML.
		exit;
	}

	/**
	 * Build the llms.txt Markdown: H1 store name, summary blockquote, and a
	 * "## Products" list of `- [name](url): price` lines.
	 *
	 * @return string
	 */
	public function render(): string {
		$name = get_bloginfo( 'name' );
		$desc = get_bloginfo( 'description' );

		$lines   = array();
		$lines[] = '# ' . $name;
		$lines[] = '';
		if ( '' !== (string) $desc ) {
			$lines[] = '> ' . $desc;
			$lines[] = '';
		}
		$lines[] = 'Product catalog for AI shopping agents. Each product links to its page; full structured data (schema.org Product JSON-LD) is available on each page.';
		$lines[] = '';
		$lines[] = '## Products';
		$lines[] = '';

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => self::MAX_PRODUCTS,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		foreach ( $products as $product ) {
			$url  = get_permalink( $product->get_id() );
			$line = '- [' . $product->get_name() . '](' . $url . ')';

			$price = trim( html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $price ) {
				$line .= ': ' . $price;
			}
			$lines[] = $line;
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Clear the cached body (call when products change).
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
