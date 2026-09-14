<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;

/**
 * @covers \Mai\PublishRequirements\Context
 */
class Test_Context extends WP_UnitTestCase {

	public function test_new_post_going_to_publish_is_a_transition(): void {
		$context = Context::from_save( [ 'post_type' => 'post', 'post_status' => 'publish' ], [] );

		$this->assertTrue( $context->is_publish_transition() );
	}

	public function test_scheduling_from_draft_is_a_transition(): void {
		$context = Context::from_save( [ 'post_type' => 'post', 'post_status' => 'future' ], [] );

		$this->assertTrue( $context->is_publish_transition() );
	}

	public function test_draft_to_draft_is_not_a_transition(): void {
		$context = Context::from_save( [ 'post_type' => 'post', 'post_status' => 'draft' ], [] );

		$this->assertFalse( $context->is_publish_transition() );
	}

	public function test_resaving_an_already_live_post_is_not_a_transition(): void {
		// A published page — pages aren't gated, so the gate won't demote it.
		$post_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );

		$context = Context::from_save( [ 'post_type' => 'page', 'post_status' => 'publish' ], [ 'ID' => $post_id ] );

		$this->assertFalse( $context->is_publish_transition() );
	}

	public function test_featured_image_id_reads_the_rest_request_first(): void {
		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post' ];
		$request  = new WP_REST_Request();
		$request['featured_media'] = 42;

		$context = Context::from_rest( $prepared, $request );

		$this->assertSame( 42, $context->featured_image_id() );
	}

	public function test_featured_image_id_falls_back_to_existing_thumbnail(): void {
		$post_id    = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$attachment = self::factory()->attachment->create();
		// set_post_thumbnail() requires a real image; factory attachments have no
		// file, so set the meta directly — our code only reads _thumbnail_id.
		update_post_meta( $post_id, '_thumbnail_id', $attachment );

		$context = Context::from_save( [ 'post_type' => 'page', 'post_status' => 'publish' ], [ 'ID' => $post_id ] );

		$this->assertSame( $attachment, $context->featured_image_id() );
	}

	public function test_featured_image_id_is_zero_when_none(): void {
		$context = Context::from_save( [ 'post_type' => 'post', 'post_status' => 'publish' ], [] );

		$this->assertSame( 0, $context->featured_image_id() );
	}

	// --- content() ------------------------------------------------------------

	public function test_content_comes_from_the_prepared_post_on_rest(): void {
		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post', 'post_content' => 'hello world' ];
		$request  = new WP_REST_Request();

		$this->assertSame( 'hello world', Context::from_rest( $prepared, $request )->content() );
	}

	/**
	 * A partial update that omits content leaves the stored body alone, so a rule
	 * measuring length must see that body rather than an empty string.
	 */
	public function test_content_falls_back_to_the_stored_post(): void {
		$post_id = self::factory()->post->create( [ 'post_content' => 'stored body' ] );
		$request = new WP_REST_Request();

		$context = Context::from_rest( (object) [ 'ID' => $post_id, 'post_type' => 'post' ], $request );

		$this->assertSame( 'stored body', $context->content() );
	}

	public function test_content_comes_from_data_on_a_classic_save(): void {
		$context = Context::from_save(
			[ 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'classic body' ],
			[ 'ID' => 0 ]
		);

		$this->assertSame( 'classic body', $context->content() );
	}

	public function test_content_is_empty_for_a_new_post_with_none(): void {
		$context = Context::from_save( [ 'post_type' => 'post' ], [ 'ID' => 0 ] );

		$this->assertSame( '', $context->content() );
	}

	// --- term_slugs() ---------------------------------------------------------

	/**
	 * REST sends term IDs under the taxonomy's rest_base, which is 'categories'
	 * for 'category' and not the taxonomy name.
	 */
	public function test_term_slugs_reads_rest_base_and_resolves_ids(): void {
		$term_id = self::factory()->category->create( [ 'slug' => 'politics' ] );
		$request = new WP_REST_Request();
		$request['categories'] = [ $term_id ];

		$context = Context::from_rest( (object) [ 'ID' => 0, 'post_type' => 'post' ], $request );

		$this->assertSame( [ 'politics' ], $context->term_slugs( 'category' ) );
	}

	public function test_term_slugs_reads_post_category_on_a_classic_save(): void {
		$term_id = self::factory()->category->create( [ 'slug' => 'sport' ] );

		$context = Context::from_save(
			[ 'post_type' => 'post' ],
			[ 'ID' => 0, 'post_category' => [ $term_id ] ]
		);

		$this->assertSame( [ 'sport' ], $context->term_slugs( 'category' ) );
	}

	public function test_term_slugs_reads_tax_input(): void {
		$term_id = self::factory()->category->create( [ 'slug' => 'culture' ] );

		$context = Context::from_save(
			[ 'post_type' => 'post' ],
			[ 'ID' => 0, 'tax_input' => [ 'category' => [ $term_id ] ] ]
		);

		$this->assertSame( [ 'culture' ], $context->term_slugs( 'category' ) );
	}

	/**
	 * A save that never mentions terms keeps the stored ones, which is different
	 * from a save that clears them.
	 */
	public function test_term_slugs_falls_back_to_stored_terms(): void {
		$term_id = self::factory()->category->create( [ 'slug' => 'archive' ] );
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, [ $term_id ], 'category' );

		$context = Context::from_save( [ 'post_type' => 'post' ], [ 'ID' => $post_id ] );

		$this->assertContains( 'archive', $context->term_slugs( 'category' ) );
	}

	public function test_term_slugs_drops_ids_that_do_not_resolve(): void {
		$request = new WP_REST_Request();
		$request['categories'] = [ 999999 ];

		$context = Context::from_rest( (object) [ 'ID' => 0, 'post_type' => 'post' ], $request );

		$this->assertSame( [], $context->term_slugs( 'category' ) );
	}
}
