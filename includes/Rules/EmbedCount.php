<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * A ceiling on embeds: videos, social posts, players and iframes.
 *
 * Every embed is a third-party document the reader's browser loads, so a post
 * with dozens of them is heavy however short its text. Warns by default: a
 * busy live blog or a round-up can need many on purpose.
 *
 * Counts what actually becomes an embed on the page, however it was added:
 *
 * - an Embed block, whatever the provider
 * - an `<iframe>`, which is how YouTube, Vimeo and Spotify's "embed code" arrives
 * - the blockquote a social network's embed code pastes, which its own script
 *   turns into an iframe (Bluesky, Twitter/X, Instagram, TikTok, Threads)
 * - an `[embed]` shortcode
 * - in classic content only, a link alone on its line that WordPress turns into
 *   an embed. Only URLs a registered oEmbed provider claims count, matched
 *   locally with no network request. The block editor does not auto-embed a
 *   bare link, so a link in a paragraph block is left alone.
 *
 * The contents of an Embed block are skipped, so its URL is not counted twice.
 */
class EmbedCount extends Rule {

	/**
	 * Marks in pasted embed code that a provider's script converts to an iframe.
	 * Keyed on what the script looks for, not on how it renders.
	 */
	private const PASTED_EMBED_PATTERNS = [
		'/\bdata-bluesky-uri\s*=/i',
		'/<blockquote\b[^>]*\bclass\s*=\s*["\'][^"\']*\b(?:twitter-tweet|instagram-media|tiktok-embed|text-post-media)\b/i',
	];

	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
		protected readonly int $max = 25,
	) {}

	public function id(): string {
		return 'embed_count';
	}

	public function check( Context $context ): ?Result {
		$count = $this->count_embeds( $context->content() );

		if ( $count <= $this->max ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: %d: how many embeds the post has. */
				__( 'use fewer embeds (%d in this post)', 'mai-publish-requirements' ),
				$count
			)
		);
	}

	/**
	 * How many embeds the content carries. Public so a site can show the same
	 * number elsewhere without re-deriving what counts.
	 */
	public function count_embeds( string $content ): int {
		if ( '' === trim( $content ) ) {
			return 0;
		}

		return $this->count_in_blocks( parse_blocks( $content ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 */
	private function count_in_blocks( array $blocks ): int {
		$count = 0;

		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );

			// core-embed/* is how Embed blocks were saved before WordPress 5.6.
			if ( 'core/embed' === $name || str_starts_with( $name, 'core-embed/' ) ) {
				$count++;
				continue;
			}

			$html   = (string) ( $block['innerHTML'] ?? '' );
			$count += $this->count_in_html( $html );

			// A null block name is classic content, where WordPress auto-embeds a
			// link on its own line.
			if ( '' === $name ) {
				$count += $this->count_bare_links( $html );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_in_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	private function count_in_html( string $html ): int {
		if ( '' === $html ) {
			return 0;
		}

		$count = (int) preg_match_all( '/<iframe\b/i', $html );

		foreach ( self::PASTED_EMBED_PATTERNS as $pattern ) {
			$count += (int) preg_match_all( $pattern, $html );
		}

		return $count + (int) preg_match_all( '/\[embed\b[^\]]*\]/i', $html );
	}

	/**
	 * Links alone on their line that a registered oEmbed provider claims, the
	 * same test WordPress's auto-embed uses, without the network discovery step.
	 */
	private function count_bare_links( string $html ): int {
		if ( ! preg_match_all( '~^\s*(https?://[^\s<>"]+)\s*$~im', $html, $matches ) ) {
			return 0;
		}

		$oembed = _wp_oembed_get_object();
		$count  = 0;

		foreach ( $matches[1] as $url ) {
			if ( $oembed->get_provider( $url, [ 'discover' => false ] ) ) {
				$count++;
			}
		}

		return $count;
	}
}
