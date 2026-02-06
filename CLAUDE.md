# Query Curator - Claude Development Guide

## Project Overview

**Query Curator** is a WordPress plugin that solves a common problem: content managers need to carefully curate which posts appear on their pages, but existing solutions are either too limited (manual post selection) or too automatic (query-based displays with no manual control).

This plugin bridges the gap by providing:
1. A visual query builder to filter posts by criteria (date, meta, taxonomy, etc.)
2. A drag-and-drop interface to reorder, add, or remove posts from the results
3. Multiple named "Query Groups" that can be reused across the site
4. A simple helper function developers can use in any theme/builder

## Target Users

- **Content Managers**: Non-technical users who need to curate homepage features, recommended products, etc.
- **Developers**: WordPress developers using page builders (Breakdance, Elementor, etc.) or custom themes who need `post__in` arrays

## Core Architecture

### Custom Post Type: `query_group`
- Each post represents one curated list
- Post title = name of the query group (e.g., "Homepage Featured Books", "Sale Items")
- Post meta stores the array of post IDs in order
- `supports` only 'title' (no editor, no comments, etc.)

### Meta Fields
- `_curated_post_ids` (array) - The ordered array of post IDs
- `_query_params` (array, optional) - Store original query parameters for "Refresh from Query" feature

### Admin Interface
Built with native WordPress UI (no React/Gutenberg):
- Meta box #1: Query Builder (top)
- Meta box #2: Results Grid (middle/bottom)

## WordPress Development Standards

### Naming Conventions
- **Function prefix**: `qc_` for all functions
- **Hook prefix**: `query_curator_` for all actions/filters
- **Class names**: PascalCase with `Query_Curator_` prefix
- **File names**: lowercase-with-hyphens.php

### File Structure
```
query-curator/
├── query-curator.php          # Main plugin file
├── includes/
│   ├── class-post-type.php    # CPT registration
│   ├── class-meta-boxes.php   # Admin meta boxes
│   ├── class-ajax-handler.php # AJAX endpoints
│   └── helpers.php            # Helper functions
├── assets/
│   ├── css/
│   │   └── admin.css         # Admin styles
│   └── js/
│       └── admin.js          # Sortable, AJAX handling
├── README.md
├── CLAUDE.md
├── AGENTS.md
└── TODO.md
```

### Code Quality Standards

**Security First**:
- Always nonce all AJAX requests
- Sanitize all inputs with appropriate functions
- Escape all outputs
- Check capabilities before allowing actions
- Use prepared statements for custom queries (if any)

**WordPress Best Practices**:
- Use WordPress coding standards (WordPress-Core ruleset)
- Leverage built-in functions (wp_localize_script, wp_enqueue_script, etc.)
- Don't reinvent the wheel - use WordPress Sortable.js, not external libraries
- Proper enqueueing of scripts/styles (only on relevant admin pages)
- Translation-ready (use `__()`, `_e()`, text domain: 'query-curator')

**Performance**:
- Only load admin assets on the query_group edit screen
- Use `get_posts()` with proper arguments instead of custom queries
- Cache query results when appropriate
- Don't load on front-end unless helper function is called

### Testing Strategy

**Manual Testing Checklist**:
1. Create a new Query Group
2. Build a query with various filters (date, category, custom meta)
3. Load results - verify correct posts appear
4. Drag to reorder - verify order persists on page reload
5. Remove posts - verify they disappear
6. Manually add posts - verify they appear
7. Save and use `get_query_group()` in a theme/builder
8. Verify `post__in` returns correct ordered IDs

**Edge Cases to Test**:
- Empty query results
- Query with 100+ results
- Reordering with only 1 post
- Deleting a post that's in a query group (should handle gracefully)
- Multiple query groups on same post type
- Hierarchical post types (pages)

## Key Technical Decisions

### Why No Gutenberg?
- Target users may not be using Gutenberg
- Classic meta boxes work universally
- Lighter weight and faster to develop
- Works in Classic Editor, Gutenberg, and page builders

### Why Custom Post Type vs Options Page?
- Multiple query groups (unlimited)
- Built-in WordPress features: revisions, trash, search
- Familiar UI for WordPress users
- Can be extended with categories/tags if needed later

### Why Store Post IDs vs Menu Order?
- Existing "Post Order" plugins modify the global `menu_order` field
- We need multiple different curated lists per post type
- Storing arrays gives complete flexibility
- Doesn't interfere with other plugins

