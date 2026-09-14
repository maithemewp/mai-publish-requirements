<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * How many terms a post must have in one taxonomy.
 *
 * One rule rather than three, because "at least one category", "only one
 * category" and "no more than five tags" are the same question asked with
 * different numbers: count the terms, compare to a bound.
 *
 * Both bounds are optional. `min` alone means required, `max` alone means
 * capped, and both together means a range, so `min: 1, max: 1` is exactly one.
 *
 * Stack it per taxonomy. Each instance is its own rule with its own severity,
 * so a site can warn about tag sprawl and block on a missing category in the
 * same save.
 */
class TermCount extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Block,
		protected readonly string $taxonomy = 'category',
		protected readonly ?int $min = null,
		protected readonly ?int $max = null,
	) {}

	/**
	 * Carries the taxonomy, because a site is expected to register several of
	 * these. A shared id would make them indistinguishable to
	 * `mai_publish_requirements_rule_post_types`, which would then move every
	 * one of them at once.
	 */
	public function id(): string {
		return 'term_count_' . $this->taxonomy;
	}

	public function check( Context $context ): ?Result {
		$count = count( $this->countable_terms( $context ) );

		$message = match ( true ) {
			null !== $this->min && $count < $this->min => $this->too_few( $count ),
			null !== $this->max && $count > $this->max => $this->too_many( $count ),
			default                                    => null,
		};

		return null === $message ? null : new Result( $this->severity, $message );
	}

	/**
	 * The terms that count as a choice.
	 *
	 * The default category is excluded, because it is what a post gets when
	 * nobody picked, so counting it would pass every unsorted post. Read from the
	 * option rather than assuming the slug 'uncategorized', which sites rename.
	 *
	 * @return string[]
	 */
	protected function countable_terms( Context $context ): array {
		$slugs = $context->term_slugs( $this->taxonomy );

		if ( 'category' !== $this->taxonomy ) {
			return $slugs;
		}

		$default = get_term( (int) get_option( 'default_category' ), 'category' );
		$ignore  = $default instanceof \WP_Term ? $default->slug : 'uncategorized';

		return array_values( array_filter( $slugs, static fn ( string $slug ): bool => $ignore !== $slug ) );
	}

	protected function too_few( int $count ): string {
		// "choose a category" reads better than "have at least 1 category" for
		// the overwhelmingly common case of requiring one, so it is worth the
		// extra branch.
		if ( 1 === $this->min ) {
			return sprintf(
				/* translators: %s: taxonomy label, e.g. "category". */
				__( 'choose a %s', 'mai-publish-requirements' ),
				$this->label( 1 )
			);
		}

		return sprintf(
			/* translators: 1: how many are required, 2: taxonomy label, 3: how many are set. */
			__( 'choose at least %1$d %2$s (you have %3$d)', 'mai-publish-requirements' ),
			$this->min,
			$this->label( (int) $this->min ),
			$count
		);
	}

	protected function too_many( int $count ): string {
		if ( 1 === $this->max ) {
			return sprintf(
				/* translators: 1: taxonomy label, 2: how many are set. */
				__( 'pick just one %1$s (you have %2$d)', 'mai-publish-requirements' ),
				$this->label( 1 ),
				$count
			);
		}

		return sprintf(
			/* translators: 1: the maximum, 2: taxonomy label, 3: how many are set. */
			__( 'use no more than %1$d %2$s (you have %3$d)', 'mai-publish-requirements' ),
			$this->max,
			$this->label( (int) $this->max ),
			$count
		);
	}

	/**
	 * The taxonomy's own words, singular or plural, so a custom taxonomy reads
	 * as itself rather than as its slug.
	 */
	protected function label( int $number ): string {
		$taxonomy = get_taxonomy( $this->taxonomy );

		if ( ! $taxonomy ) {
			return $this->taxonomy;
		}

		return strtolower( 1 === $number ? $taxonomy->labels->singular_name : $taxonomy->labels->name );
	}
}
