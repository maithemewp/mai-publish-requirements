<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * A ceiling on title length.
 *
 * 60 characters is where Google starts truncating a result and where a card
 * layout usually wraps to a third line. Neither breaks anything, so this warns.
 *
 * Counted in characters rather than bytes: an em dash and a letter both occupy
 * one place in a search result, and strlen() would call the same title too long
 * simply for containing an accent.
 */
class TitleLength extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
		protected readonly int $characters = 60,
	) {}

	public function id(): string {
		return 'title_length';
	}

	public function check( Context $context ): ?Result {
		$length = mb_strlen( wp_strip_all_tags( $context->title() ) );

		if ( $length <= $this->characters ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: 1: title length, 2: the maximum. */
				__( 'shorten the title (%1$d characters, %2$d recommended)', 'mai-publish-requirements' ),
				$length,
				$this->characters
			)
		);
	}
}
