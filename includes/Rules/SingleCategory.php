<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements\Rules;

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * At most one term in a taxonomy.
 *
 * For sites where a section is a place rather than a label: breadcrumbs, an
 * archive header and a canonical URL all answer "which section is this in", and
 * there is only room for one answer. Warns by default, because the post is
 * readable either way and this is an editorial preference rather than a fault.
 *
 * Says nothing when there are none. That is CategoryRequired's question, and two
 * rules complaining about one empty field reads as a bug.
 */
class SingleCategory extends Rule {

	public function __construct(
		protected readonly Severity $severity = Severity::Warn,
		protected readonly string $taxonomy = 'category',
	) {}

	public function id(): string {
		return 'single_category';
	}

	public function check( Context $context ): ?Result {
		$count = count( $context->term_slugs( $this->taxonomy ) );

		if ( $count <= 1 ) {
			return null;
		}

		return new Result(
			$this->severity,
			sprintf(
				/* translators: 1: taxonomy label, 2: how many are selected. */
				__( 'pick just one %1$s (you have %2$d)', 'mai-publish-requirements' ),
				$this->taxonomy_label(),
				$count
			)
		);
	}

	protected function taxonomy_label(): string {
		$taxonomy = get_taxonomy( $this->taxonomy );

		return $taxonomy ? strtolower( $taxonomy->labels->singular_name ) : $this->taxonomy;
	}
}
