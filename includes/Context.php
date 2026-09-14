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
		private readonly array $postarr,
		private readonly ?string $incoming_content = null
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

		// post_content off $prepared, NOT off the request. Core has already
		// normalised it here, while $request['content'] arrives either as a string
		// or as [ 'raw' => ... ] depending on the caller. Null when the request
		// omitted content (a partial update), which content() reads as "unchanged"
		// and answers from the stored post.
		$content = property_exists( $prepared, 'post_content' ) ? (string) $prepared->post_content : null;

		return new self( $post_id, $post_type, $new_status, $old_status, true, $request, [], $content );
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

		$content = array_key_exists( 'post_content', $data ) ? (string) $data['post_content'] : null;

		return new self( $post_id, $post_type, $new_status, $old_status, false, null, $postarr, $content );
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

	/**
	 * The post body being saved, falling back to what is stored.
	 *
	 * A partial update that does not send content leaves the stored body in
	 * place, so a rule measuring length or counting embeds has to see that body
	 * rather than an empty string, or every such save would look like a post
	 * that had suddenly been emptied.
	 */
	public function content(): string {
		if ( null !== $this->incoming_content ) {
			return $this->incoming_content;
		}

		return $this->post_id ? (string) get_post_field( 'post_content', $this->post_id ) : '';
	}

	/**
	 * Term slugs being set on this save, for one taxonomy.
	 *
	 * Three sources, because the same assignment arrives three different shapes:
	 * a REST request carries term IDs under the taxonomy's `rest_base`, which is
	 * `categories` for `category` and not the taxonomy name; a classic save
	 * carries them in `tax_input` (ids or names) or `post_category` (ids); and a
	 * save that touches neither leaves the stored terms alone.
	 *
	 * Normalised to slugs so a rule never has to know which path it came through,
	 * which is this class's whole job.
	 *
	 * @return string[]
	 */
	public function term_slugs( string $taxonomy ): array {
		$raw = $this->incoming_terms( $taxonomy );

		if ( null === $raw ) {
			return $this->post_id
				? array_values( array_map( 'strval', (array) wp_get_object_terms( $this->post_id, $taxonomy, [ 'fields' => 'slugs' ] ) ) )
				: [];
		}

		$slugs = [];

		foreach ( $raw as $value ) {
			// tax_input for a non-hierarchical taxonomy holds NAMES, everything
			// else holds ids, and a term that does not resolve is dropped rather
			// than guessed at.
			$term = is_numeric( $value )
				? get_term( (int) $value, $taxonomy )
				: get_term_by( 'name', (string) $value, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				$slugs[] = $term->slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * The raw term values this save is assigning, or null when it assigns none.
	 *
	 * Null and an empty array mean different things: null is "this save did not
	 * mention terms, keep what is stored", while [] is "this save cleared them".
	 *
	 * @return array<int,int|string>|null
	 */
	private function incoming_terms( string $taxonomy ): ?array {
		if ( $this->is_rest && $this->request ) {
			$tax  = get_taxonomy( $taxonomy );
			$base = ( $tax && $tax->rest_base ) ? $tax->rest_base : $taxonomy;

			return isset( $this->request[ $base ] ) ? (array) $this->request[ $base ] : null;
		}

		if ( isset( $this->postarr['tax_input'][ $taxonomy ] ) ) {
			return (array) $this->postarr['tax_input'][ $taxonomy ];
		}

		// Core's own special case: the category box posts here, not to tax_input.
		if ( 'category' === $taxonomy && isset( $this->postarr['post_category'] ) ) {
			return (array) $this->postarr['post_category'];
		}

		return null;
	}
}
