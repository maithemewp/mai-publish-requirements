<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Requires a featured image before publishing.
 */
final class FeaturedImage implements RuleInterface {

	public function id(): string {
		return 'featured_image';
	}

	public function label(): string {
		return __( 'Featured image', 'mai-publish-requirements' );
	}

	public function default_post_types(): array {
		return [ 'post' ];
	}

	public function check( Context $context ): ?string {
		if ( $context->featured_image_id() >= 1 ) {
			return null;
		}

		return __( 'set a featured image', 'mai-publish-requirements' );
	}
}
