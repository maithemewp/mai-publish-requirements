<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * What a rule says about one post: how serious, and what to do about it.
 *
 * Severity and message travel together because they are decided together. A
 * rule that warns about a missing featured image because the body has a picture
 * wants to say something different from the same rule blocking a post with no
 * image at all, and a severity flag bolted onto a fixed message could not.
 *
 * The message is an imperative FRAGMENT, not a sentence: several are joined into
 * one line, so "set a featured image" reads correctly where "You must set a
 * featured image." would not.
 *
 * A rule may add `detail`: one or two full sentences saying why it matters,
 * shown after the recommendation. Authors who hit the same warning repeatedly
 * read "we recommend" as an opinion, so a rule with a real reason can give it.
 */
final readonly class Result {

	public function __construct(
		public Severity $severity,
		public string $message,
		public string $detail = '',
	) {}

	public function is_block(): bool {
		return Severity::Block === $this->severity;
	}
}
