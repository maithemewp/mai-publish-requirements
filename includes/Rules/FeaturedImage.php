<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Requires a featured image.
 *
 * Binary: the image is there or it is not, so there is nothing for the rule to
 * judge and the severity is the site's call, taken at registration. A site that
 * wants the answer to depend on the post (say, warn when the body already has a
 * picture to fall back on) subclasses and overrides check().
 */
class FeaturedImage extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Block,
	) {}

	public function id(): string {
		return 'featured_image';
	}

	public function check( Context $context ): ?Result {
		if ( $context->featured_image_id() >= 1 ) {
			return null;
		}

		return new Result( $this->severity, __( 'set a featured image', 'mai-publish-requirements' ) );
	}
}
