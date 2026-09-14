<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;

defined( 'ABSPATH' ) || exit;

/**
 * A single publish requirement.
 *
 * A rule answers one question: does this post pass, and if not, how serious is
 * it and what should the author do? Nothing registers a rule for you. A site
 * opts in through the `mai_publish_requirements_rules` filter, which is also
 * where it chooses how hard a rule bites.
 *
 * Most rules should extend Rule rather than implement this directly. The
 * interface stays the contract, so anything reaching for full control still can.
 */
interface RuleInterface {

	/**
	 * Stable identifier, used as the error code (e.g. 'featured_image').
	 */
	public function id(): string;

	/**
	 * Post types this rule is enforced on.
	 *
	 * @return string[]
	 */
	public function post_types(): array;

	/**
	 * Checks a post being saved.
	 *
	 * @return Result|null Null when the post passes.
	 */
	public function check( Context $context ): ?Result;
}
