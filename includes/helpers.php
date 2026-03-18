<?php
/**
 * Helper functions for Query Curator.
 *
 * @package QueryCurator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get curated post IDs from a query group.
 *
 * Retrieves the ordered array of post IDs stored in a query group.
 * Can accept either a post ID (int) or a post slug (string).
 *
 * @param int|string $query_group Query group post ID or slug.
 * @return array Array of post IDs in curated order, or empty array if not found.
 */
function get_query_group( $query_group ) {
	// Handle slug lookup.
	if ( ! is_numeric( $query_group ) ) {
		$post = get_page_by_path( sanitize_title( $query_group ), OBJECT, 'query_group' );
		if ( ! $post ) {
			return array();
		}
		$query_group = $post->ID;
	}

	$query_group = absint( $query_group );

	// Verify the post exists and is the correct type.
	if ( ! $query_group || get_post_type( $query_group ) !== 'query_group' ) {
		return array();
	}

	// Get the curated post IDs from meta.
	$post_ids = get_post_meta( $query_group, '_curated_post_ids', true );

	return is_array( $post_ids ) ? array_map( 'absint', $post_ids ) : array();
}

/**
 * Sanitize curated post IDs into a unique ordered integer array.
 *
 * Accepts either a comma-delimited string or an array of IDs.
 *
 * @param string|array $raw_post_ids Raw post IDs from the request.
 * @return array
 */
function qc_sanitize_curated_post_ids( $raw_post_ids ) {
	if ( is_string( $raw_post_ids ) ) {
		$raw_post_ids = explode( ',', $raw_post_ids );
	}

	$post_ids = array_map( 'absint', (array) $raw_post_ids );
	$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

	return $post_ids;
}

/**
 * Persist curated post IDs and clear the query group post cache.
 *
 * @param int   $post_id  Query group post ID.
 * @param array $post_ids Ordered curated post IDs.
 * @return void
 */
function qc_persist_curated_post_ids( $post_id, $post_ids ) {
	$post_id  = absint( $post_id );
	$post_ids = qc_sanitize_curated_post_ids( $post_ids );

	update_post_meta( $post_id, '_curated_post_ids', $post_ids );
	clean_post_cache( $post_id );

	/**
	 * Fires after the curated post order is saved.
	 *
	 * @param int   $post_id  The query group post ID.
	 * @param array $post_ids The ordered array of curated post IDs.
	 */
	do_action( 'query_curator_order_saved', $post_id, $post_ids );
}

/**
 * Normalize query params to the multi-query format.
 *
 * Converts the legacy flat format (single post_type, categories, tags,
 * single meta key) into the new multi-query structure with a `queries`
 * array. If the params are already in the new format, returns as-is.
 *
 * @param array $params Raw query params from post meta.
 * @return array Normalized params with `queries` key.
 */
function qc_normalize_query_params( $params ) {
	if ( ! is_array( $params ) || empty( $params ) ) {
		return array( 'queries' => array() );
	}

	// Already in new format.
	if ( isset( $params['queries'] ) ) {
		return $params;
	}

	// Legacy flat format — convert to multi-query.
	$query = array(
		'post_type'        => isset( $params['post_type'] ) ? $params['post_type'] : 'post',
		'taxonomies'       => array(),
		'date_after'       => isset( $params['date_after'] ) ? $params['date_after'] : '',
		'date_before'      => isset( $params['date_before'] ) ? $params['date_before'] : '',
		'meta_queries'     => array(),
		'limit'            => isset( $params['limit'] ) ? absint( $params['limit'] ) : 100,
		'orderby'          => isset( $params['orderby'] ) ? $params['orderby'] : 'date',
		'order'            => isset( $params['order'] ) ? $params['order'] : 'DESC',
		'orderby_meta_key' => isset( $params['orderby_meta_key'] ) ? $params['orderby_meta_key'] : '',
		'meta_type'        => isset( $params['meta_type'] ) ? $params['meta_type'] : '',
	);

	// Map old categories to taxonomies.category.
	if ( ! empty( $params['categories'] ) ) {
		$query['taxonomies']['category'] = array_map( 'absint', (array) $params['categories'] );
	}

	// Map old tags to taxonomies.post_tag.
	if ( ! empty( $params['tags'] ) ) {
		$query['taxonomies']['post_tag'] = array_map( 'absint', (array) $params['tags'] );
	}

	// Map old single meta to meta_queries array.
	if ( ! empty( $params['meta_key'] ) ) {
		$query['meta_queries'][] = array(
			'key'     => $params['meta_key'],
			'compare' => isset( $params['meta_compare'] ) ? $params['meta_compare'] : '=',
			'value'   => isset( $params['meta_value'] ) ? $params['meta_value'] : '',
		);
	}

	return array( 'queries' => array( $query ) );
}

/**
 * Get a ready-to-use WP_Query args array from a query group.
 *
 * Returns a complete arguments array that preserves curated order,
 * includes the correct post type, and sets posts_per_page to match.
 * Drop this directly into a WP_Query, Breakdance post loop, or any
 * builder that accepts a WP_Query args array.
 *
 * Usage:
 *   return get_query_group_args( 34 );
 *   return get_query_group_args( 'homepage-books' );
 *   $query = new WP_Query( get_query_group_args( 34 ) );
 *
 * @param int|string $query_group Query group post ID or slug.
 * @param array      $overrides   Optional. Extra WP_Query args to merge in (e.g. post_status).
 * @return array WP_Query-compatible args array. Returns a "return nothing" query if the group is empty.
 */
function get_query_group_args( $query_group, $overrides = array() ) {
	$ids = get_query_group( $query_group );

	if ( empty( $ids ) ) {
		// Return args that intentionally match nothing so the caller
		// doesn't get unexpected results from an empty post__in.
		return array_merge( array(
			'post__in' => array( 0 ),
			'posts_per_page' => 0,
		), $overrides );
	}

	// Use 'any' so the curated list works even when it spans
	// multiple post types (e.g. posts added manually via search).
	$args = array(
		'post_type'      => 'any',
		'post__in'       => $ids,
		'orderby'        => 'post__in',
		'posts_per_page' => count( $ids ),
	);

	return array_merge( $args, $overrides );
}
