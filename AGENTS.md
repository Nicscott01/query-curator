# Agents Guide - Query Curator Plugin Development

## How AI Agents Should Approach This Project

This document provides specific guidance for AI agents (like Claude, GPT, etc.) working on the Query Curator WordPress plugin.

## Development Philosophy

### Incremental & Testable
Build this plugin in small, testable increments. Each TODO item should result in something that can be verified before moving to the next step. Don't try to build everything at once.

**Good approach:**
1. Create CPT → Test that it appears in admin menu
2. Add helper function → Test that it returns empty array
3. Add query builder meta box → Test that it renders
4. Add one filter (post type) → Test that it works

**Bad approach:**
- Write all files at once and hope it works

### WordPress-First Thinking

This is a WordPress plugin, not a standalone application. This means:

**Use WordPress Core Features:**
- WordPress has jQuery and jQuery UI Sortable built-in → Use them
- WordPress has AJAX handling → Use `admin-ajax.php`
- WordPress has meta box APIs → Use `add_meta_box()`
- WordPress has settings APIs → Use them if needed
- WordPress has nonce functions → Use `wp_nonce_field()` and `check_admin_referer()`

**Don't Reinvent:**
- Don't include external JavaScript libraries WordPress already has
- Don't write custom database queries when `get_posts()` works
- Don't create custom security when WordPress has capability checks
- Don't build a custom admin UI framework

### Security-First Development

Every piece of code that accepts input or performs actions must be secured:

**Required Security Checks:**
```php
// AJAX handlers
function qc_ajax_handler() {
    // 1. Check nonce
    check_ajax_referer( 'qc_nonce', 'nonce' );
    
    // 2. Check capabilities
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }
    
    // 3. Sanitize inputs
    $post_id = absint( $_POST['post_id'] );
    
    // 4. Validate
    if ( ! $post_id || get_post_type( $post_id ) !== 'query_group' ) {
        wp_send_json_error( 'Invalid post ID' );
    }
    
    // ... do work ...
}

// Saving meta
function qc_save_meta( $post_id ) {
    // 1. Verify nonce
    if ( ! isset( $_POST['qc_nonce'] ) || 
         ! wp_verify_nonce( $_POST['qc_nonce'], 'qc_save_meta' ) ) {
        return;
    }
    
    // 2. Check autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // 3. Check capabilities
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // 4. Sanitize and save
    $post_ids = array_map( 'absint', $_POST['curated_post_ids'] );
    update_post_meta( $post_id, '_curated_post_ids', $post_ids );
}

// Outputting data
echo esc_html( $post_title );           // Text
echo esc_url( $permalink );             // URLs
echo esc_attr( $data_attribute );       // Attributes
echo wp_kses_post( $post_content );     // HTML content
```

**Never skip security checks.** This is non-negotiable.

## File-by-File Development Guide

### 1. Main Plugin File (query-curator.php)

**Purpose:** Plugin header, activation/deactivation, load other files

**Start with:**
```php
<?php
/**
 * Plugin Name: Query Curator
 * Plugin URI: https://github.com/Nicscott01/query-curator
 * Description: Build and save curated post queries with drag-and-drop ordering
 * Version: 1.0.0
 * Author: Nicolas Scott
 * Author URI: https://crearewebsolutions.com
 * License: GPL v2 or later
 * Text Domain: query-curator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'QC_VERSION', '1.0.0' );
define( 'QC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include files
require_once QC_PLUGIN_DIR . 'includes/helpers.php';
require_once QC_PLUGIN_DIR . 'includes/class-post-type.php';
require_once QC_PLUGIN_DIR . 'includes/class-meta-boxes.php';
require_once QC_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Initialize
function qc_init() {
    new Query_Curator_Post_Type();
    new Query_Curator_Meta_Boxes();
    new Query_Curator_Ajax_Handler();
}
add_action( 'plugins_loaded', 'qc_init' );
```

**Test:** Activate plugin, verify no errors

### 2. Helper Functions (includes/helpers.php)

**Purpose:** Public-facing helper functions developers will use

