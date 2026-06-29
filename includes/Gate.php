<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * The publish gate. Evaluates the rules that apply to a post on the publish
 * transition and either aborts the REST save with an inline error or, on
 * non-REST paths that can't surface one, keeps the post as Pending + notice.
 *
 * Not `final`: the REST-detection seam (`is_rest_request()`) is overridden by a
 * test double so the non-REST backstop can be exercised without defining the
 * process-global REST_REQUEST constant.
 */
class Gate {

	/**
	 * Per-user transient prefix for the "kept as Pending" admin notice.
	 */
	private const NOTICE_TRANSIENT = 'mai_publish_requirements_blocked_';

	/**
	 * Wires the REST gate, the non-REST backstop, and the admin notice.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_gate' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'guard_non_rest' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'maybe_render_notice' ] );
	}

	/**
	 * Registers the REST insert gate for each gated post type.
	 */
	public function register_rest_gate(): void {
		foreach ( Rules::gated_post_types() as $post_type ) {
			add_filter( "rest_pre_insert_{$post_type}", [ $this, 'guard_rest' ], 10, 2 );
		}
	}

	/**
	 * Blocks a REST publish with an inline error when requirements are unmet.
	 *
	 * @param \stdClass|\WP_Error $prepared Post object prepared for insert/update.
	 * @param \WP_REST_Request    $request  The REST request.
	 * @return \stdClass|\WP_Error
	 */
	public function guard_rest( $prepared, \WP_REST_Request $request ) {
		if ( $prepared instanceof \WP_Error ) {
			return $prepared;
		}

		$context = Context::from_rest( $prepared, $request );

		if ( ! $context->is_publish_transition() ) {
			return $prepared;
		}

		$failures = $this->evaluate( $context );

		if ( $failures ) {
			return new \WP_Error(
				'mai_publish_requirements',
				self::format_message( $failures ),
				[ 'status' => 400 ]
			);
		}

		return $prepared;
	}

	/**
	 * Backstop for non-REST publishes: keeps the post as Pending when unmet.
	 *
	 * @param array<string,mixed> $data    Sanitized post data about to be written.
	 * @param array<string,mixed> $postarr Raw post array (includes ID on updates).
	 * @return array<string,mixed>
	 */
	public function guard_non_rest( array $data, array $postarr ): array {
		// REST publishes are handled authoritatively by guard_rest. Bailing here
		// also avoids a false demotion: during a REST publish the featured image
		// is attached after the insert, so it isn't visible at this point.
		if ( $this->is_rest_request() ) {
			return $data;
		}

		$post_type = (string) ( $data['post_type'] ?? '' );

		if ( ! in_array( $post_type, Rules::gated_post_types(), true ) ) {
			return $data;
		}

		$context = Context::from_save( $data, $postarr );

		if ( ! $context->is_publish_transition() ) {
			return $data;
		}

		$failures = $this->evaluate( $context );

		if ( $failures ) {
			$data['post_status'] = 'pending';
			$this->flag_notice( $failures );
		}

		return $data;
	}

	/**
	 * Whether the current save is being served as a REST request. Extracted as
	 * a seam so tests can exercise the non-REST path deterministically.
	 */
	protected function is_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Runs every rule that applies to the context's post type.
	 *
	 * @param Context $context
	 * @return string[] Unmet-requirement fragments; empty means all passed.
	 */
	public function evaluate( Context $context ): array {
		$failures = [];

		foreach ( Rules::for_post_type( $context->post_type ) as $rule ) {
			$message = $rule->check( $context );

			if ( null !== $message ) {
				$failures[] = $message;
			}
		}

		return $failures;
	}

	/**
	 * Composes the author-facing message from unmet-requirement fragments.
	 *
	 * @param string[] $fragments
	 */
	public static function format_message( array $fragments ): string {
		return sprintf(
			/* translators: %s: list of unmet publish requirements. */
			__( 'Before publishing this post, please %s.', 'mai-publish-requirements' ),
			implode( '; ', $fragments )
		);
	}

	/**
	 * Records, for the current user, that a publish was kept as Pending.
	 *
	 * @param string[] $fragments
	 */
	private function flag_notice( array $fragments ): void {
		$key      = self::NOTICE_TRANSIENT . get_current_user_id();
		$stored   = get_transient( $key );
		$existing = is_array( $stored ) ? $stored : [];

		$fragments = array_filter( array_map( 'trim', array_merge( $existing, $fragments ) ) );

		set_transient( $key, array_values( array_unique( $fragments ) ), MINUTE_IN_SECONDS );
	}

	/**
	 * Renders and clears the "kept as Pending" notice for the current user.
	 */
	public function maybe_render_notice(): void {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$stored = get_transient( $key );

		// Nothing flagged. Strict false check: a persistent object cache can hand
		// back '' rather than false, and (array) '' is [''] — which must not be
		// treated as a pending notice (that's the empty-bullet-on-every-page bug).
		if ( false === $stored ) {
			return;
		}

		// Clear on first read so a stale/odd value can't stick across page loads.
		delete_transient( $key );

		$fragments = array_filter( array_map( 'trim', (array) $stored ) );

		if ( ! $fragments ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'A post was kept as Pending.', 'mai-publish-requirements' ),
			esc_html( self::format_message( $fragments ) )
		);
	}
}
