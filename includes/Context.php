<?php

declare( strict_types=1 );

namespace Mai\PublishRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * A normalized view of a post being saved, built from either the REST request
 * (block editor / REST API) or the raw $data/$postarr arrays (classic editor,
 * Quick Edit, bulk edit, programmatic). Rules read from this and never branch
 * on which path they came through.
 */
final class Context {

	private function __construct(
		public readonly int $post_id,
		public readonly string $post_type,
		public readonly string $new_status,
		public readonly string $old_status,
		public readonly bool $is_rest,
		private readonly ?\WP_REST_Request $request,
		private readonly array $postarr
	) {}

	/**
	 * Builds the context for a REST insert/update.
	 *
	 * @param object           $prepared Post object prepared for insert/update.
	 * @param \WP_REST_Request $request  The REST request.
	 */
	public static function from_rest( object $prepared, \WP_REST_Request $request ): self {
		$post_id    = (int) ( $prepared->ID ?? 0 );
		$old_status = $post_id ? (string) get_post_status( $post_id ) : '';
		$post_type  = (string) ( $prepared->post_type ?? ( $post_id ? get_post_type( $post_id ) : 'post' ) );
		$new_status = isset( $request['status'] ) ? (string) $request['status'] : $old_status;

		return new self( $post_id, $post_type, $new_status, $old_status, true, $request, [] );
	}

	/**
	 * Builds the context for a non-REST save (wp_insert_post_data).
	 *
	 * @param array<string,mixed> $data    Sanitized post data about to be written.
	 * @param array<string,mixed> $postarr Raw post array (includes ID on updates).
	 */
	public static function from_save( array $data, array $postarr ): self {
		$post_id    = (int) ( $postarr['ID'] ?? 0 );
		$old_status = $post_id ? (string) get_post_status( $post_id ) : '';
		$post_type  = (string) ( $data['post_type'] ?? '' );
		$new_status = (string) ( $data['post_status'] ?? '' );

		return new self( $post_id, $post_type, $new_status, $old_status, false, null, $postarr );
	}

	/**
	 * Post statuses that make a post publicly live.
	 */
	private const LIVE_STATUSES = [ 'publish', 'future' ];

	/**
	 * True when the post is moving into a live status from a non-live one.
	 */
	public function is_publish_transition(): bool {
		return in_array( $this->new_status, self::LIVE_STATUSES, true )
			&& ! in_array( $this->old_status, self::LIVE_STATUSES, true );
	}

	/**
	 * The featured image id being set on this save (request value first, then
	 * incoming postarr, then the post's existing thumbnail). 0 when none.
	 */
	public function featured_image_id(): int {
		if ( $this->is_rest && $this->request && isset( $this->request['featured_media'] ) ) {
			return (int) $this->request['featured_media'];
		}

		if ( isset( $this->postarr['_thumbnail_id'] ) ) {
			return (int) $this->postarr['_thumbnail_id'];
		}

		return $this->post_id ? (int) get_post_thumbnail_id( $this->post_id ) : 0;
	}
}
