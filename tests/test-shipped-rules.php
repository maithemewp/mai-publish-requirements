<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\CategoryRequired;
use Mai\PublishRequirements\Rules\ExcerptRequired;
use Mai\PublishRequirements\Rules\ImageAltText;
use Mai\PublishRequirements\Rules\MinimumLength;
use Mai\PublishRequirements\Rules\SingleCategory;
use Mai\PublishRequirements\Rules\TitleLength;
use Mai\PublishRequirements\Severity;

/**
 * @covers \Mai\PublishRequirements\Rules\CategoryRequired
 * @covers \Mai\PublishRequirements\Rules\SingleCategory
 * @covers \Mai\PublishRequirements\Rules\MinimumLength
 * @covers \Mai\PublishRequirements\Rules\ExcerptRequired
 * @covers \Mai\PublishRequirements\Rules\TitleLength
 * @covers \Mai\PublishRequirements\Rules\ImageAltText
 */
class Test_Shipped_Rules extends WP_UnitTestCase {

	private function rest_context( array $params = [], string $content = '', array $fields = [] ): Context {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';

		foreach ( $params as $key => $value ) {
			$request[ $key ] = $value;
		}

		$prepared = (object) array_merge(
			[ 'ID' => 0, 'post_type' => 'post', 'post_content' => $content ],
			$fields
		);

		return Context::from_rest( $prepared, $request );
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
		$result = ( new MinimumLength( words: 50 ) )->check( $this->rest_context( [], 'three words only' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_passes_when_long_enough(): void {
		$content = implode( ' ', array_fill( 0, 60, 'word' ) );

		$this->assertNull( ( new MinimumLength( words: 50 ) )->check( $this->rest_context( [], $content ) ) );
	}

	/**
	 * A block-built post is credited for its words, not its markup.
	 */
	public function test_minimum_length_does_not_count_block_markup(): void {
		$content = '<!-- wp:paragraph --><p>one two three</p><!-- /wp:paragraph -->';

		$result = ( new MinimumLength( words: 50 ) )->check( $this->rest_context( [], $content ) );

		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_can_block_when_asked(): void {
		$result = ( new MinimumLength( Severity::Block, 50 ) )->check( $this->rest_context( [], 'short' ) );

		$this->assertSame( Severity::Block, $result->severity );
	}

	// --- ExcerptRequired ------------------------------------------------------

	public function test_excerpt_required_warns_when_missing(): void {
		$result = ( new ExcerptRequired() )->check( $this->rest_context( [], 'body' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
	}

	public function test_excerpt_required_passes_when_written(): void {
		$context = $this->rest_context( [], 'body', [ 'post_excerpt' => 'A short summary.' ] );

		$this->assertNull( ( new ExcerptRequired() )->check( $context ) );
	}

	public function test_excerpt_of_only_whitespace_does_not_count(): void {
		$context = $this->rest_context( [], 'body', [ 'post_excerpt' => "  \n " ] );

		$this->assertInstanceOf( Result::class, ( new ExcerptRequired() )->check( $context ) );
	}

	// --- TitleLength ----------------------------------------------------------

	public function test_title_length_passes_when_short(): void {
		$context = $this->rest_context( [], '', [ 'post_title' => 'A sensible headline' ] );

		$this->assertNull( ( new TitleLength() )->check( $context ) );
	}

	public function test_title_length_warns_when_long(): void {
		$context = $this->rest_context( [], '', [ 'post_title' => str_repeat( 'a', 80 ) ] );

		$result = ( new TitleLength() )->check( $context );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertStringContainsString( '80', $result->message );
	}

	/**
	 * Counted in characters, not bytes, or an accented title would be called too
	 * long simply for being accented.
	 */
	public function test_title_length_counts_characters_not_bytes(): void {
		$context = $this->rest_context( [], '', [ 'post_title' => str_repeat( 'é', 40 ) ] );

		$this->assertNull( ( new TitleLength() )->check( $context ) );
	}

	public function test_title_length_threshold_is_configurable(): void {
		$context = $this->rest_context( [], '', [ 'post_title' => str_repeat( 'a', 30 ) ] );

		$this->assertNull( ( new TitleLength() )->check( $context ) );
		$this->assertInstanceOf( Result::class, ( new TitleLength( characters: 20 ) )->check( $context ) );
	}

	// --- ImageAltText ---------------------------------------------------------

	public function test_alt_text_is_silent_with_no_images(): void {
		$this->assertNull( ( new ImageAltText() )->check( $this->rest_context( [], 'just words' ) ) );
	}

	public function test_alt_text_warns_for_an_image_without_alt(): void {
		$result = ( new ImageAltText() )->check( $this->rest_context( [], '<img src="a.jpg">' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
	}

	public function test_alt_text_passes_when_present(): void {
		$context = $this->rest_context( [], '<img src="a.jpg" alt="A dog on a beach">' );

		$this->assertNull( ( new ImageAltText() )->check( $context ) );
	}

	/**
	 * alt="" is the correct way to mark a decorative image, so it must not be
	 * read as missing.
	 */
	public function test_an_empty_alt_is_deliberate_and_passes(): void {
		$this->assertNull( ( new ImageAltText() )->check( $this->rest_context( [], '<img src="a.jpg" alt="">' ) ) );
	}

	public function test_alt_text_counts_how_many_are_missing(): void {
		$content = '<img src="a.jpg"><img src="b.jpg" alt="fine"><img src="c.jpg">';

		$result = ( new ImageAltText() )->check( $this->rest_context( [], $content ) );

		$this->assertStringContainsString( '2', $result->message );
	}
}
