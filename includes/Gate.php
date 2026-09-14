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
	 * Per-user transient prefix for the admin notices, one per severity.
	 */
	private const NOTICE_TRANSIENT = 'mai_publish_requirements_';

	/**
	 * REST field carrying warnings back to the block editor.
	 */
	public const REST_FIELD = 'mai_publish_warnings';

	/**
	 * Warnings raised during THIS request's insert, waiting for the response.
	 *
	 * Request-scoped static rather than a transient: the guard and the response
	 * filter are two points in one request, so nothing needs to outlive it, and
	 * a transient would leak a warning onto somebody else's next save.
	 *
	 * @var string[]
	 */
	private static array $pending_warnings = [];

	/**
	 * Wires the REST gate, the non-REST backstop, and the admin notice.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_gate' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_field' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'guard_non_rest' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'maybe_render_notice' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_script' ] );
	}

	/**
	 * Exposes this request's warnings on the post's REST response.
	 *
	 * Read-only and request-scoped: it reports what the save just raised, so it
	 * is empty on an ordinary GET. The editor script reads it from the response
	 * to the save it just made.
	 */
	public function register_rest_field(): void {
		foreach ( Rules::gated_post_types() as $post_type ) {
			register_rest_field(
				$post_type,
				self::REST_FIELD,
				[
					'get_callback' => static fn (): array => self::$pending_warnings,
					'schema'       => [
						'description' => __( 'Publish warnings raised by the save that produced this response.', 'mai-publish-requirements' ),
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'context'     => [ 'edit' ],
						'readonly'    => true,
					],
				]
			);
		}
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

		// Any save that leaves the post live is examined, not only the moment it
		// first goes live. Blocks still apply on the transition alone, below.
		if ( ! $context->is_live_save() ) {
			return $prepared;
		}

		$results = $this->evaluate( $context );

		// Refusing an update would unpublish a live post over a rule it has been
		// failing for months, so only the transition can be blocked. A warning on
		// an update is the point of the wider check.
		$blocks = $context->is_publish_transition() ? self::messages( $results, Severity::Block ) : [];

		if ( $blocks ) {
			return new \WP_Error(
				'mai_publish_requirements',
				self::format_message( $blocks ),
				[ 'status' => 400 ]
			);
		}

		// Warnings cannot be returned from here: this filter can hand back the
		// prepared post or a WP_Error that fails the whole save, and there is no
		// third answer meaning "saved, but read this". They are stashed for the
		// REST response instead, which is the one channel that reaches the block
		// editor after a successful save.
		self::$pending_warnings = self::messages( $results, Severity::Warn );

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

		if ( ! $context->is_live_save() ) {
			return $data;
		}

		$results = $this->evaluate( $context );

		// Same rule as the REST path: only a transition can be demoted.
		$blocks = $context->is_publish_transition() ? self::messages( $results, Severity::Block ) : [];
		$warns  = self::messages( $results, Severity::Warn );

		if ( $blocks ) {
			$data['post_status'] = 'pending';
			$this->flag_notice( $blocks, Severity::Block );
		}

		// A warning never changes post_status. It is the same admin notice,
		// styled as a warning, and it is worth showing even when a block already
		// demoted the post: the two are different problems.
		if ( $warns ) {
			$this->flag_notice( $warns, Severity::Warn );
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
	 * @return Result[] Unmet requirements; empty means all passed.
	 */
	public function evaluate( Context $context ): array {
		$results = [];

		foreach ( Rules::for_post_type( $context->post_type ) as $rule ) {
			$result = $rule->check( $context );

			if ( $result instanceof Result ) {
				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * The messages from results of one severity.
	 *
	 * @param Result[] $results
	 * @return string[]
	 */
	private static function messages( array $results, Severity $severity ): array {
		$messages = [];

		foreach ( $results as $result ) {
			if ( $severity === $result->severity ) {
				$messages[] = $result->message;
			}
		}

		return $messages;
	}

	/**
	 * Composes the author-facing message from unmet-requirement fragments.
	 *
	 * @param string[] $fragments
	 */
	public static function format_message( array $fragments, Severity $severity = Severity::Block ): string {
		$list = implode( '; ', $fragments );

		return Severity::Block === $severity
			? sprintf(
				/* translators: %s: list of unmet publish requirements. */
				__( 'Before publishing this post, please %s.', 'mai-publish-requirements' ),
				$list
			)
			// Deliberately does NOT claim the post was published. A warning and a
			// block can both come from one save, and on that save the post was
			// demoted to Pending, so "this post was published, but..." would be
			// telling the author the opposite of what happened.
			: sprintf(
				/* translators: %s: list of publish warnings. */
				__( 'You may also want to %s.', 'mai-publish-requirements' ),
				$list
			);
	}

	/**
	 * Records, for the current user, what to say on the next admin page load.
	 *
	 * Keyed by severity as well as user, so a demotion and a warning from the
	 * same save stay two separate notices rather than merging into one sentence
	 * that claims the post was demoted for both reasons.
	 *
	 * @param string[] $fragments
	 */
	private function flag_notice( array $fragments, Severity $severity ): void {
		$key      = self::NOTICE_TRANSIENT . $severity->value . '_' . get_current_user_id();
		$stored   = get_transient( $key );
		$existing = is_array( $stored ) ? $stored : [];

		$fragments = array_filter( array_map( 'trim', array_merge( $existing, $fragments ) ) );

		set_transient( $key, array_values( array_unique( $fragments ) ), MINUTE_IN_SECONDS );
	}

	/**
	 * Renders and clears the "kept as Pending" notice for the current user.
	 */
	public function maybe_render_notice(): void {
		foreach ( Severity::cases() as $severity ) {
			$key = self::NOTICE_TRANSIENT . $severity->value . '_' . get_current_user_id();

			// (array) handles both false → [] and a stray '' → [''] (a persistent
			// object cache can return '' for a missing key); array_filter drops the
			// empties. No real reasons → nothing to show.
			$fragments = array_filter( array_map( 'trim', (array) get_transient( $key ) ) );

			if ( ! $fragments ) {
				continue;
			}

			delete_transient( $key );

			$is_block = Severity::Block === $severity;

			printf(
				'<div class="notice %1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
				$is_block ? 'notice-error' : 'notice-warning',
				// The warning heading does not claim publication either, for the
				// same reason the body does not: the two notices render
				// independently, and a save that raised both ended in Pending.
				esc_html(
					$is_block
						? __( 'A post was kept as Pending.', 'mai-publish-requirements' )
						: __( 'Publish warnings.', 'mai-publish-requirements' )
				),
				esc_html( self::format_message( $fragments, $severity ) )
			);
		}
	}

	/**
	 * Loads the script that turns a warning on the REST response into a notice.
	 *
	 * Only where a rule actually applies, so an editor for an ungated post type
	 * downloads nothing. wp-data and wp-notices are the two stores it touches;
	 * both are core and already present in the editor, so the dependency is
	 * declared rather than bundled.
	 */
	public function enqueue_editor_script(): void {
		$post_type = get_post_type();

		if ( ! $post_type || ! in_array( $post_type, Rules::gated_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'mai-publish-requirements-editor',
			plugins_url( 'assets/js/editor-warnings.js', MAI_PUBLISH_REQUIREMENTS_FILE ),
			[ 'wp-data' ],
			MAI_PUBLISH_REQUIREMENTS_VERSION,
			true
		);
	}
}
