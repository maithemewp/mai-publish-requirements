<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Requires a hand-written excerpt.
 *
 * Without one, archives and share cards fall back to the opening of the body,
 * truncated to a word count, which lands mid-sentence more often than not. The
 * post reads fine either way, so this warns: it is the difference between a
 * tidy card and an untidy one, not between working and broken.
 */
class ExcerptRequired extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
	) {}

	public function id(): string {
		return 'excerpt_required';
	}

	public function check( Context $context ): ?Result {
		return '' === trim( $context->excerpt() )
			? new Result( $this->severity, __( 'write an excerpt', 'mai-publish-requirements' ) )
			: null;
	}
}
