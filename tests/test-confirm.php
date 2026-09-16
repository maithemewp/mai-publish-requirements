<?php

declare( strict_types=1 );

use Mai\PublishRequirements\Context;
use Mai\PublishRequirements\Gate;
use Mai\PublishRequirements\Result;
use Mai\PublishRequirements\Rules\Rule;
use Mai\PublishRequirements\Severity;

/**
 * Confirm: the block editor asks "Publish anyway?" before the post goes live,
 * and every other path treats the result as a warning.
 *
 * @covers \Mai\PublishRequirements\Gate
 */
class Test_Confirm extends WP_UnitTestCase {

	/** @var callable|null */
	private $registered = null;

	public function set_up(): void {
		parent::set_up();

		$this->registered = static fn ( array $rules ): array => array_merge(
			$rules,
			[
				new class() extends Rule {
					public function id(): string {
						return 'confirms_long_posts';
					}

					public function check( Context $context ): ?Result {
						return str_contains( $context->content(), 'LONG' )
							? new Result( Severity::Confirm, 'shorten this post' )
							: null;
					}
				},
			]
		);

		add_filter( 'mai_publish_requirements_rules', $this->registered );

		// Routes register on rest_api_init, which a fresh server fires.
		global $wp_rest_server;
		$wp_rest_server = null;
		rest_get_server();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	public function tear_down(): void {
		remove_filter( 'mai_publish_requirements_rules', $this->registered );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	private function check( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/' . Gate::REST_NAMESPACE . '/check' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	// --- the check route ------------------------------------------------------

	public function test_check_asks_before_a_draft_goes_live(): void {
		$post_id  = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$response = $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'LONG' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Gate::format_message( [ 'shorten this post' ], Severity::Confirm ), $response->get_data()['message'] );
	}

	public function test_check_reads_the_unsaved_content_not_the_stored_post(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft', 'post_content' => 'LONG' ] );

		$this->assertSame( '', $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'short' ] )->get_data()['message'] );
	}

	public function test_check_falls_back_to_stored_content_when_none_is_sent(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft', 'post_content' => 'LONG' ] );

		$this->assertNotSame( '', $this->check( [ 'id' => $post_id, 'status' => 'publish' ] )->get_data()['message'] );
	}

	public function test_check_is_silent_when_the_rule_passes(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->assertSame( '', $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'short' ] )->get_data()['message'] );
	}

	/**
	 * Updating a live post never asks. It would ask on every Update click for a
	 * post that went live long ago; the after-save warning covers updates.
	 */
	public function test_check_never_asks_on_an_update_to_a_live_post(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );

		$this->assertSame( '', $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'LONG' ] )->get_data()['message'] );
	}

	public function test_check_never_asks_for_a_draft_save(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->assertSame( '', $this->check( [ 'id' => $post_id, 'status' => 'draft', 'content' => 'LONG' ] )->get_data()['message'] );
	}

	public function test_check_ignores_warn_and_block_results(): void {
		remove_filter( 'mai_publish_requirements_rules', $this->registered );
		$this->registered = static fn ( array $rules ): array => array_merge(
			$rules,
			[
				new class() extends Rule {
					public function id(): string {
						return 'warns';
					}

					public function check( Context $context ): ?Result {
						return new Result( Severity::Warn, 'trim this down' );
					}
				},
			]
		);
		add_filter( 'mai_publish_requirements_rules', $this->registered );

		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->assertSame( '', $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'LONG' ] )->get_data()['message'] );
	}

	public function test_check_refuses_a_user_who_cannot_edit_the_post(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft', 'post_author' => self::factory()->user->create( [ 'role' => 'editor' ] ) ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertSame( 403, $this->check( [ 'id' => $post_id, 'status' => 'publish', 'content' => 'LONG' ] )->get_status() );
	}

	// --- every other path: a warning ------------------------------------------

	public function test_confirm_never_blocks_a_rest_publish(): void {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';

		$prepared = (object) [ 'ID' => 0, 'post_type' => 'post', 'post_content' => 'LONG' ];

		$this->assertSame( $prepared, ( new Gate() )->guard_rest( $prepared, $request ) );
	}

	public function test_confirm_is_a_warning_on_the_saved_response(): void {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';

		( new Gate() )->guard_rest( (object) [ 'ID' => 0, 'post_type' => 'post', 'post_content' => 'LONG' ], $request );

		$this->assertSame(
			[ Gate::format_message( [ 'shorten this post' ], Severity::Warn ) ],
			Gate::rest_warnings()
		);
	}

	public function test_confirm_never_demotes_a_non_rest_publish(): void {
		$data = ( new Test_Confirm_Non_Rest_Double() )->guard_non_rest(
			[ 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'LONG' ],
			[ 'ID' => 0 ]
		);

		$this->assertSame( 'publish', $data['post_status'] );
		$this->assertSame( [ 'shorten this post' ], get_transient( 'mai_publish_requirements_warn_' . get_current_user_id() ) );
	}

	// --- copy -----------------------------------------------------------------

	public function test_confirm_copy_asks_the_question(): void {
		$this->assertSame(
			'We recommend you shorten this post. Publish anyway?',
			Gate::format_message( [ 'shorten this post' ], Severity::Confirm )
		);
	}

	/**
	 * The block editor used to show the bare fragment, lowercase and with no
	 * sentence around it.
	 */
	public function test_the_rest_warning_is_a_full_sentence(): void {
		$request = new WP_REST_Request();
		$request['status'] = 'publish';

		( new Gate() )->guard_rest( (object) [ 'ID' => 0, 'post_type' => 'post', 'post_content' => 'LONG' ], $request );

		$this->assertStringStartsWith( 'We recommend you', Gate::rest_warnings()[0] );
	}
}

/**
 * Forces the non-REST path without defining REST_REQUEST process-wide.
 */
class Test_Confirm_Non_Rest_Double extends Gate {
	protected function is_rest_request(): bool {
		return false;
	}
}