**Start with:**
```php
<?php
/**
 * Get curated post IDs from a query group
 *
 * @param int|string $query_group Query group post ID or slug
 * @return array Array of post IDs in order, empty array if not found
 */
function get_query_group( $query_group ) {
    // Handle slug
    if ( ! is_numeric( $query_group ) ) {
        $post = get_page_by_path( $query_group, OBJECT, 'query_group' );
        if ( ! $post ) {
            return array();
        }
        $query_group = $post->ID;
    }
    
    // Get meta
    $post_ids = get_post_meta( $query_group, '_curated_post_ids', true );
    
    // Return array or empty
    return is_array( $post_ids ) ? $post_ids : array();
}
```

**Test:** Call `get_query_group( 999 )` and verify it returns empty array without errors

### 3. CPT Registration (includes/class-post-type.php)

**Key points:**
- Set `public` to `false` (admin-only)
- Set `show_ui` to `true`
- Only support 'title'
- Add good labels for admin UI
- Consider adding `menu_icon` with dashicons

**Test:** After registering, verify "Query Groups" appears in admin menu

### 4. Meta Boxes (includes/class-meta-boxes.php)

Build in this order:

**4a. Query Builder Meta Box (Top)**
- Dropdown for post type selection
- Common filters: date range, category, tags, meta key/value
- "Load Results" button
- Store selected filters in hidden fields

**4b. Results Meta Box (Bottom)**
- Initially hidden until results are loaded
- Display posts in grid with:
  - Featured image (thumbnail size)
  - Post title
  - Post type label
  - Remove button (X)
- Sortable container for drag-and-drop
- Hidden input field to store ordered IDs

**Test each meta box individually before moving on**

### 5. AJAX Handler (includes/class-ajax-handler.php)

**Endpoints needed:**

**5a. Fetch Posts (`qc_fetch_posts`)**
- Receives query parameters
- Builds `get_posts()` query
- Returns JSON with post data (ID, title, thumbnail, permalink)
- Security: nonce check, capability check

**5b. Save Order (`qc_save_order`)**
- Receives array of post IDs
- Saves to post meta `_curated_post_ids`
- Security: nonce check, capability check, verify post ownership

**Test:** Use browser dev tools to manually trigger AJAX and verify responses

### 6. JavaScript (assets/js/admin.js)

**Functionality needed:**

**6a. Load Results**
- Listen for "Load Results" button click
- Gather filter values
- AJAX call to `qc_fetch_posts`
- Render results in grid
- Initialize sortable

**6b. Sortable**
```javascript
jQuery( '.qc-results-grid' ).sortable({
    update: function( event, ui ) {
        // Get new order
        var order = jQuery( this ).sortable( 'toArray', { attribute: 'data-post-id' } );
        
        // Save via AJAX
        qc_save_order( order );
    }
});
```

**6c. Remove Posts**
- Click X button
- Remove from DOM
- Update order and save

**Test:** Use console.log extensively, verify sortable works before adding AJAX saves

### 7. Styling (assets/css/admin.css)

