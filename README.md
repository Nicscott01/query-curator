# Query Curator

**Build and save curated post queries with drag-and-drop ordering for WordPress**

Query Curator bridges the gap between automatic queries and manual post selection. Filter posts by your criteria, then manually curate, reorder, and save them as reusable groups for use anywhere in your WordPress site.

## The Problem

You're building a bookstore website. The homepage needs to feature 8 carefully selected books, ordered just right. You have a few bad options:

1. **Manual post selection** - Select 8 posts one by one through a dropdown. No filtering first, no cover art to help identify books, tedious searching.

2. **Automatic query** - Show the 8 most recent books by publish date. But what if you want to skip one? What if the order isn't quite right?

3. **Hard-coded IDs** - Copy post IDs into your theme code. Now every change requires a developer.

**Query Curator solves this.** Filter books by published date first, then manually reorder, remove, or add from the results. Save it as "Homepage Books" and use it anywhere.

## Features

### 🎯 Visual Query Builder
- Filter by post type, taxonomy (category/tags), date range, meta keys, and more
- See results with featured images immediately
- Start with automated filtering, then manually refine

### 🎨 Drag-and-Drop Curation
- Reorder posts by dragging
- Remove posts with one click
- Manually add posts beyond the original query
- See cover art/featured images while curating

### 💾 Reusable Query Groups
- Save multiple curated lists
- Name them meaningfully ("Homepage Books", "Featured Products", "Staff Picks")
- Reuse across your entire site
- Edit anytime - changes apply everywhere

### 🔧 Developer-Friendly
- Simple helper function: `get_query_group( $id )`
- Returns ordered array of post IDs
- Works with any theme or page builder
- No proprietary shortcodes or widgets required

### ⚡ Performance-Focused
- No front-end scripts or styles
- Only loads admin assets on query group edit screens
- Leverages WordPress core (no external dependencies)
- Lightweight and fast

## Use Cases

- **Homepage Features**: Curate exactly which posts appear on your homepage
- **Recommended Products**: WooCommerce product recommendations with full control
- **Resource Libraries**: Organize guides, tutorials, or documentation
- **Team Member Showcases**: Order staff profiles manually
- **Portfolio Items**: Control exactly which work appears where
- **Press/Media**: Curate which articles to feature
- **Testimonials**: Select and order customer reviews
- **Event Calendars**: Feature specific upcoming events

## Installation

### From WordPress.org (Future)
1. Go to Plugins → Add New
2. Search for "Query Curator"
3. Click Install Now → Activate

### Manual Installation
1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click Install Now
4. Activate the plugin

### From GitHub
```bash
cd wp-content/plugins
git clone https://github.com/Nicscott01/query-curator.git
```
Then activate via WordPress admin.

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- Modern browser with JavaScript enabled (for admin interface)

## Quick Start Guide

### Step 1: Create a Query Group

1. In WordPress admin, go to **Query Groups → Add New**
2. Give your group a descriptive name (e.g., "Homepage Featured Books")

### Step 2: Build Your Query

1. In the **Query Builder** meta box:
   - Select a post type (e.g., "Products")
   - Add filters (e.g., Category = "Fiction", Published after = "2024-01-01")
   - Click **Load Results**

### Step 3: Curate Your Results

1. Results appear below with featured images
2. **Drag and drop** to reorder posts
3. Click the **X** to remove unwanted posts
4. Use **Add Posts** to manually include others
5. Changes save automatically

### Step 4: Use in Your Theme/Builder

#### Breakdance Example
```php
// In Breakdance Post Loop Builder query
return [
    'post_type' => 'product',
    'post__in' => get_query_group( 34 ), // Your query group ID
    'orderby' => 'post__in' // Preserves your manual order
];
```

#### Elementor Example
Use the Posts widget with a custom query:
```php
add_action( 'elementor/query/my_custom_query', function( $query ) {
    $query->set( 'post__in', get_query_group( 'homepage-books' ) );
    $query->set( 'orderby', 'post__in' );
});
```

#### Standard WP_Query
```php
$query = new WP_Query([
    'post_type' => 'product',
    'post__in' => get_query_group( 34 ),
    'orderby' => 'post__in',
    'posts_per_page' => -1
]);
```

#### Get_posts()
```php
$posts = get_posts([
    'post_type' => 'product',
    'include' => get_query_group( 34 ),
    'orderby' => 'post__in'
]);
```

## Helper Function Reference

### `get_query_group( $query_group )`

Retrieves the curated post IDs from a query group.

**Parameters:**
- `$query_group` (int|string) - Query group post ID or post slug

**Returns:**
- (array) Ordered array of post IDs, or empty array if not found

**Examples:**

```php
// By ID
$post_ids = get_query_group( 34 );

// By slug
$post_ids = get_query_group( 'homepage-featured-books' );

// Check if query group has posts
if ( $post_ids = get_query_group( 34 ) ) {
    // Do something with $post_ids
}

// Use with conditional
$args = [
    'post_type' => 'product',
];

if ( is_front_page() ) {
    $args['post__in'] = get_query_group( 'homepage-books' );
    $args['orderby'] = 'post__in';
}

$query = new WP_Query( $args );
```

## Query Builder Filters

The query builder supports the following filters:

### Basic Filters
- **Post Type** - Any public post type
- **Post Status** - Published, Draft, Pending, etc.
- **Number of Results** - Limit initial results (default: 100)

### Taxonomy Filters
- **Category** - Filter by category (for posts)
- **Tag** - Filter by tag (for posts)
- **Custom Taxonomies** - Any custom taxonomy registered on the post type

