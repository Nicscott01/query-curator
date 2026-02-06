<?php
/**
 * Custom post type registration for Query Curator.
 *
 * @package QueryCurator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the query_group custom post type and configures
 * the admin list table with custom columns.
 */
class Query_Curator_Post_Type {

	/**
	 * Constructor. Hooks into WordPress init and admin column filters.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'manage_query_group_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_query_group_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
	}

	/**
	 * Register the query_group post type.
	 *
	 * Creates an admin-only CPT for managing curated post lists.
	 * Not publicly queryable — data is consumed via get_query_group() helper.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Query Groups', 'query-curator' ),
			'singular_name'        => __( 'Query Group', 'query-curator' ),
			'menu_name'            => __( 'Query Groups', 'query-curator' ),
			'add_new'              => __( 'Add New', 'query-curator' ),
			'add_new_item'         => __( 'Add New Query Group', 'query-curator' ),
			'edit_item'            => __( 'Edit Query Group', 'query-curator' ),
			'new_item'             => __( 'New Query Group', 'query-curator' ),
			'view_item'            => __( 'View Query Group', 'query-curator' ),
			'search_items'         => __( 'Search Query Groups', 'query-curator' ),
			'not_found'            => __( 'No query groups found.', 'query-curator' ),
			'not_found_in_trash'   => __( 'No query groups found in Trash.', 'query-curator' ),
			'all_items'            => __( 'All Query Groups', 'query-curator' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-filter',
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => array( 'slug' => 'query-group' ),
			'capability_type'     => 'post',
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
		);

		register_post_type( 'query_group', $args );
	}

	/**
	 * Add custom columns to the query groups list table.
	 *
	 * Inserts "Curated Posts" count and "Post Type" columns after the title.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_custom_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Insert custom columns after the title column.
			if ( 'title' === $key ) {
				$new_columns['curated_count']     = __( 'Curated Posts', 'query-curator' );
				$new_columns['curated_post_type'] = __( 'Post Type', 'query-curator' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content for the list table.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_custom_columns( $column, $post_id ) {
		if ( 'curated_count' === $column ) {
			$post_ids = get_post_meta( $post_id, '_curated_post_ids', true );
			$count    = is_array( $post_ids ) ? count( $post_ids ) : 0;
			echo esc_html( $count );
		}

		if ( 'curated_post_type' === $column ) {
			$raw_params = get_post_meta( $post_id, '_query_params', true );
			$params     = qc_normalize_query_params( $raw_params );
			$types      = array();

			if ( ! empty( $params['queries'] ) ) {
				foreach ( $params['queries'] as $query ) {
					if ( ! empty( $query['post_type'] ) && ! in_array( $query['post_type'], $types, true ) ) {
						$types[] = $query['post_type'];
					}
				}
			}

			if ( ! empty( $types ) ) {
				$labels = array();
				foreach ( $types as $type_name ) {
					$pt_object = get_post_type_object( $type_name );
					$labels[]  = $pt_object ? $pt_object->labels->singular_name : $type_name;
				}
				echo esc_html( implode( ', ', $labels ) );
			} else {
				echo '<span class="qc-muted">&mdash;</span>';
			}
		}
	}
}
