<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

use Mai\PublishRequirements\Rules\RuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Rule registry. Empty until a site registers something.
 *
 * Nothing ships enabled on purpose. The plugin owns the plumbing that is the
 * same everywhere (the gate, the demotion backstop, the notices); which rules
 * apply and how hard they bite are per-site judgements, so they are made in
 * code where the rest of a site's configuration lives.
 */
final class Rules {

	/**
	 * Every registered rule.
	 *
	 * @return RuleInterface[]
	 */
	public static function all(): array {
		/**
		 * Registers publish requirements.
		 *
		 * @param RuleInterface[] $rules
		 */
		$rules = (array) apply_filters( 'mai_publish_requirements_rules', [] );

		return array_values(
			array_filter( $rules, static fn ( $rule ): bool => $rule instanceof RuleInterface )
		);
	}

	/**
	 * The rules that apply to a given post type.
	 *
	 * @return RuleInterface[]
	 */
	public static function for_post_type( string $post_type ): array {
		$applicable = [];

		foreach ( self::all() as $rule ) {
			if ( in_array( $post_type, self::post_types_for( $rule ), true ) ) {
				$applicable[] = $rule;
			}
		}

		return $applicable;
	}

	/**
	 * Every post type gated by at least one rule.
	 *
	 * @return string[]
	 */
	public static function gated_post_types(): array {
		$types = [];

		foreach ( self::all() as $rule ) {
			$types = array_merge( $types, self::post_types_for( $rule ) );
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Where a rule is enforced: its own answer, filterable per site so moving a
	 * rule to another post type does not need a subclass.
	 *
	 * @return string[]
	 */
	public static function post_types_for( RuleInterface $rule ): array {
		/**
		 * Filters the post types a rule is enforced on.
		 *
		 * @param string[] $types   Post type slugs.
		 * @param string   $rule_id The rule identifier.
		 */
		return (array) apply_filters( 'mai_publish_requirements_rule_post_types', $rule->post_types(), $rule->id() );
	}
}
