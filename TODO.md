# Query Curator - Development TODO

This is the master task list for building the Query Curator WordPress plugin. Complete tasks in order, checking them off as you go. Each task should be tested before moving to the next.

## Status Legend
- [ ] Not started
- [x] Completed
- [!] Blocked/Issue

---

## Phase 1: Foundation & Setup

### 1.1 Project Structure
- [x] Create main plugin file: `query-curator.php`
  - [x] Add WordPress plugin header (name, description, version, author, license)
  - [x] Add ABSPATH security check
  - [x] Define constants: QC_VERSION, QC_PLUGIN_DIR, QC_PLUGIN_URL
  - [x] Add text domain: 'query-curator'
- [x] Create directory structure:
  - [x] `/includes/` directory
  - [x] `/assets/` directory
  - [x] `/assets/css/` directory
  - [x] `/assets/js/` directory
- [x] Test: Activate plugin, verify no errors

### 1.2 Helper Functions
- [x] Create file: `includes/helpers.php`
- [x] Implement `get_query_group( $query_group )` function:
  - [x] Accept int (post ID) or string (post slug)
  - [x] Handle slug lookup using `get_page_by_path()`
  - [x] Get post meta `_curated_post_ids`
  - [x] Return array of IDs or empty array
  - [x] Add proper PHPDoc
- [x] Include helpers.php in main plugin file
- [ ] Test: Call `get_query_group( 999 )`, verify returns empty array
- [ ] Test: Call with non-existent slug, verify returns empty array

---

## Phase 2: Custom Post Type

### 2.1 CPT Registration
- [x] Create file: `includes/class-post-type.php`
- [x] Create class: `Query_Curator_Post_Type`
- [x] Register 'query_group' post type in constructor:
  - [x] Labels: singular 'Query Group', plural 'Query Groups'
  - [x] `public` => false
  - [x] `show_ui` => true
  - [x] `show_in_menu` => true
  - [x] `menu_icon` => 'dashicons-filter'
  - [x] `supports` => array( 'title' ) only
  - [x] `has_archive` => false
  - [x] `rewrite` => array( 'slug' => 'query-group' )
  - [x] `capability_type` => 'post'
- [x] Hook into 'init' action
- [x] Include class file in main plugin file
- [x] Initialize class in main plugin file
- [x] Test: Verify "Query Groups" appears in admin menu
- [x] Test: Create a new query group, verify it saves with just title

### 2.2 Custom Columns (Optional but Nice)
- [x] Add custom admin column showing number of curated posts
- [x] Add custom admin column showing post type being curated
- [ ] Test: Verify columns appear in Query Groups list table

---

## Phase 3: Meta Boxes - Query Builder

### 3.1 Meta Box Registration
- [x] Create file: `includes/class-meta-boxes.php`
- [x] Create class: `Query_Curator_Meta_Boxes`
- [x] Register meta box 'qc_query_builder':
  - [x] Title: 'Query Builder'
  - [x] Post type: 'query_group'
  - [x] Context: 'normal'
  - [x] Priority: 'high'
- [x] Create callback function `render_query_builder()`
- [x] Include class file in main plugin file
- [x] Initialize class in main plugin file
- [x] Test: Edit query group, verify meta box appears

### 3.2 Query Builder UI
- [x] Add nonce field for security
- [x] Add post type selector:
  - [x] Get all public post types using `get_post_types()`
  - [x] Render as `<select>` dropdown
  - [x] Default to 'post'
- [x] Add taxonomy filters section:
  - [x] Category multi-select (for posts)
  - [x] Tags multi-select (for posts)
  - [ ] Dynamic taxonomy selectors based on chosen post type (future enhancement)
- [x] Add date filters:
  - [x] Published after (date input)
  - [x] Published before (date input)
- [x] Add meta filters:
  - [x] Meta key (text input)
  - [x] Meta compare (dropdown: =, !=, >, <, >=, <=, LIKE)
  - [x] Meta value (text input)
- [x] Add results limit:
  - [x] Number input (default 100, max 500)