### AJAX vs Page Reloads?
- AJAX for better UX (instant feedback on reorder/remove)
- Autosave on drag-and-drop (no "Save" button needed)
- Standard WordPress AJAX pattern (admin-ajax.php)

## Development Workflow

### Phase 1: Foundation
1. Plugin boilerplate
2. CPT registration
3. Basic helper function

### Phase 2: Query Builder
4. Meta box with filter UI
5. AJAX endpoint to fetch results
6. Display results as simple list

### Phase 3: Curation Interface
7. Display results as grid with thumbnails
8. Implement drag-and-drop (WordPress Sortable)
9. Add/remove functionality
10. Save to post meta

### Phase 4: Polish
11. Styling (match WordPress admin)
12. Error handling
13. Inline documentation
14. README and examples

## Helper Function Usage

```php
// Get array of post IDs for use in queries
$curated_ids = get_query_group( 34 ); // Pass query group post ID

// Use in Breakdance
return [
    'post_type' => 'product',
    'post__in' => $curated_ids,
    'orderby' => 'post__in' // Preserves manual order
];

// Use in WP_Query
$query = new WP_Query([
    'post_type' => 'product',
    'post__in' => get_query_group( 34 ),
    'orderby' => 'post__in'
]);

// Get by query group slug instead
$curated_ids = get_query_group( 'homepage-books' );
```

## Common WordPress Patterns to Use

### Registering the CPT
```php
add_action( 'init', 'qc_register_post_type' );
function qc_register_post_type() {
    register_post_type( 'query_group', [ /* args */ ] );
}
```

### Adding Meta Boxes
```php
add_action( 'add_meta_boxes', 'qc_add_meta_boxes' );
function qc_add_meta_boxes() {
    add_meta_box( 'qc_query_builder', __( 'Query Builder', 'query-curator' ), 
        'qc_query_builder_callback', 'query_group', 'normal', 'high' );
}
```

### AJAX Handler
```php
add_action( 'wp_ajax_qc_fetch_posts', 'qc_ajax_fetch_posts' );
function qc_ajax_fetch_posts() {
    check_ajax_referer( 'qc_nonce', 'nonce' );
    // Do stuff
    wp_send_json_success( $data );
}
```

### Enqueuing Assets
```php
add_action( 'admin_enqueue_scripts', 'qc_enqueue_admin_assets' );
function qc_enqueue_admin_assets( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
        return;
    }
    
    $screen = get_current_screen();
    if ( 'query_group' !== $screen->post_type ) {
        return;
    }
    
    wp_enqueue_script( 'jquery-ui-sortable' ); // WordPress built-in
    wp_enqueue_script( 'qc-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), 
        [ 'jquery', 'jquery-ui-sortable' ], '1.0.0', true );
    
    wp_localize_script( 'qc-admin', 'qcData', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'qc_nonce' )
    ]);
}
```

## Reference Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Post Types](https://developer.wordpress.org/plugins/post-types/)
- [Meta Boxes](https://developer.wordpress.org/plugins/metadata/custom-meta-boxes/)
- [AJAX in Plugins](https://developer.wordpress.org/plugins/javascript/ajax/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [get_posts() Documentation](https://developer.wordpress.org/reference/functions/get_posts/)

## Notes for AI Agents

When building this plugin:

1. **Start simple**: Get the CPT working first, then add complexity
2. **Test incrementally**: After each TODO item, verify it works before moving on
3. **Use WordPress functions**: Don't write custom SQL or reinvent core functionality
4. **Security is non-negotiable**: Every AJAX endpoint must check nonce and capabilities
5. **Think about the user**: The content manager shouldn't need to read documentation to use this
6. **Comment your code**: Future developers (human or AI) should understand why you made decisions
7. **Keep it lightweight**: Don't load libraries if WordPress already has it (jQuery, Sortable, etc.)

## Success Criteria

The plugin is complete when:
- [ ] A user can create a Query Group post
- [ ] They can filter posts using common criteria
- [ ] Results load and display with thumbnails
- [ ] They can drag to reorder posts
- [ ] They can remove posts from the list
- [ ] The order persists after page reload
- [ ] `get_query_group()` returns the correct ordered array
- [ ] It works in a real Breakdance post loop
- [ ] No PHP errors or notices
- [ ] No JavaScript console errors
- [ ] Code passes WordPress coding standards