### Date Filters
- **Published After** - Only posts published after this date
- **Published Before** - Only posts published before this date
- **Modified After** - Only posts modified after this date

### Meta Filters
- **Meta Key** - Filter by custom field key
- **Meta Value** - Filter by custom field value
- **Meta Compare** - Comparison operator (equals, not equals, greater than, etc.)

### Advanced Filters
- **Author** - Filter by post author
- **Search** - Keyword search in title/content
- **Parent** - For hierarchical post types

## Tips & Best Practices

### For Content Managers

**Naming Convention**
Use descriptive names that indicate where the group is used:
- ✅ "Homepage - Featured Books"
- ✅ "Sidebar - Recent Articles"
- ❌ "Query 1"
- ❌ "Test"

**Start with Filters**
Always use the query builder first to narrow results, then manually refine. Don't start from scratch every time.

**Use Slugs for Stability**
When possible, have your developer use slugs instead of IDs:
```php
get_query_group( 'homepage-books' ) // Good - won't break if query group is deleted/recreated
get_query_group( 34 ) // Less ideal - ID might change
```

### For Developers

**Always Use `orderby => 'post__in'`**
This preserves the manual order from the query group:
```php
// Correct
'post__in' => get_query_group( 34 ),
'orderby' => 'post__in'

// Wrong - will ignore manual order
'post__in' => get_query_group( 34 ),
'orderby' => 'date'
```

**Handle Empty Results**
Always check if the query group returns posts:
```php
$post_ids = get_query_group( 34 );

if ( empty( $post_ids ) ) {
    // Fallback behavior
    $post_ids = get_posts([
        'fields' => 'ids',
        'post_type' => 'product',
        'posts_per_page' => 8
    ]);
}
```

**Don't Hardcode IDs**
Create a settings page or use theme customizer to let users select which query group to display:
```php
// Good - user can change via customizer
$query_group_id = get_theme_mod( 'homepage_query_group', 34 );
$post_ids = get_query_group( $query_group_id );

// Bad - hardcoded
$post_ids = get_query_group( 34 );
```

## Frequently Asked Questions

### Can I use multiple query groups on the same page?
Yes! Each query group is independent. You can use different groups in different sections:
```php
// Hero section
$hero_posts = get_query_group( 'homepage-hero' );

// Sidebar
$sidebar_posts = get_query_group( 'sidebar-featured' );
```

### What happens if I delete a post that's in a query group?
The query group will automatically skip deleted posts. The `get_query_group()` function only returns valid post IDs.

### Can I filter by custom fields?
Yes! Use the Meta Key and Meta Value filters in the query builder. For example, to show only books with `in_stock = yes`:
- Meta Key: `in_stock`
- Meta Compare: `=`
- Meta Value: `yes`

### Does this work with WooCommerce?
Absolutely! Products are just a custom post type. Use Query Curator to curate featured products, sale items, or any custom product collections.

### Can non-admin users create query groups?
By default, any user who can edit posts can create query groups. You can customize this with the `query_curator_capability` filter:
```php
add_filter( 'query_curator_capability', function() {
    return 'manage_options'; // Only administrators
});
```

### Does this affect my site's performance?
No. Query Curator only loads admin scripts on the query group edit screen. On the front-end, `get_query_group()` is a simple meta query that returns cached post IDs.

### Can I export/import query groups?
Query groups are standard WordPress posts, so they work with any import/export tool:
- WordPress's built-in export (Tools → Export)
- WP All Import/Export
- Manual database export

## Roadmap

### v1.1 (Planned)
- [ ] "Refresh from Query" button to reload original query results
- [ ] Export/Import query groups as JSON
- [ ] Bulk actions (add multiple posts at once)

### v1.2 (Planned)
- [ ] Query group templates
- [ ] Duplicate query group
- [ ] Preview mode (see how it looks on front-end)

### v2.0 (Future)
- [ ] Gutenberg block for displaying query groups
- [ ] Shortcode support `[query_curator id="34"]`
- [ ] REST API endpoints
- [ ] Conditional logic (if/then rules)

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress coding standards
4. Test your changes thoroughly
5. Commit with clear messages (`git commit -m 'Add amazing feature'`)
6. Push to your branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup
```bash
# Clone the repo
git clone https://github.com/Nicscott01/query-curator.git
cd query-curator

# Install in WordPress plugins directory
# No build process required - this is pure PHP/JS
```

### Running Tests
```bash
# Coming soon - PHPUnit tests
composer test

# WordPress Coding Standards
phpcs --standard=WordPress query-curator.php includes/
```

## Support

- **Documentation**: [github.com/Nicscott01/query-curator/wiki](https://github.com/Nicscott01/query-curator/wiki)
- **Issues**: [github.com/Nicscott01/query-curator/issues](https://github.com/Nicscott01/query-curator/issues)
- **Discussions**: [github.com/Nicscott01/query-curator/discussions](https://github.com/Nicscott01/query-curator/discussions)

## Credits

**Author**: Nicolas Scott ([Creare Web Solutions](https://crearewebsolutions.com))

**Built with**:
- WordPress Core APIs
- jQuery & jQuery UI (WordPress bundled)
- Inspiration from the WordPress community

## License

Query Curator is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

**Built by developers, for developers (and the content managers who love them).**

If Query Curator saves you time, consider [⭐ starring the repo](https://github.com/Nicscott01/query-curator) or [☕ buying me a coffee](https://buymeacoffee.com/nicscott01)!