- [x] Add "Load Results" button
  - [x] ID: 'qc-load-results'
  - [x] Classes for styling
- [x] Add hidden input to store query group post ID
- [x] Add loading indicator (hidden by default)
- [ ] Test: Verify all form fields render correctly
- [ ] Test: Verify nonce field is present

### 3.3 Query Builder Styling
- [x] Create file: `assets/css/admin.css`
- [x] Style query builder meta box:
  - [x] Organize filters in rows/columns
  - [x] Add proper spacing and padding
  - [x] Style inputs to match WordPress admin
  - [x] Style "Load Results" button (WordPress button-primary class)
  - [x] Add loading spinner styles
- [x] Enqueue stylesheet on query_group edit screen only
- [ ] Test: Verify styles load and look clean

---

## Phase 4: Meta Boxes - Results Grid

### 4.1 Results Meta Box Registration
- [x] Register meta box 'qc_results_grid':
  - [x] Title: 'Curated Posts'
  - [x] Post type: 'query_group'
  - [x] Context: 'normal'
  - [x] Priority: 'default' (below query builder)
- [x] Create callback function `render_results_grid()`
- [ ] Test: Verify meta box appears below query builder

### 4.2 Results Grid UI
- [x] Add container div with class 'qc-results-grid'
- [x] Add hidden input field 'qc_curated_post_ids' to store ordered IDs
- [x] Add "Add Posts" button (for manual additions)
- [x] Add empty state message (shown when no results)
- [x] Add template for post item (will be populated via JS):
  ```html
  <div class="qc-post-item" data-post-id="{ID}">
      <img src="{thumbnail}" />
      <span class="qc-post-title">{title}</span>
      <span class="qc-post-type">{post_type}</span>
      <button class="qc-remove-post">×</button>
  </div>
  ```
- [ ] Test: Verify container renders

### 4.3 Results Grid Styling
- [x] Style results grid in `assets/css/admin.css`:
  - [x] CSS Grid or Flexbox layout (3-4 columns)
  - [x] Post item cards with border
  - [x] Thumbnail styling (consistent size, object-fit: cover)
  - [x] Hover states
  - [x] Drag handle indicator (cursor: move)
  - [x] Remove button styling (top-right corner)
  - [x] Empty state styling
- [x] Add drag-and-drop visual feedback:
  - [x] Placeholder style during drag
  - [x] Opacity change on dragged item
- [ ] Test: Verify grid looks good with dummy HTML

### 4.4 Load Existing Curated Posts
- [x] In `render_results_grid()`, check for existing meta
- [x] If `_curated_post_ids` exists:
  - [x] Get post objects for each ID
  - [x] Render post items in grid
  - [x] Populate hidden input with IDs
- [ ] Test: Save meta manually in database, verify it loads on edit

---

## Phase 5: AJAX - Fetch Posts

### 5.1 AJAX Handler Setup
- [x] Create file: `includes/class-ajax-handler.php`
- [x] Create class: `Query_Curator_Ajax_Handler`
- [x] Register AJAX action: `wp_ajax_qc_fetch_posts`
- [x] Create handler method: `fetch_posts()`
- [x] Include class file in main plugin file
- [x] Initialize class in main plugin file

### 5.2 Fetch Posts Implementation
- [x] In `fetch_posts()` method:
  - [x] Check nonce: `check_ajax_referer( 'qc_nonce', 'nonce' )`
  - [x] Check capability: `current_user_can( 'edit_posts' )`
  - [x] Sanitize inputs:
    - [x] Post type: `sanitize_key()`
    - [x] Taxonomy terms: `array_map( 'absint' )`
    - [x] Dates: `sanitize_text_field()`
    - [x] Meta key/value: `sanitize_text_field()`
    - [x] Limit: `absint()` with max 500
  - [x] Build `get_posts()` arguments array
  - [x] Add post type
  - [x] Add taxonomy query if filters present
  - [x] Add date query if dates present
  - [x] Add meta query if meta filters present
  - [x] Set posts_per_page from limit
  - [x] Execute `get_posts()`
  - [x] Build response array with post data:
    - [x] Post ID
    - [x] Post title
    - [x] Post type label
    - [x] Thumbnail URL (get_the_post_thumbnail_url, 'thumbnail' size)
    - [x] Permalink
  - [x] Return JSON: `wp_send_json_success( $posts_data )`
  - [x] Handle errors: `wp_send_json_error( $message )`
