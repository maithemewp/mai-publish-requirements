<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Requires at least one real term in a taxonomy.
 *
 * The default category is excluded on purpose: it is what a post gets when
 * nobody chose, so treating it as a choice would pass every unsorted post. Read
 * from the option rather than hardcoding 'uncategorized', because sites rename
 * it and the slug then no longer matches.
 */
class CategoryRequired extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Block,
		protected readonly string $taxonomy = 'category',
	) {}

	public function id(): string {
		return 'category_required';
	}

	public function check( Context $context ): ?Result {
		$slugs = $context->term_slugs( $this->taxonomy );

		if ( 'category' === $this->taxonomy ) {
			$default = get_term( (int) get_option( 'default_category' ), 'category' );
			$ignore  = $default instanceof \WP_Term ? $default->slug : 'uncategorized';
			$slugs   = array_filter( $slugs, static fn ( string $slug ): bool => $ignore !== $slug );
		}

		if ( $slugs ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: %s: taxonomy label, e.g. "category". */
				__( 'choose a %s', 'mai-publish-requirements' ),
				$this->taxonomy_label()
			)
		);
	}

	protected function taxonomy_label(): string {
		$taxonomy = get_taxonomy( $this->taxonomy );

		return $taxonomy ? strtolower( $taxonomy->labels->singular_name ) : $this->taxonomy;
	}
}
