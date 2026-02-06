
# Changes 2/6/26 before rate limits hit around 3:15pm

`includes/class-ajax-handler.php:`

- get_taxonomies() now returns count with each term (line 85)
- fetch_posts() now returns { posts: [...], card_map: { 0: [ids], 1: [ids] } } instead of a flat array — card_map tracks which post IDs each query card contributed (lines 212-258)
- New count_posts() endpoint (line 150) — lightweight version of fetch_posts that only returns deduplicated count, no post data or meta saving

`includes/class-meta-boxes.php:`

- Added <span class="qc-preview-count"></span> to the query builder actions area (line 255)
 - Added 6 new i18n strings: addedPosts, removeCardResults, removedPosts, allResultsExist, previewCount, counting

`assets/js/admin.js (1020 → 1270 lines):`

- New state: cardContributions, previewTimer, previewXhr, $previewCount
- renderTaxonomies() shows term counts: "Fiction (23)"
- mergeResultsIntoGrid(newPosts, cardMap) accepts card map, tracks which posts were actually added per card
- updateCardContributionLabels() shows "Added X posts (Remove)" in card headers
- clearCardContributions() resets all tracking
- Undo handler (.qc-undo-card-results) removes a card's contributed posts from the grid
- schedulePreviewCount() / fetchPreviewCount() — debounced 500ms live count preview via qc_count_posts
- Preview triggers on: post type change, taxonomy change, date change, meta key/compare/value change, limit change, add/remove meta row, add/remove query card
- Load Results cancels preview, reads response.data.posts + response.data.card_map
- Save clears contributions via clearCardContributions()
- Remove post handler updates contribution tracking
- Remove card handler re-indexes contributions
`assets/css/admin.css (675 → 786 lines):`

- .qc-card-contribution — right-aligned label in card header
- .qc-undo-card-results — red underlined link
- .qc-preview-count — italic count next to Load Results button
- .qc-counting — muted gray while counting