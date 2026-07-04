<?php
/**
 * robots.txt directives that welcome AI shopping crawlers + reference llms.txt.
 *
 * @package ShopGraph
 */

use ShopGraph\Llms\RobotsTxt;

class RobotsTxtTest extends WP_UnitTestCase {

	public function test_filter_adds_ai_bot_directives_and_llms_reference(): void {
		$original = "User-agent: *\nDisallow:\n";
		$out      = ( new RobotsTxt() )->filter( $original, true );

		$this->assertStringContainsString( 'User-agent: GPTBot', $out );
		$this->assertStringContainsString( 'User-agent: Google-Extended', $out );
		$this->assertStringContainsString( 'User-agent: ClaudeBot', $out );
		$this->assertStringContainsString( 'Allow: /', $out );
		$this->assertStringContainsString( '# llms.txt: ' . home_url( '/llms.txt' ), $out );

		// Original robots.txt content is preserved.
		$this->assertStringContainsString( 'User-agent: *', $out );
	}

	public function test_filter_respects_non_public_site(): void {
		$original = "User-agent: *\nDisallow: /\n";
		$out      = ( new RobotsTxt() )->filter( $original, false );

		$this->assertSame( $original, $out );
		$this->assertStringNotContainsString( 'GPTBot', $out );
	}
}
