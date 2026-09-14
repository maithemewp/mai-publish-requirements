<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * A floor on length.
 *
 * Aimed at the empty placeholder published by accident, not at short posts
 * written on purpose, so the default is low enough that deliberate brevity
 * passes. Warns by default for the same reason: a short post is a judgement
 * call, and a rule that blocks one is wrong more often than it is right.
 *
 * Counts words in the rendered text, with tags and block comments stripped, so
 * a post built entirely from blocks is not credited for its markup.
 */
class MinimumLength extends Rule {

	/**
	 * Severity first, as every rule here takes it, so the positional order is
	 * guessable across all of them. The interesting argument is $words, which is
	 * what named arguments are for: `new MinimumLength( words: 30 )`.
	 */
	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
		protected readonly int $words = 50,
	) {}

	public function id(): string {
		return 'minimum_length';
	}

	public function check( Context $context ): ?Result {
		$count = $this->count_words( $context->content() );

		if ( $count >= $this->words ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: 1: words written, 2: words expected. */
				__( 'write a bit more (%1$d words, %2$d expected)', 'mai-publish-requirements' ),
				$count,
				$this->words
			)
		);
	}

	protected function count_words( string $content ): int {
		// excerpt_remove_blocks drops block DELIMITERS but keeps inner content,
		// so a block-built post counts its words rather than its markup.
		$text = function_exists( 'excerpt_remove_blocks' ) ? excerpt_remove_blocks( $content ) : $content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );

		return count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] );
	}
}
