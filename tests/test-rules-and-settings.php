<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Rules;
use Mai\PublishRequirements\Settings;
use Mai\PublishRequirements\Rules\FeaturedImage;

/**
 * @covers \Mai\PublishRequirements\Rules
 * @covers \Mai\PublishRequirements\Settings
 */
class Test_Rules_And_Settings extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Settings::flush_cache();
	}

	public function test_post_types_default_to_the_rule_default(): void {
		$this->assertSame( [ 'post' ], Settings::post_types_for_rule( new FeaturedImage() ) );
	}

	public function test_saved_option_overrides_the_default(): void {
		update_option( Settings::OPTION_NAME, [ 'featured_image' => [ 'post', 'page' ] ] );

		$this->assertSame( [ 'post', 'page' ], Settings::post_types_for_rule( new FeaturedImage() ) );
	}

	public function test_filter_overrides_everything(): void {
		$callback = static fn ( array $types, string $id ): array => 'featured_image' === $id ? [ 'custom' ] : $types;
		add_filter( 'mai_publish_requirements_rule_post_types', $callback, 10, 2 );

		$result = Settings::post_types_for_rule( new FeaturedImage() );

		remove_filter( 'mai_publish_requirements_rule_post_types', $callback, 10 );

		$this->assertSame( [ 'custom' ], $result );
	}

	public function test_for_post_type_returns_applicable_rules_only(): void {
		$this->assertCount( 1, Rules::for_post_type( 'post' ) );
		$this->assertInstanceOf( FeaturedImage::class, Rules::for_post_type( 'post' )[0] );
		$this->assertSame( [], Rules::for_post_type( 'page' ) );
	}

	public function test_gated_post_types_is_the_union_across_rules(): void {
		$this->assertSame( [ 'post' ], Rules::gated_post_types() );
	}

	public function test_custom_rule_can_be_registered(): void {
		$rule     = new class() implements \Mai\PublishRequirements\Rules\RuleInterface {
			public function id(): string { return 'always_fails'; }
			public function label(): string { return 'Always fails'; }
			public function default_post_types(): array { return [ 'post' ]; }
			public function check( \Mai\PublishRequirements\Context $context ): ?string { return 'do the thing'; }
		};
		$callback = static fn ( array $rules ): array => array_merge( $rules, [ $rule ] );
		add_filter( 'mai_publish_requirements_rules', $callback );

		$ids = array_map( static fn ( $r ): string => $r->id(), Rules::all() );

		remove_filter( 'mai_publish_requirements_rules', $callback );

		$this->assertContains( 'always_fails', $ids );
	}
}
