<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules;
use Mai\PublishRequirements\Rules\FeaturedImage;
use Mai\PublishRequirements\Rules\Rule;
use Mai\PublishRequirements\Severity;

/**
 * @covers \Mai\PublishRequirements\Rules
 * @covers \Mai\PublishRequirements\Rules\Rule
 */
class Test_Rules extends WP_UnitTestCase {

	/** @var callable[] */
	private array $added = [];

	public function tear_down(): void {
		foreach ( $this->added as $callback ) {
			remove_filter( 'mai_publish_requirements_rules', $callback );
		}

		$this->added = [];

		parent::tear_down();
	}

	private function register( object ...$rules ): void {
		$callback      = static fn ( array $existing ): array => array_merge( $existing, $rules );
		$this->added[] = $callback;

		add_filter( 'mai_publish_requirements_rules', $callback );
	}

	/**
	 * The plugin is a registry. Nothing enforces anything until a site says so,
	 * which is what makes severity a site decision rather than a plugin one.
	 */
	public function test_nothing_is_registered_by_default(): void {
		$this->assertSame( [], Rules::all() );
		$this->assertSame( [], Rules::gated_post_types() );
	}

	public function test_a_registered_rule_is_returned(): void {
		$this->register( new FeaturedImage() );

		$this->assertCount( 1, Rules::all() );
		$this->assertSame( [ 'post' ], Rules::gated_post_types() );
	}

	public function test_non_rules_are_ignored(): void {
		$this->register( new FeaturedImage() );
		add_filter( 'mai_publish_requirements_rules', static fn ( array $r ): array => array_merge( $r, [ 'nonsense', 42 ] ) );

		$this->assertCount( 1, Rules::all() );
	}

	public function test_post_types_come_from_the_rule(): void {
		$this->assertSame( [ 'post' ], Rules::post_types_for( new FeaturedImage() ) );
	}

	public function test_the_filter_moves_a_rule(): void {
		$callback = static fn ( array $types, string $id ): array => 'featured_image' === $id ? [ 'custom' ] : $types;
		add_filter( 'mai_publish_requirements_rule_post_types', $callback, 10, 2 );

		$result = Rules::post_types_for( new FeaturedImage() );

		remove_filter( 'mai_publish_requirements_rule_post_types', $callback, 10 );

		$this->assertSame( [ 'custom' ], $result );
	}

	public function test_for_post_type_returns_applicable_rules_only(): void {
		$this->register( new FeaturedImage() );

		$this->assertCount( 1, Rules::for_post_type( 'post' ) );
		$this->assertSame( [], Rules::for_post_type( 'page' ) );
	}

	/**
	 * Rules stack: every applicable one runs on the same save.
	 */
	public function test_rules_stack(): void {
		$this->register( new FeaturedImage(), $this->always_fails() );

		$this->assertCount( 2, Rules::for_post_type( 'post' ) );
	}

	public function test_the_base_class_defaults_post_types(): void {
		$this->assertSame( [ 'post' ], $this->always_fails()->post_types() );
	}

	private function always_fails(): Rule {
		return new class() extends Rule {
			public function id(): string {
				return 'always_fails';
			}

			public function check( Context $context ): ?Result {
				return new Result( Severity::Warn, 'do the thing' );
			}
		};
	}
}