- [ ] Test: Manually trigger AJAX, verify response structure

---

## Phase 6: JavaScript - Load Results

### 6.1 JavaScript Setup
- [x] Create file: `assets/js/admin.js`
- [x] Enqueue script on query_group edit screen only:
  - [x] Dependency: jquery, jquery-ui-sortable
  - [x] Localize script with:
    - [x] ajaxurl
    - [x] nonce
    - [x] post_id (current query group ID)
- [x] Wrap all code in jQuery document ready
- [ ] Test: Verify script loads with correct dependencies

### 6.2 Load Results Button Handler
- [x] Listen for click on '#qc-load-results' button
- [x] Prevent default action
- [x] Show loading indicator
- [x] Gather form data:
  - [x] Post type value
  - [x] All taxonomy filter values
  - [x] Date filter values
  - [x] Meta filter values
  - [x] Results limit
  - [x] Nonce
- [x] Make AJAX POST request to 'qc_fetch_posts'
- [x] On success:
  - [x] Hide loading indicator
  - [x] Clear existing results grid
  - [x] Call function to render results
  - [x] Show success message
- [x] On error:
  - [x] Hide loading indicator
  - [x] Show error message
  - [x] Log error to console
- [ ] Test: Click button, verify AJAX request fires
- [ ] Test: Verify loading indicator shows/hides

### 6.3 Render Results Function
- [x] Create function `renderResults( posts )`
- [x] For each post in array:
  - [x] Create post item HTML from template
  - [x] Populate post ID, title, thumbnail, type
  - [x] Append to results grid
- [x] Update hidden input field with post IDs
- [x] If no posts:
  - [x] Show empty state message
- [x] Call function to initialize sortable (see next phase)
- [ ] Test: Verify posts render with images and data
- [ ] Test: Verify empty state shows when no results

---

## Phase 7: JavaScript - Drag & Drop

### 7.1 Sortable Initialization
- [x] Create function `initSortable()`
- [x] Initialize jQuery UI Sortable on '.qc-results-grid':
  - [x] cursor: 'move'
  - [x] placeholder: 'qc-post-placeholder' (styled)
  - [x] opacity: 0.6 during drag
- [x] Add 'update' event handler:
  - [x] Get new order from DOM
  - [x] Update hidden input field
  - [x] Call save function (see next phase)
- [ ] Test: Verify drag-and-drop works visually
- [ ] Test: Verify order updates in hidden input

### 7.2 Remove Post Handler
- [x] Listen for click on '.qc-remove-post' button (use event delegation)
- [x] Get post ID from parent element
- [x] Remove post item from DOM
- [x] Update hidden input field (remove that ID from array)
- [x] Call save function
- [ ] Test: Click X, verify post removed
- [ ] Test: Verify hidden input updates

---

## Phase 8: AJAX - Save Order

### 8.1 Save Order AJAX Handler
- [x] In `Query_Curator_Ajax_Handler` class
- [x] Register AJAX action: `wp_ajax_qc_save_order`
- [x] Create handler method: `save_order()`
- [x] Security checks:
  - [x] Check nonce
  - [x] Check capability: `current_user_can( 'edit_post', $post_id )`
- [x] Sanitize inputs:
  - [x] Post ID: `absint()`
  - [x] Post IDs array: `array_map( 'absint', $post_ids )`
- [x] Verify post type is 'query_group'
- [x] Save to post meta: `update_post_meta( $post_id, '_curated_post_ids', $post_ids )`
- [x] Return success: `wp_send_json_success()`
- [x] Handle errors appropriately
- [ ] Test: Manually call endpoint, verify meta saves

