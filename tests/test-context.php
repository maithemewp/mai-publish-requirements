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
}
