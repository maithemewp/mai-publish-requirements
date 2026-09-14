<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Every image in the body carries alt text.
 *
 * Publishing is the last moment somebody remembers what the picture was of, so
 * it is the right moment to ask. Warns rather than blocks: a missing alt is a
 * real accessibility failure, and holding the post hostage over it mostly
 * produces alt="image" typed to get past the gate.
 *
 * An EMPTY alt is left alone on purpose. alt="" is the correct, meaningful way
 * to mark a decorative image, and treating it as missing would push authors
 * into describing spacers.
 */
class ImageAltText extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
	) {}

	public function id(): string {
		return 'image_alt_text';
	}

	public function check( Context $context ): ?Result {
		$content = $context->content();

		if ( ! str_contains( $content, '<img' ) ) {
			return null;
		}

		$missing = 0;

		if ( preg_match_all( '/<img\b[^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $tag ) {
				if ( ! preg_match( '/\balt\s*=/i', $tag ) ) {
					$missing++;
				}
			}
		}

		if ( ! $missing ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: %d: how many images have no alt attribute. */
				_n(
					'add alt text to %d image',
					'add alt text to %d images',
					$missing,
					'mai-publish-requirements'
				),
				$missing
			)
		);
	}
}