### 8.2 JavaScript Save Function
- [x] Create function `saveOrder()`
- [x] Get post IDs from hidden input field
- [x] Get current query group post ID
- [x] Make AJAX POST request to 'qc_save_order'
- [x] Pass nonce, post_id, post_ids array
- [x] On success:
  - [x] Show brief success indicator (optional)
  - [x] Log to console for debugging
- [x] On error:
  - [x] Show error message to user
  - [x] Log error to console
- [ ] Test: Reorder posts, verify saves via AJAX
- [ ] Test: Reload page, verify order persisted

---

## Phase 9: Integration Testing

### 9.1 Full Workflow Test
- [ ] Create new Query Group: "Test Books"
- [ ] Build query:
  - [ ] Post type: Post
  - [ ] Category: Uncategorized
  - [ ] Limit: 10
- [ ] Click "Load Results"
- [ ] Verify posts load with thumbnails
- [ ] Drag to reorder posts
- [ ] Verify order saves (check hidden input)
- [ ] Click Update/Publish
- [ ] Reload page
- [ ] Verify order persisted
- [ ] Remove a post with X button
- [ ] Verify it's removed and saves
- [ ] Reload page
- [ ] Verify removal persisted

### 9.2 Helper Function Test
- [ ] Get query group ID from database
- [ ] In theme or test file, call:
  ```php
  $post_ids = get_query_group( $id );
  var_dump( $post_ids );
  ```
- [ ] Verify returns array in correct order
- [ ] Test with slug instead of ID
- [ ] Test with non-existent ID (should return empty array)

### 9.3 Real-World Integration Test
- [ ] Create query group in admin
- [ ] Use `get_query_group()` in actual WP_Query:
  ```php
  $query = new WP_Query([
      'post__in' => get_query_group( $id ),
      'orderby' => 'post__in'
  ]);
  ```
- [ ] Verify posts display in correct order on front-end
- [ ] Change order in admin
- [ ] Refresh front-end
- [ ] Verify order updated

---

## Phase 10: Enhancement - Manual Post Addition

### 10.1 Add Posts UI
- [x] Add "Add Posts" button to results meta box
- [x] Create modal/dropdown for post selection
- [x] Implement search functionality (search by title)
- [x] Display post type and thumbnail in search results
- [x] Add selected posts to grid
- [x] Update hidden input and save

### 10.2 Test Manual Addition
- [ ] Use "Add Posts" to add post that wasn't in query
- [ ] Verify it appears in grid
- [ ] Verify it saves and persists
- [ ] Verify it appears in `get_query_group()` results

---

## Phase 11: Enhancement - Query Params Storage

### 11.1 Store Original Query
- [x] When "Load Results" is clicked, save query params to meta
- [x] Meta key: `_query_params`
- [x] Store as serialized array

### 11.2 Refresh from Query Button
- [x] Add "Refresh from Query" button
- [x] Retrieve stored query params (form fields pre-populated from PHP)
- [x] Re-run query
- [x] Replace current results with confirmation dialog
- [x] Button disabled when no saved query params exist

---

## Phase 12: Polish & Documentation

### 12.1 Error Handling
- [x] Add user-friendly error messages for all AJAX failures
- [x] Handle edge cases:
  - [x] No results found
  - [ ] Query timeout
  - [x] Invalid post type selected
  - [x] Deleted posts in saved array (gracefully skipped in PHP render)
- [x] Add JavaScript validation for required fields
- [x] Add PHP validation for all inputs

### 12.2 UI Polish
- [x] Add tooltips/help text to query builder fields
- [x] Add success feedback (e.g., "Order saved!" message)
- [x] Improve loading states (spinner)
- [ ] Add keyboard shortcuts (optional)
- [x] Responsive design for smaller screens
- [x] Accessibility improvements:
  - [x] ARIA labels
  - [x] Keyboard navigation (Delete/Backspace to remove, Escape to close modal)
  - [x] Focus states
  - [x] role="list" / role="listitem" on grid
  - [x] role="dialog" and aria-modal on modal

