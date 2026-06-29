<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

use Mai\PublishRequirements\Rules\FeaturedImage;
use Mai\PublishRequirements\Rules\RuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Rule registry. The built-in rules plus anything added via the
 * `mai_publish_requirements_rules` filter.
 */
final class Rules {

	/**
	 * All registered rules.
	 *
	 * @return RuleInterface[]
	 */
	public static function all(): array {
		$rules = (array) apply_filters( 'mai_publish_requirements_rules', [ new FeaturedImage() ] );

		return array_values(
			array_filter( $rules, static fn ( $rule ): bool => $rule instanceof RuleInterface )
		);
	}

	/**
	 * The rules that apply to a given post type.
	 *
	 * @param string $post_type
	 * @return RuleInterface[]
	 */
	public static function for_post_type( string $post_type ): array {
		$applicable = [];

		foreach ( self::all() as $rule ) {
			if ( in_array( $post_type, Settings::post_types_for_rule( $rule ), true ) ) {
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
			$types = array_merge( $types, Settings::post_types_for_rule( $rule ) );
		}

		return array_values( array_unique( $types ) );
	}
}