**Match WordPress admin aesthetic:**
- Use WordPress admin colors (#0073aa for primary, etc.)
- Use WordPress admin fonts (system font stack)
- Grid layout for results (CSS Grid or Flexbox)
- Hover states for draggable items
- Clear visual feedback for drag operations

**Keep it minimal:** Don't over-design. This should feel like part of WordPress.

## Common Pitfalls & How to Avoid Them

### Pitfall #1: Over-Engineering
**Symptom:** Adding features that aren't in the spec (filters, sorting options, export/import)
**Fix:** Stick to the TODO list. Note ideas for v2.0 but don't build them now.

### Pitfall #2: Breaking WordPress Patterns
**Symptom:** Using custom routing, custom AJAX endpoints, not following WP conventions
**Fix:** If WordPress has a way to do it, use that way. Check docs first.

### Pitfall #3: No Error Handling
**Symptom:** Plugin breaks silently, no feedback to user
**Fix:** Every AJAX call should handle errors. Every user action should have feedback.

```javascript
// Good
jQuery.post( ajaxurl, data, function( response ) {
    if ( response.success ) {
        // Success feedback
        show_notice( 'Results loaded!', 'success' );
    } else {
        // Error feedback
        show_notice( response.data, 'error' );
    }
});

// Bad
jQuery.post( ajaxurl, data, function( response ) {
    render_results( response.data ); // What if this fails?
});
```

### Pitfall #4: Not Testing in Target Environment
**Symptom:** Works on your machine, breaks with Breakdance/Elementor
**Fix:** Actually test `get_query_group()` in a real post loop. Don't assume.

### Pitfall #5: Performance Issues with Large Result Sets
**Symptom:** Query builder returns 1000 posts, browser freezes
**Fix:** Limit results to reasonable number (default 100, max 500). Add pagination if needed.

## Code Review Checklist

Before marking a TODO as complete, verify:

- [ ] Code has no PHP syntax errors
- [ ] Code has no JavaScript errors in console
- [ ] All inputs are sanitized
- [ ] All outputs are escaped
- [ ] AJAX endpoints check nonce and capabilities
- [ ] Functions have docblocks
- [ ] Code follows WordPress naming conventions
- [ ] No hard-coded values (use constants or variables)
- [ ] User-facing strings are translatable (`__()`, `_e()`)
- [ ] No `var_dump()`, `console.log()` debugging left in code
- [ ] Feature actually works (test it!)

## Communication with Users

When you complete tasks or encounter issues:

**Good status update:**
> Completed: CPT registration. "Query Groups" now appears in admin menu with custom icon. Tested by creating a test query group - saves and displays correctly.

**Bad status update:**
> Done with CPT

**Good error report:**
> Issue: AJAX save order endpoint returns 403 error. Cause: nonce check failing because nonce field name doesn't match. Fix: Changed `qc_meta_nonce` to `qc_nonce` in both PHP and JS. Now saving successfully.

**Bad error report:**
> Save doesn't work

## When to Ask for Clarification

You should ask the user if:
- The spec is ambiguous (e.g., "What should happen if user tries to add duplicate post?")
- You need to make a UX decision not covered in spec
- You encounter a technical limitation (e.g., WordPress API doesn't support X)
- Multiple approaches exist and spec doesn't specify preference

You should NOT ask if:
- Information is in CLAUDE.md or README.md
- It's a standard WordPress pattern (check docs first)
- It's covered in the TODO list
- You can make a reasonable decision yourself

## Testing Protocol

After each major TODO section (e.g., "Query Builder Complete"), perform:

**1. Smoke Test**
- Does it load without errors?
- Can you access all UI elements?

**2. Happy Path Test**
- Can a user complete the intended workflow?
- Does it save correctly?

**3. Edge Case Test**
- What if results are empty?
- What if there's only 1 result?
- What if there are 500 results?

**4. Security Test**
- Try to access AJAX endpoint without nonce
- Try to access as non-admin user
- Try to inject SQL/XSS in inputs

## Development Environment Expectations

Assume the developer has:
- WordPress 6.0+ installed locally
- PHP 7.4+
- Browser dev tools available
- Basic understanding of WordPress (or will ask if confused)

Don't assume they have:
- Command-line tools (Composer, npm, etc.)
- Build processes (webpack, gulp, etc.)
- Testing frameworks (PHPUnit, etc.)

Keep it simple. This should work by simply activating the plugin.

## Success Metrics

You've built this successfully when:

1. A content manager can create a query group in < 2 minutes
2. Reordering posts feels natural and instant
3. A developer can integrate `get_query_group()` in < 30 seconds
4. Code passes WordPress coding standards automated checks
5. Plugin works in WordPress 5.9 through 6.4+
6. No console errors, no PHP warnings
7. User doesn't need to read documentation to use basic features

## Final Notes

- **Prioritize working over perfect**: A working v1.0 is better than a perfect unfinished plugin
- **Document as you go**: Add comments explaining "why" not just "what"
- **Think about the next developer**: Code should be maintainable
- **Respect WordPress**: Don't fight the platform, work with it

Remember: The goal is to ship a useful tool, not to demonstrate every WordPress API or JavaScript technique. Keep it simple, make it work, make it secure.
