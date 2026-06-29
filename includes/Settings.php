<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

use Mai\PublishRequirements\Rules\RuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Settings facade. The single array option holds `[ rule_id => [post types] ]`.
 * A rule's applicable post types come from the saved value, falling back to the
 * rule's default, then through the `mai_publish_requirements_rule_post_types` filter.
 */
final class Settings {

	/**
	 * The single array option that holds every saved setting.
	 */
	public const OPTION_NAME = 'mai_publish_requirements';

	/**
	 * Per-request memo of the saved option. Null = not loaded.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * The saved option, memoized per request.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all(): array {
		if ( null === self::$cache ) {
			$value       = get_option( self::OPTION_NAME, [] );
			self::$cache = is_array( $value ) ? $value : [];
		}

		return self::$cache;
	}

	/**
	 * Drops the per-request memo so a save reflects on the same request.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * The post types a rule is enforced on.
	 *
	 * @param RuleInterface $rule
	 * @return string[]
	 */
	public static function post_types_for_rule( RuleInterface $rule ): array {
		$saved = self::get_all();

		$types = array_key_exists( $rule->id(), $saved ) && is_array( $saved[ $rule->id() ] )
			? array_values( array_map( 'strval', $saved[ $rule->id() ] ) )
			: $rule->default_post_types();

		/**
		 * Filters the post types a rule is enforced on.
		 *
		 * @param string[] $types   Post type slugs.
		 * @param string   $rule_id The rule identifier.
		 */
		return (array) apply_filters( 'mai_publish_requirements_rule_post_types', $types, $rule->id() );
	}
}