### 12.3 Code Documentation
- [x] Add PHPDoc to all functions
- [x] Add inline comments explaining complex logic
- [x] Add JSDoc to JavaScript functions
- [x] Document all hooks and filters (query_curator_fetch_args, query_curator_order_saved)

### 12.4 Inline Help
- [x] Add contextual help tab to query group edit screen
- [x] Include usage examples
- [x] Link to full documentation (developer code examples in help tab)

---

## Phase 13: Testing & QA

### 13.1 Browser Testing
- [ ] Test in Chrome
- [ ] Test in Firefox
- [ ] Test in Safari
- [ ] Test in Edge

### 13.2 WordPress Version Testing
- [ ] Test in WordPress 5.9
- [ ] Test in WordPress 6.0
- [ ] Test in WordPress 6.1
- [ ] Test in latest WordPress

### 13.3 PHP Version Testing
- [ ] Test with PHP 7.4
- [ ] Test with PHP 8.0
- [ ] Test with PHP 8.1
- [ ] Test with PHP 8.2

### 13.4 Theme Compatibility
- [ ] Test with Twenty Twenty-One
- [ ] Test with Twenty Twenty-Two
- [ ] Test with Twenty Twenty-Three
- [ ] Test with popular builder theme (Astra, GeneratePress)

### 13.5 Plugin Compatibility
- [ ] Test with Breakdance
- [ ] Test with Elementor
- [ ] Test with WooCommerce
- [ ] Test with ACF (ensure no conflicts)

### 13.6 Performance Testing
- [ ] Test query builder with 1000+ posts
- [ ] Test curating 100+ posts
- [ ] Profile AJAX response times
- [ ] Check for memory leaks in JavaScript

### 13.7 Security Testing
- [ ] Attempt AJAX calls without nonce
- [ ] Attempt AJAX calls without authentication
- [ ] Test SQL injection in inputs
- [ ] Test XSS in inputs
- [ ] Verify all capabilities are checked

---

## Phase 14: Documentation & Release Prep

### 14.1 User Documentation
- [ ] Write user guide with screenshots
- [ ] Create video tutorial (optional)
- [ ] Document all filters and hooks for developers
- [ ] Create FAQ section

### 14.2 Developer Documentation
- [ ] Document helper function with code examples
- [ ] Provide integration examples for popular builders
- [ ] Document available hooks and filters
- [ ] Create developer wiki on GitHub

### 14.3 README Updates
- [ ] Add screenshots to README
- [ ] Update feature list if anything changed
- [ ] Add changelog
- [ ] Update installation instructions

### 14.4 Release Preparation
- [ ] Set version to 1.0.0
- [ ] Create Git tags
- [ ] Build release ZIP file
- [ ] Test ZIP installation from scratch
- [ ] Prepare WordPress.org assets (if submitting)

---

## Phase 15: Future Enhancements (Post-Launch)

### 15.1 v1.1 Features
- [ ] Export/Import query groups as JSON
- [ ] Duplicate query group feature
- [ ] Bulk actions (delete multiple, add multiple)
- [ ] Query group categories/tags
- [ ] Preview mode (see how it looks on front-end)

### 15.2 v1.2 Features
- [ ] Gutenberg block for displaying query groups
- [ ] Shortcode support: `[query_curator id="34"]`
- [ ] Widget for displaying query groups
- [ ] REST API endpoints

### 15.3 v2.0 Features
- [ ] Visual query builder (drag-and-drop interface)
- [ ] Conditional logic (if/then rules)
- [ ] Scheduled query refreshes
- [ ] Multi-user collaboration (lock editing)
- [ ] Query group templates library

---

## Notes

- Test each TODO item before moving to the next
- Mark items as complete only when fully tested
- Document any deviations from the plan
- Ask for clarification if requirements are unclear
- Keep commits small and focused on single TODOs
- Write commit messages that reference TODO numbers

## Blocked Items

Track any items that are blocked here:
- None currently

## Questions/Decisions Needed

Track any questions or decisions needed here:
- None currently
