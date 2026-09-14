<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\CategoryRequired;
use Mai\PublishRequirements\Rules\MinimumLength;
use Mai\PublishRequirements\Rules\SingleCategory;
use Mai\PublishRequirements\Severity;

/**
 * @covers \Mai\PublishRequirements\Rules\CategoryRequired
 * @covers \Mai\PublishRequirements\Rules\SingleCategory
 * @covers \Mai\PublishRequirements\Rules\MinimumLength
 */
class Test_Shipped_Rules extends WP_UnitTestCase {

	private function rest_context( array $params = [], string $content = '' ): Context {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';

		foreach ( $params as $key => $value ) {
			$request[ $key ] = $value;
		}

		return Context::from_rest(
			(object) [ 'ID' => 0, 'post_type' => 'post', 'post_content' => $content ],
			$request
		);
	}

	// --- CategoryRequired -----------------------------------------------------

	public function test_category_required_fails_with_none(): void {
		$result = ( new CategoryRequired() )->check( $this->rest_context() );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Block, $result->severity );
	}

	public function test_category_required_passes_with_a_real_one(): void {
		$term = self::factory()->category->create( [ 'slug' => 'politics' ] );

		$this->assertNull( ( new CategoryRequired() )->check( $this->rest_context( [ 'categories' => [ $term ] ] ) ) );
	}

	/**
	 * The default category is what a post gets when nobody chose, so counting it
	 * would pass every unsorted post.
	 */
	public function test_category_required_ignores_the_default_category(): void {
		$default = (int) get_option( 'default_category' );

		$result = ( new CategoryRequired() )->check( $this->rest_context( [ 'categories' => [ $default ] ] ) );

		$this->assertInstanceOf( Result::class, $result );
	}

	public function test_category_required_severity_is_the_sites_call(): void {
		$result = ( new CategoryRequired( Severity::Warn ) )->check( $this->rest_context() );

		$this->assertSame( Severity::Warn, $result->severity );
	}

	// --- SingleCategory -------------------------------------------------------

	public function test_single_category_passes_with_one(): void {
		$term = self::factory()->category->create();

		$this->assertNull( ( new SingleCategory() )->check( $this->rest_context( [ 'categories' => [ $term ] ] ) ) );
	}

	public function test_single_category_warns_with_two(): void {
		$a = self::factory()->category->create( [ 'slug' => 'a' ] );
		$b = self::factory()->category->create( [ 'slug' => 'b' ] );

		$result = ( new SingleCategory() )->check( $this->rest_context( [ 'categories' => [ $a, $b ] ] ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertStringContainsString( '2', $result->message );
	}

	/**
	 * Silent on none: that is CategoryRequired's question, and two rules
	 * complaining about one empty field reads as a bug.
	 */
	public function test_single_category_is_silent_when_there_are_none(): void {
		$this->assertNull( ( new SingleCategory() )->check( $this->rest_context() ) );
	}

	// --- MinimumLength --------------------------------------------------------

	public function test_minimum_length_warns_when_short(): void {
		$result = ( new MinimumLength( 50 ) )->check( $this->rest_context( [], 'three words only' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_passes_when_long_enough(): void {
		$content = implode( ' ', array_fill( 0, 60, 'word' ) );

		$this->assertNull( ( new MinimumLength( 50 ) )->check( $this->rest_context( [], $content ) ) );
	}

	/**
	 * A block-built post is credited for its words, not its markup.
	 */
	public function test_minimum_length_does_not_count_block_markup(): void {
		$content = '<!-- wp:paragraph --><p>one two three</p><!-- /wp:paragraph -->';

		$result = ( new MinimumLength( 50 ) )->check( $this->rest_context( [], $content ) );

		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_can_block_when_asked(): void {
		$result = ( new MinimumLength( 50, Severity::Block ) )->check( $this->rest_context( [], 'short' ) );

		$this->assertSame( Severity::Block, $result->severity );
	}
}
