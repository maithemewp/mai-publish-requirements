<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Base for a publish requirement. Extend this, not the interface.
 *
 * It exists so a rule is usually two methods, and so the contract can grow
 * without breaking every rule already written against it: anything added here
 * with a default is inherited rather than demanded.
 */
abstract class Rule implements RuleInterface {

	/**
	 * Posts, unless a rule says otherwise. Override to widen or move it, or use
	 * the `mai_publish_requirements_rule_post_types` filter to do it per site
	 * without subclassing.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		return [ 'post' ];
	}
}
