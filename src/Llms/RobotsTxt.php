<?php
/**
 * Appends robots.txt directives that explicitly welcome AI shopping crawlers
 * and point them at the store's /llms.txt index.
 *
 * AI Product Schema's goal is discoverability, so the default posture is Allow (not
 * block) for known AI agents. Gated by the site's "public" flag and, from
 * Task 6 onward, by the plugin settings.
 *
 * @package Internick\AIProductSchema
 */

namespace Internick\AIProductSchema\Llms;

use Internick\AIProductSchema\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Filters the virtual robots.txt output.
 */
class RobotsTxt {

	/**
	 * Known AI shopping / crawler user agents welcomed by default.
	 */
	public const DEFAULT_BOTS = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'Google-Extended',
		'ClaudeBot',
		'Claude-User',
		'PerplexityBot',
		'CCBot',
		'Amazonbot',
		'Applebot-Extended',
	);

	/** @var string[] */
	private array $bots;

	/**
	 * @param string[] $bots User agents to welcome.
	 */
	public function __construct( array $bots = self::DEFAULT_BOTS ) {
		$this->bots = $bots;
	}

	/**
	 * Register the robots_txt filter.
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * Append AI-bot Allow directives + the llms.txt reference.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether the site is public (Settings > Reading).
	 * @return string
	 */
	public function filter( string $output, bool $public = true ): string {
		if ( ! Options::enabled( 'enable_robots' ) ) {
			return $output;
		}

		// Respect a site that asks search engines not to index it.
		if ( ! $public ) {
			return $output;
		}

		$lines   = array();
		$lines[] = '';
		$lines[] = '# AI Product Schema: welcome AI shopping agents.';
		foreach ( $this->bots as $bot ) {
			$lines[] = 'User-agent: ' . $bot;
			$lines[] = 'Allow: /';
			$lines[] = '';
		}
		$lines[] = '# llms.txt: ' . home_url( '/llms.txt' );

		return $output . implode( "\n", $lines ) . "\n";
	}
}
