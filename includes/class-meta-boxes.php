<?php
/**
 * Meta box registration and rendering for Query Curator.
 *
 * Provides three meta boxes on the query_group edit screen:
 * 1. Query Builder — JS-rendered query cards with dynamic filters.
 * 2. Curated Posts — sortable grid of results with locked/new indicators.
 * 3. Code Snippets — sidebar with ready-to-copy PHP helpers.
 *
 * Also registers the contextual help tab and enqueues admin assets.
 *
 * @package QueryCurator
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the query builder and results grid meta boxes.
 */
class Query_Curator_Meta_Boxes {

	/**
	 * Constructor. Hooks into WordPress.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_head', array( $this, 'add_help_tab' ) );
	}

	/**
	 * Register meta boxes for the query_group post type.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'qc_query_builder',
			__( 'Query Builder', 'query-curator' ),
			array( $this, 'render_query_builder' ),
			'query_group',
			'normal',
			'high'
		);

		add_meta_box(
			'qc_results_grid',
			__( 'Curated Posts', 'query-curator' ),
			array( $this, 'render_results_grid' ),
			'query_group',
			'normal',
			'default'
		);

		add_meta_box(
			'qc_code_snippets',
			__( 'Code Snippets', 'query-curator' ),
			array( $this, 'render_code_snippets' ),
			'query_group',
			'side',
			'default'
		);
	}

	/**
	 * Enqueue admin assets on the query_group edit screen only.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'query_group' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style(
			'qc-admin',
			QC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QC_VERSION
		);

		wp_enqueue_script(
			'qc-admin',
			QC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			QC_VERSION,
			true
		);

		// Get saved data.
		$current_post_id = get_the_ID();
		$saved_queries   = array( 'queries' => array() );
		$saved_curated   = array();

		if ( $current_post_id ) {
			// Normalize query params (handles old flat format).
			$raw_params    = get_post_meta( $current_post_id, '_query_params', true );
			$saved_queries = qc_normalize_query_params( $raw_params );

			// Get saved curated IDs.
			$curated_ids   = get_post_meta( $current_post_id, '_curated_post_ids', true );
			$saved_curated = is_array( $curated_ids ) ? array_map( 'absint', $curated_ids ) : array();
		}

		// Get all public post types for the dropdown.
		$post_types     = get_post_types( array( 'public' => true ), 'objects' );
		$post_type_data = array();
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt->name || 'query_group' === $pt->name ) {
				continue;
			}
			$post_type_data[] = array(
				'name'  => $pt->name,
				'label' => $pt->labels->singular_name,
			);
		}

		wp_localize_script( 'qc-admin', 'qcData', array(
			'ajaxurl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'qc_nonce' ),
			'postId'         => $current_post_id,
			'savedQueries'   => $saved_queries,
			'savedCuratedIds' => $saved_curated,
			'postTypes'      => $post_type_data,
			'i18n'           => array(
				'noResults'         => __( 'No posts found matching your query.', 'query-curator' ),
				'postsSaved'        => __( 'Posts saved.', 'query-curator' ),
				'errorSaving'       => __( 'Error saving posts.', 'query-curator' ),
				'errorLoading'      => __( 'Error loading results.', 'query-curator' ),
				'failedLoad'        => __( 'Failed to load results. Please try again.', 'query-curator' ),
				'failedSave'        => __( 'Failed to save. Please try again.', 'query-curator' ),
				'postsLoaded'       => __( '%d post(s) loaded.', 'query-curator' ),
				'searchPlaceholder' => __( 'Search by title...', 'query-curator' ),
				'noSearchResults'   => __( 'No posts found.', 'query-curator' ),
				'addPostsTitle'     => __( 'Add Posts', 'query-curator' ),
				'added'             => __( 'Added!', 'query-curator' ),
				'add'               => __( 'Add', 'query-curator' ),
				'deletedPost'       => __( '(deleted)', 'query-curator' ),
				'addQuery'          => __( '+ Add Query', 'query-curator' ),
				'removeQuery'       => __( 'Remove', 'query-curator' ),
				'savePosts'         => __( 'Save Posts', 'query-curator' ),
				'unsavedChanges'    => __( 'You have unsaved changes. Leave this page?', 'query-curator' ),
				'newPost'           => __( 'NEW', 'query-curator' ),
				'queryCard'         => __( 'Query %d', 'query-curator' ),
				'loadResults'       => __( 'Load Results', 'query-curator' ),
				'refreshQuery'      => __( 'Refresh from Query', 'query-curator' ),
				'loadingTaxonomies' => __( 'Loading taxonomies...', 'query-curator' ),
				'loadingMetaKeys'   => __( 'Loading meta keys...', 'query-curator' ),
				'addMetaFilter'     => __( '+ Add Meta Filter', 'query-curator' ),
				'metaKey'           => __( 'Meta Key', 'query-curator' ),
				'compare'           => __( 'Compare', 'query-curator' ),
				'metaValue'         => __( 'Value', 'query-curator' ),
				'postType'          => __( 'Post Type', 'query-curator' ),
				'resultsLimit'      => __( 'Limit', 'query-curator' ),
				'publishedAfter'    => __( 'Published After', 'query-curator' ),
				'publishedBefore'   => __( 'Published Before', 'query-curator' ),
				'confirmReplace'    => __( 'This will add new results below your saved posts. Continue?', 'query-curator' ),
				'selectMetaKey'     => __( '-- Select Key --', 'query-curator' ),
				'addedPosts'        => __( 'Added %d posts', 'query-curator' ),
				'removeCardResults' => __( 'Remove', 'query-curator' ),
				'removedPosts'      => __( 'Removed %d posts', 'query-curator' ),
				'allResultsExist'   => __( 'No new posts added', 'query-curator' ),
				'previewCount'      => __( '~%d results', 'query-curator' ),
				'counting'          => __( 'Counting...', 'query-curator' ),
			),
		) );
	}

	/**
	 * Add a contextual help tab to the query group edit screen.
	 *
	 * Provides usage instructions and code examples directly in the
	 * WordPress admin help panel.
	 *
	 * @return void
	 */
	public function add_help_tab() {
		$screen = get_current_screen();
		if ( ! $screen || 'query_group' !== $screen->post_type ) {
			return;
		}

		$screen->add_help_tab( array(
			'id'      => 'qc_overview',
			'title'   => __( 'Query Curator', 'query-curator' ),
			'content' => '<h3>' . esc_html__( 'How to Use Query Curator', 'query-curator' ) . '</h3>'
				. '<ol>'
				. '<li>' . esc_html__( 'Give your Query Group a title (e.g. "Homepage Featured Books").', 'query-curator' ) . '</li>'
				. '<li>' . esc_html__( 'Use the Query Builder to create one or more query cards. Each card filters by post type, taxonomies, dates, and meta fields.', 'query-curator' ) . '</li>'
				. '<li>' . esc_html__( 'Click "Load Results" to fetch posts. Saved posts stay locked at the top; new results appear below.', 'query-curator' ) . '</li>'
				. '<li>' . esc_html__( 'Drag and drop to reorder. Click the X to remove posts.', 'query-curator' ) . '</li>'
				. '<li>' . esc_html__( 'Use "Add Posts" to manually search and add individual posts.', 'query-curator' ) . '</li>'
				. '<li>' . esc_html__( 'Click "Save Posts" to persist your changes.', 'query-curator' ) . '</li>'
				. '</ol>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'qc_developer',
			'title'   => __( 'Developer Usage', 'query-curator' ),
			'content' => '<h3>' . esc_html__( 'Using in Themes &amp; Builders', 'query-curator' ) . '</h3>'
				. '<p><strong>' . esc_html__( 'One-liner (recommended):', 'query-curator' ) . '</strong></p>'
				. '<pre><code>'
				. esc_html( '// Returns a complete WP_Query args array — drop into any builder.' ) . "\n"
				. esc_html( 'return get_query_group_args( 34 );' ) . "\n\n"
				. esc_html( '// With overrides:' ) . "\n"
				. esc_html( 'return get_query_group_args( \'homepage-books\', [ \'post_status\' => \'publish\' ] );' ) . "\n\n"
				. esc_html( '// In a WP_Query:' ) . "\n"
				. esc_html( '$query = new WP_Query( get_query_group_args( 34 ) );' )
				. '</code></pre>'
				. '<p><strong>' . esc_html__( 'IDs only:', 'query-curator' ) . '</strong></p>'
				. '<pre><code>'
				. esc_html( '$ids = get_query_group( 34 );        // by post ID' ) . "\n"
				. esc_html( '$ids = get_query_group( \'my-slug\' ); // by slug' )
				. '</code></pre>'
				. '<h4>' . esc_html__( 'Available Hooks', 'query-curator' ) . '</h4>'
				. '<ul>'
				. '<li><code>query_curator_fetch_args</code> — ' . esc_html__( 'Filter get_posts() arguments before fetch.', 'query-curator' ) . '</li>'
				. '<li><code>query_curator_order_saved</code> — ' . esc_html__( 'Action fired after order is saved.', 'query-curator' ) . '</li>'
				. '</ul>',
		) );
	}

