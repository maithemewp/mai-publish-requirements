<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;

defined( 'ABSPATH' ) || exit;

/**
 * A single publish requirement.
 *
 * A rule answers one question: does this post pass, and if not, what should the
 * author do about it? Where a rule applies (which post types) is configuration,
 * not part of the rule — see Settings::post_types_for_rule().
 */
interface RuleInterface {

	/**
	 * Stable identifier used as the settings key and error code (e.g. 'featured_image').
	 */
	public function id(): string;

	/**
	 * Human label for the settings UI (e.g. 'Featured image').
	 */
	public function label(): string;

	/**
	 * Post types this rule applies to out of the box, before any saved setting
	 * or filter override.
	 *
	 * @return string[]
	 */
	public function default_post_types(): array;

	/**
	 * Checks a post being published.
	 *
	 * @param Context $context The normalized save context.
	 * @return string|null Null if the post passes; otherwise a short imperative
	 *                     fragment for the aggregated message (e.g. 'set a featured image').
	 */
	public function check( Context $context ): ?string;
}
