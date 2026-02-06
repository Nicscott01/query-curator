# Select2 Integration for Query Curator

## Summary

Bundle Select2 v4.0.13 into the plugin and apply it to all select dropdowns in the Query Builder. This gives users searchable, clearable, pill-tag multi-selects for taxonomies and meta keys — especially useful when there are hundreds of terms or meta keys.

---

## Files to Modify / Add

| File | Changes |
|------|---------|
| `assets/vendor/select2/select2.min.js` | **New** — bundled Select2 v4.0.13 JS (~73KB) |
| `assets/vendor/select2/select2.min.css` | **New** — bundled Select2 v4.0.13 CSS (~17KB) |
| `includes/class-meta-boxes.php` | Enqueue Select2 CSS/JS, update dependency arrays, add i18n placeholder strings |
| `assets/js/admin.js` | Add `initSelect2OnCard()` / `destroySelect2In()` helpers; modify 6 functions and handlers for Select2 lifecycle |
| `assets/css/admin.css` | WordPress admin style overrides for Select2 elements |

---

## Implementation Steps

### Step 1: Download and bundle Select2 v4.0.13

Create `assets/vendor/select2/` directory. Download the two minified files from the official release:
- `select2.min.js`
- `select2.min.css`

Source: https://github.com/select2/select2/releases/tag/4.0.13 (standard jQuery build, not the `full` version).

### Step 2: Enqueue Select2 in `class-meta-boxes.php`

In `enqueue_assets()`:

- Enqueue `qc-select2` CSS before `qc-admin` CSS (so admin.css can override Select2 styles)
- Enqueue `qc-select2` JS with `jquery` dependency
- Add `qc-select2` as dependency to both `qc-admin` CSS and JS
- Add 2 new i18n strings: `selectTaxTerms` ("Select terms..."), `selectCompare` ("Compare...")

### Step 3: Add `initSelect2OnCard()` and `destroySelect2In()` helpers in `admin.js`

**`initSelect2OnCard( $card )`** — Initializes Select2 on all selects within a card:
- `.qc-card-taxonomy` — multi-select with placeholder, allowClear, `width: '100%'`
- `.qc-card-post-type` — single select, `minimumResultsForSearch: 10`
- `.qc-meta-key` — single select with placeholder, allowClear, `width: '100%'`
- `.qc-meta-compare` — single select, `minimumResultsForSearch: Infinity` (no search, only 10 items)
- Checks `$( this ).data( 'select2' )` before initializing to avoid double-init

**`destroySelect2In( $container )`** — Destroys Select2 on all selects in a container. Safe no-op if not initialized.

### Step 4: Modify `renderQueryCard()` in `admin.js`

After `$cardsContainer.append( $card )`, initialize Select2 on the post type select immediately. Taxonomy/meta selects don't exist yet — they get initialized later after AJAX.

### Step 5: Modify `loadCardDynamicData()` in `admin.js`

In the `$.when()` callback:
1. Call `destroySelect2In()` on `.qc-taxonomy-container` and `.qc-meta-rows` before re-rendering
2. Call `renderTaxonomies()` and `updateMetaKeyDropdowns()` as before
3. Call `initSelect2OnCard( $card )` after rendering is complete

### Step 6: Modify `renderTaxonomies()` in `admin.js`

Before `$container.empty()`, call `destroySelect2In( $container )` to clean up old instances.

### Step 7: Modify `addMetaRow()` in `admin.js`

After appending the new row HTML, initialize Select2 on the new row's `.qc-meta-key` and `.qc-meta-compare` selects.

### Step 8: Modify `updateMetaKeyDropdowns()` in `admin.js`

For each `.qc-meta-key` select:
1. Destroy Select2 before replacing options HTML
2. Replace options via `.html()`
3. Reinitialize Select2

### Step 9: Modify removal handlers in `admin.js`

- **Remove meta row** (`.qc-remove-meta-row`): Call `destroySelect2In( $row )` before `fadeOut`
- **Remove query card** (`.qc-remove-card`): Call `destroySelect2In( $removedCard )` before `fadeOut`

### Step 10: Add WordPress admin CSS overrides in `admin.css`

Key overrides:
- Border color: `#8c8f94` (WP admin input border)
- Focus state: `#2271b1` border + box-shadow (WP admin blue)
- Multi-select tags: `#2271b1` background, white text, 3px radius
- Tag "x" button: white with hover effect
- Dropdown z-index: `100200` (above WP admin bar and Add Posts modal)
- Search input: match WP admin input styling
- Highlighted option: `#2271b1` background
- Override fixed `height: 100px` on `select[multiple]` to `height: auto` (Select2 manages its own height)
- Meta row flex layout: ensure Select2 container inherits flex sizing
- Responsive (`max-width: 782px`): force `width: 100% !important` on Select2 containers in meta rows

---

## What Does NOT Change

- `gatherQueryData()` — `.val()` works identically with Select2
- Delegated `change` event handlers — Select2 v4 fires native `change` on the original `<select>`
- Preview count triggers — all still work via delegated change events
- Card contributions / undo — unaffected
- AJAX endpoints — no server changes needed

---

## Verification / Testing

1. New Query Group page loads with Select2 on post type dropdown
2. Taxonomy multi-selects render with Select2 pill tags after AJAX
3. Search within taxonomy Select2 — filtering works
4. Select/deselect terms — blue pill tags with "x" to remove
5. Change post type — taxonomy selects destroyed and recreated cleanly
6. "Add Meta Filter" — new meta key and compare selects get Select2
7. Meta key search works (dropdown may have 500+ options)
8. Post type change refreshes meta key dropdowns with new options
9. Remove meta row — no JS errors
10. Remove query card — no JS errors
11. "Load Results" — `gatherQueryData()` collects correct values
12. Preview count updates on every filter change
13. Saved query data pre-selects values on page load
14. Responsive at < 782px — selects fill full width
15. No console errors throughout all operations