	/**
	 * Render the Query Builder meta box.
	 *
	 * Outputs a minimal container that JavaScript populates with
	 * dynamic query cards. Saved query data is passed via wp_localize_script.
	 *
	 * @param WP_Post $post The current post object.
	 * @return void
	 */
	public function render_query_builder( $post ) {
		wp_nonce_field( 'qc_nonce', 'qc_nonce_field' );
		?>
		<div class="qc-query-builder">
			<div id="qc-query-cards">
				<!-- Query cards rendered by JavaScript -->
			</div>

			<div class="qc-query-actions">
				<button type="button" id="qc-add-query-card" class="button">
					<?php esc_html_e( '+ Add Query', 'query-curator' ); ?>
				</button>
				<button type="button" id="qc-load-results" class="button button-primary">
					<?php esc_html_e( 'Load Results', 'query-curator' ); ?>
				</button>
				<button type="button" id="qc-refresh-query" class="button" disabled>
					<?php esc_html_e( 'Refresh from Query', 'query-curator' ); ?>
				</button>
				<span class="qc-loading spinner" style="display: none;"></span>
				<span class="qc-preview-count"></span>
				<span class="qc-filter-help">
					<?php esc_html_e( 'Load Results keeps saved posts and appends new matches below. Multiple queries are combined with OR.', 'query-curator' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Curated Posts results grid meta box.
	 *
	 * Shows saved curated posts in a sortable card grid. Includes the
	 * Save Posts button, Add Posts button, and search modal markup.
	 *
	 * @param WP_Post $post The current post object.
	 * @return void
	 */
	public function render_results_grid( $post ) {
		$curated_ids = get_post_meta( $post->ID, '_curated_post_ids', true );
		$curated_ids = is_array( $curated_ids ) ? array_map( 'absint', $curated_ids ) : array();
		?>
		<div class="qc-results-wrapper">
			<input type="hidden" id="qc-curated-post-ids" name="qc_curated_post_ids" value="<?php echo esc_attr( implode( ',', $curated_ids ) ); ?>" />

			<div class="qc-results-toolbar">
				<span class="qc-results-count">
					<?php
					if ( ! empty( $curated_ids ) ) {
						printf(
							/* translators: %d: number of curated posts */
							esc_html__( '%d post(s) curated', 'query-curator' ),
							count( $curated_ids )
						);
					}
					?>
				</span>
				<div class="qc-toolbar-buttons">
					<button type="button" id="qc-save-posts-btn" class="button button-primary qc-save-posts-btn" disabled>
						<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php esc_html_e( 'Save Posts', 'query-curator' ); ?>
					</button>
					<button type="button" id="qc-add-posts-btn" class="button">
						<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php esc_html_e( 'Add Posts', 'query-curator' ); ?>
					</button>
				</div>
			</div>

			<div class="qc-results-grid" id="qc-results-grid" role="list" aria-label="<?php esc_attr_e( 'Curated posts. Drag to reorder.', 'query-curator' ); ?>">
				<?php if ( ! empty( $curated_ids ) ) : ?>
					<?php
					$posts = get_posts( array(
						'post__in'       => $curated_ids,
						'post_type'      => 'any',
						'posts_per_page' => count( $curated_ids ),
						'orderby'        => 'post__in',
						'post_status'    => 'any',
					) );

					// Build a map so we can detect deleted posts.
					$found_ids = array();
					foreach ( $posts as $curated_post ) {
						$found_ids[] = $curated_post->ID;
					}

					foreach ( $curated_ids as $cid ) :
						// Check if the post still exists.
						$curated_post = null;
						foreach ( $posts as $p ) {
							if ( $p->ID === $cid ) {
								$curated_post = $p;
								break;
							}
						}

						if ( $curated_post ) :
							$thumbnail     = get_the_post_thumbnail_url( $curated_post->ID, 'thumbnail' );
							$post_type_obj = get_post_type_object( $curated_post->post_type );
							$type_label    = $post_type_obj ? $post_type_obj->labels->singular_name : $curated_post->post_type;
					?>
						<div class="qc-post-item qc-locked" data-post-id="<?php echo esc_attr( $curated_post->ID ); ?>" role="listitem" tabindex="0">
							<div class="qc-post-thumbnail">
								<?php if ( $thumbnail ) : ?>
									<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $curated_post->post_title ); ?>" />
								<?php else : ?>
									<div class="qc-no-thumbnail"><?php esc_html_e( 'No Image', 'query-curator' ); ?></div>
								<?php endif; ?>
							</div>
							<span class="qc-post-title"><?php echo esc_html( $curated_post->post_title ); ?></span>
							<span class="qc-post-type"><?php echo esc_html( $type_label ); ?></span>
							<button type="button" class="qc-remove-post" title="<?php esc_attr_e( 'Remove', 'query-curator' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'query-curator' ), $curated_post->post_title ) ); ?>">&times;</button>
						</div>
						<?php else : ?>
						<!-- Post ID <?php echo esc_html( $cid ); ?> no longer exists — auto-removed on next save. -->
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<p class="qc-empty-state" <?php echo ! empty( $curated_ids ) ? 'style="display: none;"' : ''; ?>>
				<?php esc_html_e( 'No curated posts yet. Use the Query Builder above to load posts, or click "Add Posts" to search and add them manually.', 'query-curator' ); ?>
			</p>
		</div>

		<!-- Add Posts Modal -->
		<div id="qc-add-posts-modal" class="qc-modal" style="display: none;" role="dialog" aria-labelledby="qc-modal-title" aria-modal="true">
			<div class="qc-modal-backdrop"></div>
			<div class="qc-modal-content">
				<div class="qc-modal-header">
					<h2 id="qc-modal-title"><?php esc_html_e( 'Add Posts', 'query-curator' ); ?></h2>
					<button type="button" class="qc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'query-curator' ); ?>">&times;</button>
				</div>
				<div class="qc-modal-body">
					<div class="qc-search-bar">
						<input type="text" id="qc-search-input" placeholder="<?php esc_attr_e( 'Search by title...', 'query-curator' ); ?>" aria-label="<?php esc_attr_e( 'Search posts by title', 'query-curator' ); ?>" />
						<button type="button" id="qc-search-btn" class="button"><?php esc_html_e( 'Search', 'query-curator' ); ?></button>
						<span class="qc-search-spinner spinner" style="display: none;"></span>
					</div>
					<div id="qc-search-results" class="qc-search-results" aria-live="polite">
						<p class="qc-search-empty"><?php esc_html_e( 'Type a title and click Search to find posts.', 'query-curator' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Code Snippets sidebar meta box.
	 *
	 * Shows ready-to-copy PHP snippets with the current query group ID
	 * pre-filled, so developers can paste them directly into their
	 * theme or page builder.
	 *
	 * @param WP_Post $post The current post object.
	 * @return void
	 */
	public function render_code_snippets( $post ) {
		$post_id = $post->ID;
		$slug    = $post->post_name ? $post->post_name : 'your-slug';

		// Only show the ID-based snippets once the post has been saved.
		$is_new = 'auto-draft' === $post->post_status;
		?>
		<div class="qc-snippets">
			<?php if ( $is_new ) : ?>
				<p class="qc-snippets-note">
					<?php esc_html_e( 'Publish this Query Group first to see code snippets with its ID.', 'query-curator' ); ?>
				</p>
			<?php else : ?>
				<p class="qc-snippets-label"><?php esc_html_e( 'One-liner (recommended)', 'query-curator' ); ?></p>
				<div class="qc-snippet-block">
					<code class="qc-snippet-code"><?php echo esc_html( "get_query_group_args( {$post_id} )" ); ?></code>
					<button type="button" class="qc-copy-btn button button-small" data-snippet="<?php echo esc_attr( "get_query_group_args( {$post_id} )" ); ?>">
						<?php esc_html_e( 'Copy', 'query-curator' ); ?>
					</button>
				</div>

				<p class="qc-snippets-label"><?php esc_html_e( 'Breakdance / Builder return', 'query-curator' ); ?></p>
				<div class="qc-snippet-block">
					<code class="qc-snippet-code"><?php echo esc_html( "return get_query_group_args( {$post_id} );" ); ?></code>
					<button type="button" class="qc-copy-btn button button-small" data-snippet="<?php echo esc_attr( "return get_query_group_args( {$post_id} );" ); ?>">
						<?php esc_html_e( 'Copy', 'query-curator' ); ?>
					</button>
				</div>

				<p class="qc-snippets-label"><?php esc_html_e( 'WP_Query', 'query-curator' ); ?></p>
				<div class="qc-snippet-block">
					<code class="qc-snippet-code"><?php echo esc_html( "\$query = new WP_Query( get_query_group_args( {$post_id} ) );" ); ?></code>
					<button type="button" class="qc-copy-btn button button-small" data-snippet="<?php echo esc_attr( "\$query = new WP_Query( get_query_group_args( {$post_id} ) );" ); ?>">
						<?php esc_html_e( 'Copy', 'query-curator' ); ?>
					</button>
				</div>

				<p class="qc-snippets-label"><?php esc_html_e( 'IDs only', 'query-curator' ); ?></p>
				<div class="qc-snippet-block">
					<code class="qc-snippet-code"><?php echo esc_html( "get_query_group( {$post_id} )" ); ?></code>
					<button type="button" class="qc-copy-btn button button-small" data-snippet="<?php echo esc_attr( "get_query_group( {$post_id} )" ); ?>">
						<?php esc_html_e( 'Copy', 'query-curator' ); ?>
					</button>
				</div>

				<p class="qc-snippets-label"><?php esc_html_e( 'By slug', 'query-curator' ); ?></p>
				<div class="qc-snippet-block">
					<code class="qc-snippet-code"><?php echo esc_html( "get_query_group_args( '{$slug}' )" ); ?></code>
					<button type="button" class="qc-copy-btn button button-small" data-snippet="<?php echo esc_attr( "get_query_group_args( '{$slug}' )" ); ?>">
						<?php esc_html_e( 'Copy', 'query-curator' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
