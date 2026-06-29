<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Gate;
use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Settings;

/**
 * @covers \Mai\PublishRequirements\Gate
 */
class Test_Gate extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Settings::flush_cache();
	}

	/**
	 * A genuinely-published post that passed the gate (draft → add image → publish).
	 */
	private function published_post_with_image(): int {
		$post_id    = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment = self::factory()->attachment->create();
		update_post_meta( $post_id, '_thumbnail_id', $attachment );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );

		return $post_id;
	}

	private function publish_request( array $params = [] ): WP_REST_Request {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';
		foreach ( $params as $key => $value ) {
			$request[ $key ] = $value;
		}
		return $request;
	}

	// --- evaluate() -----------------------------------------------------------

	public function test_evaluate_reports_a_failure_when_post_has_no_image(): void {
		$context = Context::from_rest( (object) [ 'ID' => 0, 'post_type' => 'post' ], $this->publish_request() );

		$this->assertCount( 1, ( new Gate() )->evaluate( $context ) );
	}

	public function test_evaluate_passes_when_image_present(): void {
		$context = Context::from_rest( (object) [ 'ID' => 0, 'post_type' => 'post' ], $this->publish_request( [ 'featured_media' => 9 ] ) );

		$this->assertSame( [], ( new Gate() )->evaluate( $context ) );
	}

	public function test_evaluate_ignores_ungated_post_types(): void {
		$context = Context::from_rest( (object) [ 'ID' => 0, 'post_type' => 'page' ], $this->publish_request() );

		$this->assertSame( [], ( new Gate() )->evaluate( $context ) );
	}

	// --- guard_rest() ---------------------------------------------------------

	public function test_guard_rest_blocks_publish_without_image(): void {
		$result = ( new Gate() )->guard_rest( (object) [ 'ID' => 0, 'post_type' => 'post' ], $this->publish_request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->get_error_data()['status'] ?? null );
	}

	public function test_guard_rest_allows_publish_with_image(): void {
		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post' ];

		$result = ( new Gate() )->guard_rest( $prepared, $this->publish_request( [ 'featured_media' => 9 ] ) );

		$this->assertSame( $prepared, $result );
	}

	public function test_guard_rest_does_not_police_already_live_posts(): void {
		$post_id  = $this->published_post_with_image();
		delete_post_thumbnail( $post_id );
		$prepared = (object) [ 'ID' => $post_id, 'post_type' => 'post' ];

		// Editing a live post (publish → publish) is not a transition, so it passes.
		$result = ( new Gate() )->guard_rest( $prepared, $this->publish_request() );

		$this->assertNotInstanceOf( WP_Error::class, $result );
	}

	public function test_guard_rest_passes_through_an_incoming_wp_error(): void {
		$error  = new WP_Error( 'prior', 'prior failure' );
		$result = ( new Gate() )->guard_rest( $error, $this->publish_request() );

		$this->assertSame( $error, $result );
	}

	// --- register_rest_gate() -------------------------------------------------

	public function test_rest_gate_hooks_gated_types_only(): void {
		$gate = new Gate();
		$gate->register_rest_gate();

		$this->assertNotFalse( has_filter( 'rest_pre_insert_post', [ $gate, 'guard_rest' ] ) );
		$this->assertFalse( has_filter( 'rest_pre_insert_page', [ $gate, 'guard_rest' ] ) );
	}

	// --- guard_non_rest() (real save path) ------------------------------------

	public function test_publishing_without_image_is_kept_as_pending(): void {
		$post_id = wp_insert_post( [ 'post_title' => 'No image', 'post_type' => 'post', 'post_status' => 'publish' ] );

		$this->assertSame( 'pending', get_post_status( $post_id ) );
	}

	public function test_publishing_with_image_goes_live(): void {
		$post_id    = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment = self::factory()->attachment->create();
		update_post_meta( $post_id, '_thumbnail_id', $attachment );

		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	public function test_editing_an_already_live_post_without_image_is_allowed(): void {
		$post_id = $this->published_post_with_image();
		delete_post_thumbnail( $post_id );

		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish', 'post_content' => 'edited' ] );

		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	// --- admin notice rendering --------------------------------------------

	private function notice_output(): string {
		ob_start();
		( new Gate() )->maybe_render_notice();
		return (string) ob_get_clean();
	}

	public function test_notice_renders_for_a_real_reason(): void {
		set_transient( 'mai_publish_requirements_blocked_' . get_current_user_id(), [ 'set a featured image' ], MINUTE_IN_SECONDS );

		$out = $this->notice_output();

		$this->assertStringContainsString( 'featured image', $out );
	}

	public function test_notice_is_silent_for_an_empty_string_transient(): void {
		// A persistent object cache can hand back '' instead of false; (array) ''
		// is [''], which must not render an empty-bullet notice on every page.
		set_transient( 'mai_publish_requirements_blocked_' . get_current_user_id(), '', MINUTE_IN_SECONDS );

		$this->assertSame( '', $this->notice_output() );
	}

	public function test_notice_never_emits_an_empty_list_item(): void {
		set_transient( 'mai_publish_requirements_blocked_' . get_current_user_id(), [ '', 'set a featured image', '' ], MINUTE_IN_SECONDS );

		$this->assertStringNotContainsString( '<li></li>', $this->notice_output() );
	}

	public function test_non_rest_guard_bails_during_a_rest_request(): void {
		$gate = new class() extends Gate {
			protected function is_rest_request(): bool {
				return true;
			}
		};

		$data = $gate->guard_non_rest( [ 'post_type' => 'post', 'post_status' => 'publish' ], [] );

		$this->assertSame( 'publish', $data['post_status'] );
	}
}
