<?php
/**
 * AJAX handler for Query Curator.
 *
 * Provides six endpoints:
 * - qc_fetch_posts:     Run multi-query builder and return matching posts with card_map.
 * - qc_save_order:      Persist the curated post ID array to post meta.
 * - qc_search_posts:    Live-search posts by title for the "Add Posts" modal.
 * - qc_get_taxonomies:  Return taxonomies (with term counts) for a given post type.
 * - qc_get_meta_keys:   Return distinct meta keys for a given post type.
 * - qc_count_posts:     Lightweight count of matching posts for live preview.
 *
 * @package QueryCurator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for fetching posts, saving order, searching posts,
 * and dynamic taxonomy/meta key loading.
 */
class Query_Curator_Ajax_Handler {

	/**
	 * Allowed meta compare operators.
	 *
	 * @var array
	 */
	private $valid_compares = array( '=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS' );

	/**
	 * Allowed meta type values for ordering.
	 *
	 * @var array
	 */
	private $valid_meta_types = array( 'CHAR', 'NUMERIC', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'TIME' );

	/**
	 * Constructor. Register AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_qc_fetch_posts', array( $this, 'fetch_posts' ) );
		add_action( 'wp_ajax_qc_save_order', array( $this, 'save_order' ) );
		add_action( 'wp_ajax_qc_search_posts', array( $this, 'search_posts' ) );
		add_action( 'wp_ajax_qc_get_taxonomies', array( $this, 'get_taxonomies' ) );
		add_action( 'wp_ajax_qc_get_meta_keys', array( $this, 'get_meta_keys' ) );
		add_action( 'wp_ajax_qc_count_posts', array( $this, 'count_posts' ) );
	}

	/**
	 * Return taxonomies and their terms for a given post type.
	 *
	 * Used by the Query Builder to dynamically load taxonomy selects
	 * when the user changes the post type dropdown.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_taxonomies() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';

		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( __( 'Invalid post type.', 'query-curator' ) );
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$data       = array();

		foreach ( $taxonomies as $tax ) {
			// Skip non-public or internal taxonomies.
			if ( ! $tax->public && ! $tax->show_ui ) {
				continue;
			}

			$terms     = get_terms( array(
				'taxonomy'   => $tax->name,
				'hide_empty' => false,
			) );
			$term_data = array();

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_data[] = array(
						'id'    => $term->term_id,
						'name'  => $term->name,
						'count' => (int) $term->count,
					);
				}
			}

			$data[] = array(
				'name'  => $tax->name,
				'label' => $tax->labels->name,
				'terms' => $term_data,
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * Return distinct meta keys for a given post type.
	 *
	 * Queries the postmeta table for all unique meta keys associated
	 * with posts of the given type. Returns both public and private
	 * (underscore-prefixed) keys to support WooCommerce, ACF, etc.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_meta_keys() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';

		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( __( 'Invalid post type.', 'query-curator' ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s
				ORDER BY pm.meta_key ASC
				LIMIT 500",
				$post_type
			)
		);

		wp_send_json_success( is_array( $keys ) ? $keys : array() );
	}

	/**
	 * Return a lightweight count of matching posts for the live preview.
	 *
	 * Accepts the same multi-query format as fetch_posts() but only
	 * returns the deduplicated count — no post data, no meta saving.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function count_posts() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_queries = isset( $_POST['queries'] ) ? $_POST['queries'] : array();

		if ( empty( $raw_queries ) || ! is_array( $raw_queries ) ) {
			wp_send_json_success( array( 'count' => 0 ) );
		}

		$seen_ids = array();

		foreach ( $raw_queries as $raw_query ) {
			$result = $this->execute_single_query( $raw_query, 0 );

			foreach ( $result['post_ids'] as $pid ) {
				if ( ! isset( $seen_ids[ $pid ] ) ) {
					$seen_ids[ $pid ] = true;
				}
			}
		}

		wp_send_json_success( array( 'count' => count( $seen_ids ) ) );
	}

	/**
	 * Fetch posts based on multi-query builder parameters.
	 *
	 * Accepts an array of query card configurations, executes each
	 * as a separate get_posts() call, then unions the results (OR
	 * between cards, AND within each card's filters). Deduplicates
	 * by post ID across cards.
	 *
	 * Also persists the query parameters to _query_params meta for
	 * the "Refresh from Query" feature.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function fetch_posts() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		// Accept the new multi-query format.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_queries = isset( $_POST['queries'] ) ? $_POST['queries'] : array();

		if ( empty( $raw_queries ) || ! is_array( $raw_queries ) ) {
			wp_send_json_error( __( 'No query cards provided.', 'query-curator' ) );
		}

		$all_post_ids   = array();
		$seen_ids       = array();
		$saved_queries  = array();
		$card_map       = array();

		foreach ( $raw_queries as $card_index => $raw_query ) {
			$result = $this->execute_single_query( $raw_query, $post_id );

			// Track the sanitized query params for saving.
			$saved_queries[] = $result['params'];

			// Track which posts this card contributed (only new, unseen IDs).
			$card_contributed = array();

			// Union results — deduplicate by post ID.
			foreach ( $result['post_ids'] as $pid ) {
				if ( ! isset( $seen_ids[ $pid ] ) ) {
					$seen_ids[ $pid ]   = true;
					$all_post_ids[]     = $pid;
					$card_contributed[] = $pid;
				}
			}

			$card_map[ $card_index ] = $card_contributed;
		}

		// Fetch the full post objects for all collected IDs.
		$posts_data = array();
		if ( ! empty( $all_post_ids ) ) {
			$posts = get_posts( array(
				'post__in'       => $all_post_ids,
				'post_type'      => 'any',
				'posts_per_page' => count( $all_post_ids ),
				'orderby'        => 'post__in',
				'post_status'    => 'publish',
			) );

			$posts_data = $this->format_posts( $posts );
		}

		// Save query params so the user can "Refresh from Query" later.
		if ( $post_id ) {
			update_post_meta( $post_id, '_query_params', array(
				'queries' => $saved_queries,
			) );
		}

		wp_send_json_success( array(
			'posts'    => $posts_data,
			'card_map' => $card_map,
		) );
	}

	/**
	 * Execute a single query card and return post IDs.
	 *
	 * @param array $raw_query Raw query card data from POST.
	 * @param int   $post_id   The query group post ID.
	 * @return array Associative array with 'post_ids' (int[]) and 'params' (sanitized params).
	 */
	private function execute_single_query( $raw_query, $post_id ) {
		// Sanitize inputs.
		$post_type   = isset( $raw_query['post_type'] ) ? sanitize_key( $raw_query['post_type'] ) : 'post';
		$date_after  = isset( $raw_query['date_after'] ) ? sanitize_text_field( $raw_query['date_after'] ) : '';
		$date_before = isset( $raw_query['date_before'] ) ? sanitize_text_field( $raw_query['date_before'] ) : '';
		$limit       = isset( $raw_query['limit'] ) ? absint( $raw_query['limit'] ) : 100;

		// Cap the limit at 500.
		$limit = min( max( $limit, 1 ), 500 );

		// Ordering fields.
		$orderby          = isset( $raw_query['orderby'] ) ? sanitize_key( $raw_query['orderby'] ) : 'date';
		$order            = isset( $raw_query['order'] ) ? strtoupper( sanitize_text_field( $raw_query['order'] ) ) : 'DESC';
		$orderby_meta_key = isset( $raw_query['orderby_meta_key'] ) ? sanitize_text_field( $raw_query['orderby_meta_key'] ) : '';
		$meta_type        = isset( $raw_query['meta_type'] ) ? strtoupper( sanitize_text_field( $raw_query['meta_type'] ) ) : '';

		// Validate orderby.
		$valid_orderbys = array( 'date', 'title', 'meta_value', 'rand' );
		if ( ! in_array( $orderby, $valid_orderbys, true ) ) {
			$orderby = 'date';
		}

		// Validate order direction.
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Validate meta_type.
		if ( ! empty( $meta_type ) && ! in_array( $meta_type, $this->valid_meta_types, true ) ) {
			$meta_type = '';
		}

		// Validate post type exists.
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		// Build query arguments.
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		// Apply ordering.
		if ( 'rand' === $orderby ) {
			$args['orderby'] = 'rand';
		} elseif ( 'meta_value' === $orderby && ! empty( $orderby_meta_key ) ) {
			$args['meta_key'] = $orderby_meta_key;
			// Use meta_value_num for numeric types, meta_value for string types.
			$numeric_types = array( 'NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED' );
			if ( ! empty( $meta_type ) && in_array( $meta_type, $numeric_types, true ) ) {
				$args['orderby'] = 'meta_value_num';
			} else {
				$args['orderby'] = 'meta_value';
			}
			$args['order'] = $order;
			if ( ! empty( $meta_type ) ) {
				$args['meta_type'] = $meta_type;
			}
		} else {
			// 'date' or 'title'.
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		// --- Dynamic taxonomy query ---
		$raw_taxonomies  = isset( $raw_query['taxonomies'] ) && is_array( $raw_query['taxonomies'] ) ? $raw_query['taxonomies'] : array();
		$clean_taxonomies = array();
		$tax_query       = array();

		foreach ( $raw_taxonomies as $tax_name => $term_ids ) {
			$tax_name = sanitize_key( $tax_name );
			if ( ! taxonomy_exists( $tax_name ) ) {
				continue;
			}
			$term_ids = array_map( 'absint', (array) $term_ids );
			$term_ids = array_filter( $term_ids );
			if ( empty( $term_ids ) ) {
				continue;
			}
			$clean_taxonomies[ $tax_name ] = $term_ids;
			$tax_query[] = array(
				'taxonomy' => $tax_name,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		// --- Date query ---
		$date_query = array();
		if ( ! empty( $date_after ) ) {
			$date_query['after'] = $date_after;
		}
		if ( ! empty( $date_before ) ) {
			$date_query['before'] = $date_before;
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
			$args['date_query']     = array( $date_query );
		}

		// --- Multiple meta queries ---
		$raw_meta_queries  = isset( $raw_query['meta_queries'] ) && is_array( $raw_query['meta_queries'] ) ? $raw_query['meta_queries'] : array();
		$clean_meta_queries = array();
		$meta_query        = array();

		foreach ( $raw_meta_queries as $raw_mq ) {
			$mq_key     = isset( $raw_mq['key'] ) ? sanitize_text_field( $raw_mq['key'] ) : '';
			$mq_compare = isset( $raw_mq['compare'] ) ? sanitize_text_field( $raw_mq['compare'] ) : '=';
			$mq_value   = isset( $raw_mq['value'] ) ? sanitize_text_field( $raw_mq['value'] ) : '';

			if ( empty( $mq_key ) ) {
				continue;
			}

			// Validate compare operator.
			if ( ! in_array( $mq_compare, $this->valid_compares, true ) ) {
				$mq_compare = '=';
			}

			$clean_mq = array(
				'key'     => $mq_key,
				'compare' => $mq_compare,
				'value'   => $mq_value,
			);

			$clean_meta_queries[] = $clean_mq;

			// For EXISTS/NOT EXISTS, omit the value from WP_Query.
			$wp_mq = array(
				'key'     => $mq_key,
				'compare' => $mq_compare,
			);
			if ( ! in_array( $mq_compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$wp_mq['value'] = $mq_value;
			}

			$meta_query[] = $wp_mq;
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query;
		}

		/**
		 * Filter the query arguments before posts are fetched.
		 *
		 * @param array $args    The get_posts() arguments.
		 * @param int   $post_id The query group post ID.
		 */
		$args = apply_filters( 'query_curator_fetch_args', $args, $post_id );

		// Execute query — returns IDs only for performance.
		$post_ids = get_posts( $args );

		// Build sanitized params for saving.
		$params = array(
			'post_type'        => $post_type,
			'taxonomies'       => $clean_taxonomies,
			'date_after'       => $date_after,
			'date_before'      => $date_before,
			'meta_queries'     => $clean_meta_queries,
			'limit'            => $limit,
			'orderby'          => $orderby,
			'order'            => $order,
			'orderby_meta_key' => $orderby_meta_key,
			'meta_type'        => $meta_type,
		);

		return array(
			'post_ids' => $post_ids,
			'params'   => $params,
		);
	}

	/**
	 * Save the curated post order.
	 *
	 * Receives an ordered array of post IDs and stores it in the
	 * _curated_post_ids meta field for the given query group.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function save_order() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid post ID.', 'query-curator' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		// Verify this is a query_group post.
		if ( get_post_type( $post_id ) !== 'query_group' ) {
			wp_send_json_error( __( 'Invalid post type.', 'query-curator' ) );
		}

		// Sanitize post IDs — remove zeros and duplicates.
		$post_ids = isset( $_POST['post_ids'] ) ? (array) $_POST['post_ids'] : array();
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_values( array_filter( $post_ids ) );

		// Save to post meta.
		update_post_meta( $post_id, '_curated_post_ids', $post_ids );

		/**
		 * Fires after the curated post order is saved.
		 *
		 * @param int   $post_id  The query group post ID.
		 * @param array $post_ids The ordered array of curated post IDs.
		 */
		do_action( 'query_curator_order_saved', $post_id, $post_ids );

		wp_send_json_success( array(
			'message' => __( 'Posts saved.', 'query-curator' ),
			'count'   => count( $post_ids ),
		) );
	}

	/**
	 * Search posts by title for the "Add Posts" modal.
	 *
	 * Returns up to 20 posts matching the search term, excluding
	 * any posts that are already in the curated list.
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function search_posts() {
		check_ajax_referer( 'qc_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'query-curator' ) );
		}

		$search    = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'any';
		$exclude   = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) $_POST['exclude'] ) : array();

		if ( empty( $search ) ) {
			wp_send_json_error( __( 'Please enter a search term.', 'query-curator' ) );
		}

		// Allow searching across all public types or a specific one.
		if ( 'any' === $post_type ) {
			$search_types = get_post_types( array( 'public' => true ), 'names' );
			$search_types = array_diff( $search_types, array( 'attachment', 'query_group' ) );
			$search_types = array_values( $search_types );
		} else {
			$search_types = $post_type;
		}

		$args = array(
			'post_type'      => $search_types,
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			's'              => $search,
			'orderby'        => 'relevance',
		);

		// Exclude posts already in the curated list.
		if ( ! empty( $exclude ) ) {
			$args['post__not_in'] = $exclude;
		}

		$posts      = get_posts( $args );
		$posts_data = $this->format_posts( $posts );

		wp_send_json_success( $posts_data );
	}

	/**
	 * Format an array of WP_Post objects into a consistent data structure.
	 *
	 * Used by fetch_posts() and search_posts() to ensure a uniform
	 * JSON response shape.
	 *
	 * @param WP_Post[] $posts Array of post objects.
	 * @return array Array of associative arrays with id, title, type, thumbnail, permalink.
	 */
	private function format_posts( $posts ) {
		$data = array();

		foreach ( $posts as $p ) {
			$thumbnail     = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
			$post_type_obj = get_post_type_object( $p->post_type );
			$type_label    = $post_type_obj ? $post_type_obj->labels->singular_name : $p->post_type;

			$data[] = array(
				'id'        => $p->ID,
				'title'     => $p->post_title,
				'type'      => $type_label,
				'thumbnail' => $thumbnail ? $thumbnail : '',
				'permalink' => get_permalink( $p->ID ),
			);
		}

		return $data;
	}
}
