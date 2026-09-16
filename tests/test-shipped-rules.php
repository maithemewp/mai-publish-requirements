<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\Excerpt;
use Mai\PublishRequirements\Rules\EmbedCount;
use Mai\PublishRequirements\Rules\ImageAltText;
use Mai\PublishRequirements\Rules\ContentLength;
use Mai\PublishRequirements\Rules\TermCount;
use Mai\PublishRequirements\Rules\TitleLength;
use Mai\PublishRequirements\Severity;

/**
 * @covers \Mai\PublishRequirements\Rules\TermCount
 * @covers \Mai\PublishRequirements\Rules\ContentLength
 * @covers \Mai\PublishRequirements\Rules\Excerpt
 * @covers \Mai\PublishRequirements\Rules\TitleLength
 * @covers \Mai\PublishRequirements\Rules\ImageAltText
 * @covers \Mai\PublishRequirements\Rules\EmbedCount
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

	// --- TermCount ------------------------------------------------------------

	public function test_term_count_requires_at_least_one(): void {
		$result = ( new TermCount( min: 1 ) )->check( $this->rest_context() );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Block, $result->severity );
		$this->assertStringContainsString( 'choose a', $result->message );
	}

	public function test_term_count_passes_with_a_real_term(): void {
		$term = self::factory()->category->create( [ 'slug' => 'politics' ] );

		$this->assertNull( ( new TermCount( min: 1 ) )->check( $this->rest_context( [ 'categories' => [ $term ] ] ) ) );
	}

	/**
	 * The default category is what a post gets when nobody chose, so counting it
	 * would pass every unsorted post.
	 */
	public function test_term_count_ignores_the_default_category(): void {
		$default = (int) get_option( 'default_category' );

		$result = ( new TermCount( min: 1 ) )->check( $this->rest_context( [ 'categories' => [ $default ] ] ) );

		$this->assertInstanceOf( Result::class, $result );
	}

	public function test_term_count_caps_the_maximum(): void {
		$a = self::factory()->category->create( [ 'slug' => 'a' ] );
		$b = self::factory()->category->create( [ 'slug' => 'b' ] );

		$result = ( new TermCount( max: 1 ) )->check( $this->rest_context( [ 'categories' => [ $a, $b ] ] ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertStringContainsString( 'just one', $result->message );
	}

	/**
	 * min and max together: exactly one.
	 */
	public function test_term_count_accepts_a_range(): void {
		$a    = self::factory()->category->create( [ 'slug' => 'x' ] );
		$b    = self::factory()->category->create( [ 'slug' => 'y' ] );
		$rule = new TermCount( min: 1, max: 1 );

		$this->assertNull( $rule->check( $this->rest_context( [ 'categories' => [ $a ] ] ) ) );
		$this->assertInstanceOf( Result::class, $rule->check( $this->rest_context() ) );
		$this->assertInstanceOf( Result::class, $rule->check( $this->rest_context( [ 'categories' => [ $a, $b ] ] ) ) );
	}

	public function test_term_count_requiring_more_than_one_says_so(): void {
		$term   = self::factory()->category->create( [ 'slug' => 'solo' ] );
		$result = ( new TermCount( min: 2 ) )->check( $this->rest_context( [ 'categories' => [ $term ] ] ) );

		$this->assertStringContainsString( 'at least 2', $result->message );
		$this->assertStringContainsString( 'you have 1', $result->message );
	}

	public function test_term_count_with_no_bounds_never_complains(): void {
		$this->assertNull( ( new TermCount() )->check( $this->rest_context() ) );
	}

	/**
	 * Several of these get registered at once, so each must be addressable on
	 * its own by the post-types filter.
	 */
	public function test_term_count_ids_are_unique_per_taxonomy(): void {
		$this->assertSame( 'term_count_category', ( new TermCount() )->id() );
		$this->assertSame( 'term_count_post_tag', ( new TermCount( taxonomy: 'post_tag' ) )->id() );
	}

	public function test_term_count_works_on_another_taxonomy(): void {
		$tag = self::factory()->tag->create( [ 'slug' => 'news' ] );

		$rule    = new TermCount( Severity::Warn, 'post_tag', max: 1 );
		$request = [ 'tags' => [ $tag, self::factory()->tag->create( [ 'slug' => 'more' ] ) ] ];

		$result = $rule->check( $this->rest_context( $request ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
	}

	// --- ContentLength --------------------------------------------------------

	public function test_minimum_length_warns_when_short(): void {
		$result = ( new ContentLength( words: 50 ) )->check( $this->rest_context( [], 'three words only' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_passes_when_long_enough(): void {
		$content = implode( ' ', array_fill( 0, 60, 'word' ) );

		$this->assertNull( ( new ContentLength( words: 50 ) )->check( $this->rest_context( [], $content ) ) );
	}

	/**
	 * A block-built post is credited for its words, not its markup.
	 */
	public function test_minimum_length_does_not_count_block_markup(): void {
		$content = '<!-- wp:paragraph --><p>one two three</p><!-- /wp:paragraph -->';

		$result = ( new ContentLength( words: 50 ) )->check( $this->rest_context( [], $content ) );

		$this->assertStringContainsString( '3 words', $result->message );
	}

	public function test_minimum_length_can_block_when_asked(): void {
		$result = ( new ContentLength( Severity::Block, 50 ) )->check( $this->rest_context( [], 'short' ) );

		$this->assertSame( Severity::Block, $result->severity );
	}

	// --- Excerpt ------------------------------------------------------

	public function test_excerpt_required_warns_when_missing(): void {
		$result = ( new Excerpt() )->check( $this->rest_context( [], 'body' ) );

		$this->assertInstanceOf( Result::class, $result );
		$this->assertSame( Severity::Warn, $result->severity );
	}

	public function test_excerpt_required_passes_when_written(): void {
		$context = $this->rest_context( [], 'body', [ 'post_excerpt' => 'A short summary.' ] );

		$this->assertNull( ( new Excerpt() )->check( $context ) );
	}

	public function test_excerpt_of_only_whitespace_does_not_count(): void {
		$context = $this->rest_context( [], 'body', [ 'post_excerpt' => "  \n " ] );

		$this->assertInstanceOf( Result::class, ( new Excerpt() )->check( $context ) );
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

	// --- EmbedCount ----------------------------------------------------------

	public function test_embed_count_counts_every_kind_once(): void {
		$content = implode( "\n", [
			// Embed block: counted once, its URL inside is not counted again.
			'<!-- wp:embed {"url":"https://www.youtube.com/watch?v=abc","providerNameSlug":"youtube"} -->',
			'<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">',
			'https://www.youtube.com/watch?v=abc',
			'</div></figure>',
			'<!-- /wp:embed -->',
			// Pasted embed codes inside a Custom HTML block.
			'<!-- wp:html -->',
			'<iframe src="https://player.vimeo.com/video/1"></iframe>',
			'<iframe src="https://open.spotify.com/embed/track/1"></iframe>',
			'<blockquote class="bluesky-embed" data-bluesky-uri="at://did:plc:x/app.bsky.feed.post/1"><p>b</p></blockquote>',
			'<blockquote class="twitter-tweet"><p>t</p></blockquote>',
			'<blockquote class="instagram-media" data-instgrm-permalink="x"></blockquote>',
			'<blockquote class="tiktok-embed" cite="x"></blockquote>',
			'<!-- /wp:html -->',
		] );

		$this->assertSame( 7, ( new EmbedCount() )->count_embeds( $content ) );
	}

	public function test_embed_count_counts_auto_embedded_links_in_classic_content(): void {
		$classic = "Some words.\n\nhttps://www.youtube.com/watch?v=abc\n\nhttps://bsky.app/profile/x.bsky.social/post/1\n\n[embed]https://vimeo.com/1[/embed]";

		$this->assertSame( 3, ( new EmbedCount() )->count_embeds( $classic ) );
	}

	public function test_embed_count_ignores_links_no_provider_claims(): void {
		$this->assertSame( 0, ( new EmbedCount() )->count_embeds( "Read this:\n\nhttps://example.com/story\n" ) );
	}

	public function test_embed_count_ignores_a_link_inside_a_sentence(): void {
		$this->assertSame( 0, ( new EmbedCount() )->count_embeds( 'Watch https://www.youtube.com/watch?v=abc today.' ) );
	}

	public function test_embed_count_ignores_a_bare_link_in_a_paragraph_block(): void {
		$content = "<!-- wp:paragraph -->\n<p>\nhttps://www.youtube.com/watch?v=abc\n</p>\n<!-- /wp:paragraph -->";

		$this->assertSame( 0, ( new EmbedCount() )->count_embeds( $content ) );
	}

	public function test_embed_count_ignores_plain_quotes(): void {
		$quotes = str_repeat( '<blockquote><p>Someone said a thing.</p></blockquote>', 40 );

		$this->assertNull( ( new EmbedCount( max: 1 ) )->check( $this->rest_context( [], $quotes ) ) );
	}

	public function test_embed_count_counts_embeds_inside_nested_blocks(): void {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:html --><iframe src="https://x"></iframe><!-- /wp:html --><!-- wp:embed {"url":"https://vimeo.com/1"} --><figure></figure><!-- /wp:embed --></div><!-- /wp:group -->';

		$this->assertSame( 2, ( new EmbedCount() )->count_embeds( $content ) );
	}

	public function test_embed_count_allows_up_to_the_max(): void {
		$content = str_repeat( "<iframe src=\"https://x\"></iframe>\n", 12 );

		$this->assertNull( ( new EmbedCount() )->check( $this->rest_context( [], $content ) ) );
	}

	public function test_embed_count_reports_over_the_max_with_its_severity(): void {
		$content = str_repeat( "<iframe src=\"https://x\"></iframe>\n", 13 );
		$result  = ( new EmbedCount( Severity::Confirm ) )->check( $this->rest_context( [], $content ) );

		$this->assertSame( Severity::Confirm, $result->severity );
		$this->assertSame( 'use fewer embeds (13 in this post)', $result->message );
	}

	public function test_embed_count_takes_a_site_max(): void {
		$content = str_repeat( "<iframe src=\"https://x\"></iframe>\n", 25 );

		$this->assertNull( ( new EmbedCount( max: 25 ) )->check( $this->rest_context( [], $content ) ) );
	}
}
