/**
 * Query Curator - Admin JavaScript
 *
 * Handles:
 * - Dynamic query card system (multiple cards with OR logic)
 * - Dynamic taxonomy loading per post type
 * - Searchable meta key dropdowns from the database
 * - Repeatable meta filter rows
 * - Curated post grid with locked/new distinction
 * - jQuery UI Sortable drag-and-drop reordering
 * - Manual save with dirty state tracking
 * - "Add Posts" modal with live search
 * - Code Snippets copy-to-clipboard
 *
 * @package QueryCurator
 */

/* global jQuery, qcData */

( function( $ ) {
	'use strict';

	$( document ).ready( function() {

		// -----------------------------------------------------------------
		// State
		// -----------------------------------------------------------------

		/** @type {boolean} Whether there are unsaved changes. */
		var isDirty = false;

		/** @type {number[]} IDs currently considered "saved" (locked). */
		var lockedIds = ( qcData.savedCuratedIds || [] ).map( Number );

		/** @type {number} Auto-incrementing index for query cards. */
		var cardCounter = 0;

		/** @type {Object} Cache of taxonomy data keyed by post type. */
		var taxonomyCache = {};

		/** @type {Object} Cache of meta keys keyed by post type. */
		var metaKeyCache = {};

		/** @type {string[]} Valid compare operators for meta queries. */
		var validCompares = [ '=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS' ];

		/** @type {Object} Tracks which post IDs each query card contributed. Keyed by card index. */
		var cardContributions = {};

		/** @type {number|null} Timer ID for debounced preview count. */
		var previewTimer = null;

		/** @type {jqXHR|null} Active preview count AJAX request. */
		var previewXhr = null;

		// -----------------------------------------------------------------
		// Cache DOM references
		// -----------------------------------------------------------------

		var $cardsContainer = $( '#qc-query-cards' );
		var $addCardBtn     = $( '#qc-add-query-card' );
		var $loadButton     = $( '#qc-load-results' );
		var $refreshButton  = $( '#qc-refresh-query' );
		var $spinner        = $( '.qc-loading' );
		var $grid           = $( '#qc-results-grid' );
		var $hiddenInput    = $( '#qc-curated-post-ids' );
		var $emptyState     = $( '.qc-empty-state' );
		var $resultsCount   = $( '.qc-results-count' );
		var $saveBtn        = $( '#qc-save-posts-btn' );
		var $previewCount   = $( '.qc-preview-count' );

		// Add Posts modal elements.
		var $addBtn        = $( '#qc-add-posts-btn' );
		var $modal         = $( '#qc-add-posts-modal' );
		var $modalClose    = $modal.find( '.qc-modal-close' );
		var $modalBackdrop = $modal.find( '.qc-modal-backdrop' );
		var $searchInput   = $( '#qc-search-input' );
		var $searchBtn     = $( '#qc-search-btn' );
		var $searchSpinner = $( '.qc-search-spinner' );
		var $searchResults = $( '#qc-search-results' );

		// -----------------------------------------------------------------
		// Utility: Escape helpers (XSS prevention in JS-rendered HTML)
		// -----------------------------------------------------------------

		/**
		 * Escape a value for safe use inside an HTML attribute.
		 *
		 * @param {*} str Value to escape.
		 * @return {string} Escaped string.
		 */
		function escAttr( str ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML.replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
		}

		/**
		 * Escape a value for safe use inside HTML text content.
		 *
		 * @param {*} str Value to escape.
		 * @return {string} Escaped string.
		 */
		function escHtml( str ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML;
		}

		// -----------------------------------------------------------------
		// Utility: Get current curated IDs from the hidden input
		// -----------------------------------------------------------------

		/**
		 * Parse the hidden input value into an array of numeric IDs.
		 *
		 * @return {number[]} Array of post IDs currently in the curated list.
		 */
		function getCuratedIds() {
			var val = $hiddenInput.val();
			if ( ! val ) {
				return [];
			}
			return val.split( ',' ).map( Number ).filter( Boolean );
		}

		// -----------------------------------------------------------------
		// Dirty state management
		// -----------------------------------------------------------------

		/**
		 * Mark the session as having unsaved changes.
		 */
		function markDirty() {
			if ( ! isDirty ) {
				isDirty = true;
				$saveBtn.prop( 'disabled', false ).addClass( 'qc-dirty' );
			}
		}

		/**
		 * Mark the session as clean (all changes saved).
		 */
		function markClean() {
			isDirty = false;
			$saveBtn.prop( 'disabled', true ).removeClass( 'qc-dirty' );
		}

		// Warn on page leave with unsaved changes.
		$( window ).on( 'beforeunload', function() {
			if ( isDirty ) {
				return qcData.i18n.unsavedChanges;
			}
		} );

		// -----------------------------------------------------------------
		// Results count display
		// -----------------------------------------------------------------

		/**
		 * Update the "X post(s) curated" counter above the grid.
		 */
		function updateResultsCount() {
			var count = $grid.find( '.qc-post-item' ).length;
			if ( count > 0 ) {
				$resultsCount.text( count + ' post(s) curated' );
			} else {
				$resultsCount.text( '' );
			}
		}

		// =====================================================================
		//  QUERY CARD SYSTEM
		// =====================================================================

		/**
		 * Render a query card and append it to the container.
		 *
		 * @param {Object} [data] Optional saved query data to populate fields.
		 * @return {jQuery} The created card element.
		 */
		function renderQueryCard( data ) {
			data = data || {};
			var idx = cardCounter++;
			var title = qcData.i18n.queryCard.replace( '%d', idx + 1 );

			// Build post type options from localized data.
			var ptOptions = '';
			$.each( qcData.postTypes, function( i, pt ) {
				var sel = ( data.post_type && data.post_type === pt.name ) ? ' selected' : '';
				if ( ! data.post_type && i === 0 ) {
					sel = ' selected';
				}
				ptOptions += '<option value="' + escAttr( pt.name ) + '"' + sel + '>' + escHtml( pt.label ) + '</option>';
			} );

			// Build compare options.
			var compareOptions = '';
			$.each( validCompares, function( i, cmp ) {
				compareOptions += '<option value="' + escAttr( cmp ) + '">' + escHtml( cmp ) + '</option>';
			} );

			var html =
				'<div class="qc-query-card" data-card-idx="' + idx + '">' +
					'<div class="qc-query-card-header">' +
						'<strong>' + escHtml( title ) + '</strong>' +
						'<button type="button" class="qc-remove-card button-link">' + escHtml( qcData.i18n.removeQuery ) + '</button>' +
					'</div>' +
					'<div class="qc-query-card-body">' +
						'<div class="qc-filter-row">' +
							'<div class="qc-filter-field">' +
								'<label>' + escHtml( qcData.i18n.postType ) + '</label>' +
								'<select class="qc-card-post-type">' + ptOptions + '</select>' +
							'</div>' +
							'<div class="qc-filter-field qc-field-limit">' +
								'<label>' + escHtml( qcData.i18n.resultsLimit ) + '</label>' +
								'<input type="number" class="qc-card-limit" value="' + escAttr( data.limit || 100 ) + '" min="1" max="500" />' +
							'</div>' +
						'</div>' +
						'<div class="qc-taxonomy-container">' +
							'<p class="qc-loading-msg">' + escHtml( qcData.i18n.loadingTaxonomies ) + '</p>' +
						'</div>' +
						'<div class="qc-filter-row">' +
							'<div class="qc-filter-field">' +
								'<label>' + escHtml( qcData.i18n.publishedAfter ) + '</label>' +
								'<input type="date" class="qc-card-date-after" value="' + escAttr( data.date_after || '' ) + '" />' +
							'</div>' +
							'<div class="qc-filter-field">' +
								'<label>' + escHtml( qcData.i18n.publishedBefore ) + '</label>' +
								'<input type="date" class="qc-card-date-before" value="' + escAttr( data.date_before || '' ) + '" />' +
							'</div>' +
						'</div>' +
						'<div class="qc-meta-section">' +
							'<label class="qc-meta-section-label">' + escHtml( qcData.i18n.metaKey ) + ' Filters</label>' +
							'<div class="qc-meta-rows"></div>' +
							'<button type="button" class="qc-add-meta-row button button-small">' + escHtml( qcData.i18n.addMetaFilter ) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>';

			var $card = $( html );
			$cardsContainer.append( $card );

			// Determine post type to load taxonomies for.
			var postType = data.post_type || ( qcData.postTypes.length > 0 ? qcData.postTypes[0].name : 'post' );

			// Load taxonomies and meta keys for this card.
			loadCardDynamicData( $card, postType, data );

			// Add saved meta rows.
			if ( data.meta_queries && data.meta_queries.length > 0 ) {
				$.each( data.meta_queries, function( i, mq ) {
					addMetaRow( $card, mq );
				} );
			}

			updateCardHeaders();
			updateRemoveButtons();

			return $card;
		}

		/**
		 * Load taxonomies and meta keys for a card via AJAX.
		 *
		 * @param {jQuery} $card    The card element.
		 * @param {string} postType Post type name.
		 * @param {Object} [data]   Optional saved data with taxonomy selections.
		 */
		function loadCardDynamicData( $card, postType, data ) {
			data = data || {};
			var $taxContainer = $card.find( '.qc-taxonomy-container' );
			$taxContainer.html( '<p class="qc-loading-msg">' + escHtml( qcData.i18n.loadingTaxonomies ) + '</p>' );

			// Load taxonomies (with cache).
			var taxPromise;
			if ( taxonomyCache[ postType ] ) {
				taxPromise = $.Deferred().resolve( taxonomyCache[ postType ] ).promise();
			} else {
				taxPromise = $.post( qcData.ajaxurl, {
					action: 'qc_get_taxonomies',
					nonce: qcData.nonce,
					post_type: postType,
				} ).then( function( response ) {
					if ( response.success ) {
						taxonomyCache[ postType ] = response.data;
						return response.data;
					}
					return [];
				} );
			}

			// Load meta keys (with cache).
			var metaPromise;
			if ( metaKeyCache[ postType ] ) {
				metaPromise = $.Deferred().resolve( metaKeyCache[ postType ] ).promise();
			} else {
				metaPromise = $.post( qcData.ajaxurl, {
					action: 'qc_get_meta_keys',
					nonce: qcData.nonce,
					post_type: postType,
				} ).then( function( response ) {
					if ( response.success ) {
						metaKeyCache[ postType ] = response.data;
						return response.data;
					}
					return [];
				} );
			}

			// When both load, render the UI.
			$.when( taxPromise, metaPromise ).done( function( taxonomies, metaKeys ) {
				renderTaxonomies( $card, taxonomies, data.taxonomies || {} );
				// Store meta keys on the card for use when adding meta rows.
				$card.data( 'metaKeys', metaKeys );
				// Update existing meta rows with the new key options.
				updateMetaKeyDropdowns( $card, metaKeys );
			} );
		}

		/**
		 * Render taxonomy multi-selects inside a card.
		 *
		 * @param {jQuery} $card      The card element.
		 * @param {Array}  taxonomies Array of taxonomy objects from AJAX.
		 * @param {Object} selected   Map of taxonomy_name => [term_ids].
		 */
		function renderTaxonomies( $card, taxonomies, selected ) {
			var $container = $card.find( '.qc-taxonomy-container' );
			$container.empty();

			if ( ! taxonomies || taxonomies.length === 0 ) {
				$container.html( '<p class="qc-filter-help" style="margin:0;">No taxonomies available for this post type.</p>' );
				return;
			}

			var html = '<div class="qc-filter-row qc-taxonomy-row">';
			$.each( taxonomies, function( i, tax ) {
				var selectedTerms = selected[ tax.name ] || [];
				var options = '';
				$.each( tax.terms, function( j, term ) {
					var sel = ( selectedTerms.indexOf( term.id ) !== -1 ) ? ' selected' : '';
					var label = term.name;
					if ( typeof term.count !== 'undefined' ) {
						label += ' (' + term.count + ')';
					}
					options += '<option value="' + escAttr( term.id ) + '"' + sel + '>' + escHtml( label ) + '</option>';
				} );

				html += '<div class="qc-filter-field">' +
					'<label>' + escHtml( tax.label ) + '</label>' +
					'<select class="qc-card-taxonomy" data-taxonomy="' + escAttr( tax.name ) + '" multiple>' +
						options +
					'</select>' +
				'</div>';
			} );
			html += '</div>';

			$container.html( html );
		}

		/**
		 * Add a meta filter row to a card.
		 *
		 * @param {jQuery} $card The card element.
		 * @param {Object} [data] Optional saved meta data { key, compare, value }.
		 */
		function addMetaRow( $card, data ) {
			data = data || {};
			var metaKeys = $card.data( 'metaKeys' ) || [];

			// Build key dropdown options.
			var keyOptions = '<option value="">' + escHtml( qcData.i18n.selectMetaKey ) + '</option>';
			$.each( metaKeys, function( i, key ) {
				var sel = ( data.key && data.key === key ) ? ' selected' : '';
				keyOptions += '<option value="' + escAttr( key ) + '"' + sel + '>' + escHtml( key ) + '</option>';
			} );

			// If saved key isn't in the list, add it.
			if ( data.key && metaKeys.indexOf( data.key ) === -1 ) {
				keyOptions += '<option value="' + escAttr( data.key ) + '" selected>' + escHtml( data.key ) + '</option>';
			}

			// Build compare dropdown.
			var compareOptions = '';
			$.each( validCompares, function( i, cmp ) {
				var sel = ( data.compare && data.compare === cmp ) ? ' selected' : '';
				compareOptions += '<option value="' + escAttr( cmp ) + '"' + sel + '>' + escHtml( cmp ) + '</option>';
			} );

			var html =
				'<div class="qc-meta-row">' +
					'<select class="qc-meta-key">' + keyOptions + '</select>' +
					'<select class="qc-meta-compare">' + compareOptions + '</select>' +
					'<input type="text" class="qc-meta-value" value="' + escAttr( data.value || '' ) + '" placeholder="' + escAttr( qcData.i18n.metaValue ) + '" />' +
					'<button type="button" class="qc-remove-meta-row button-link" title="Remove">&times;</button>' +
				'</div>';

			$card.find( '.qc-meta-rows' ).append( html );
		}

		/**
		 * Update meta key dropdowns in existing rows when keys reload.
		 *
		 * @param {jQuery} $card    The card element.
		 * @param {Array}  metaKeys Array of meta key strings.
		 */
		function updateMetaKeyDropdowns( $card, metaKeys ) {
			$card.find( '.qc-meta-key' ).each( function() {
				var $select    = $( this );
				var currentVal = $select.val();

				var options = '<option value="">' + escHtml( qcData.i18n.selectMetaKey ) + '</option>';
				$.each( metaKeys, function( i, key ) {
					var sel = ( key === currentVal ) ? ' selected' : '';
					options += '<option value="' + escAttr( key ) + '"' + sel + '>' + escHtml( key ) + '</option>';
				} );

				// Preserve current selection even if not in list.
				if ( currentVal && metaKeys.indexOf( currentVal ) === -1 ) {
					options += '<option value="' + escAttr( currentVal ) + '" selected>' + escHtml( currentVal ) + '</option>';
				}

				$select.html( options );
			} );
		}

		/**
		 * Update query card header numbers after add/remove.
		 */
		function updateCardHeaders() {
			$cardsContainer.find( '.qc-query-card' ).each( function( i ) {
				var title = qcData.i18n.queryCard.replace( '%d', i + 1 );
				$( this ).find( '.qc-query-card-header strong' ).text( title );
			} );
		}

		/**
		 * Show/hide remove buttons based on card count.
		 * Must have at least one card.
		 */
		function updateRemoveButtons() {
			var $cards = $cardsContainer.find( '.qc-query-card' );
			if ( $cards.length <= 1 ) {
				$cards.find( '.qc-remove-card' ).hide();
			} else {
				$cards.find( '.qc-remove-card' ).show();
			}
		}

		/**
		 * Gather all query card data into the multi-query format for AJAX.
		 *
		 * @return {Array} Array of query card objects.
		 */
		function gatherQueryData() {
			var queries = [];

			$cardsContainer.find( '.qc-query-card' ).each( function() {
				var $card = $( this );
				var query = {
					post_type:    $card.find( '.qc-card-post-type' ).val(),
					taxonomies:   {},
					date_after:   $card.find( '.qc-card-date-after' ).val() || '',
					date_before:  $card.find( '.qc-card-date-before' ).val() || '',
					meta_queries: [],
					limit:        parseInt( $card.find( '.qc-card-limit' ).val(), 10 ) || 100,
				};

				// Gather taxonomy selections.
				$card.find( '.qc-card-taxonomy' ).each( function() {
					var taxName = $( this ).data( 'taxonomy' );
					var vals    = $( this ).val();
					if ( vals && vals.length > 0 ) {
						query.taxonomies[ taxName ] = vals.map( Number );
					}
				} );

				// Gather meta rows.
				$card.find( '.qc-meta-row' ).each( function() {
					var key     = $( this ).find( '.qc-meta-key' ).val();
					var compare = $( this ).find( '.qc-meta-compare' ).val();
					var value   = $( this ).find( '.qc-meta-value' ).val();
					if ( key ) {
						query.meta_queries.push( {
							key:     key,
							compare: compare || '=',
							value:   value || '',
						} );
					}
				} );

				queries.push( query );
			} );

			return queries;
		}

		// -----------------------------------------------------------------
		// Query card event handlers
		// -----------------------------------------------------------------

		// Add new query card.
		$addCardBtn.on( 'click', function( e ) {
			e.preventDefault();
			renderQueryCard();
			schedulePreviewCount();
		} );

		// Remove query card.
		$cardsContainer.on( 'click', '.qc-remove-card', function( e ) {
			e.preventDefault();
			var $removedCard = $( this ).closest( '.qc-query-card' );
			var removedIdx = $cardsContainer.find( '.qc-query-card' ).index( $removedCard );

			$removedCard.fadeOut( 200, function() {
				$( this ).remove();

				// Re-index cardContributions after card removal.
				var newContributions = {};
				$.each( cardContributions, function( idx, ids ) {
					idx = parseInt( idx, 10 );
					if ( idx === removedIdx ) {
						return; // skip removed card
					}
					var newIdx = idx > removedIdx ? idx - 1 : idx;
					newContributions[ newIdx ] = ids;
				} );
				cardContributions = newContributions;

				updateCardHeaders();
				updateRemoveButtons();
				updateCardContributionLabels();
				schedulePreviewCount();
			} );
		} );

		// Post type change — reload taxonomies and meta keys.
		$cardsContainer.on( 'change', '.qc-card-post-type', function() {
			var $card    = $( this ).closest( '.qc-query-card' );
			var postType = $( this ).val();
			loadCardDynamicData( $card, postType );
		} );

		// Add meta filter row.
		$cardsContainer.on( 'click', '.qc-add-meta-row', function( e ) {
			e.preventDefault();
			var $card = $( this ).closest( '.qc-query-card' );
			addMetaRow( $card );
			schedulePreviewCount();
		} );

		// Remove meta filter row.
		$cardsContainer.on( 'click', '.qc-remove-meta-row', function( e ) {
			e.preventDefault();
			$( this ).closest( '.qc-meta-row' ).fadeOut( 150, function() {
				$( this ).remove();
				schedulePreviewCount();
			} );
		} );

		// Undo card results — remove posts contributed by a specific card.
		$cardsContainer.on( 'click', '.qc-undo-card-results', function( e ) {
			e.preventDefault();
			var cardIdx = parseInt( $( this ).data( 'card-index' ), 10 );
			var ids = cardContributions[ cardIdx ];

			if ( ! ids || ids.length === 0 ) {
				return;
			}

			var removedCount = 0;
			$.each( ids, function( i, id ) {
				var $item = $grid.find( '.qc-post-item[data-post-id="' + id + '"]' );
				if ( $item.length ) {
					$item.remove();
					removedCount++;
				}
			} );

			delete cardContributions[ cardIdx ];
			updateHiddenInput();
			updateCardContributionLabels();

			if ( removedCount > 0 ) {
				markDirty();
				var msg = qcData.i18n.removedPosts.replace( '%d', removedCount );
				showNotice( msg, 'success' );
			}

			if ( $grid.find( '.qc-post-item' ).length === 0 ) {
				$emptyState.show();
			}
		} );

		// -----------------------------------------------------------------
		// Preview count triggers — fire on any filter change.
		// -----------------------------------------------------------------

		// Post type change.
		$cardsContainer.on( 'change', '.qc-card-post-type', function() {
			schedulePreviewCount();
		} );

		// Taxonomy selection change.
		$cardsContainer.on( 'change', '.qc-card-taxonomy', function() {
			schedulePreviewCount();
		} );

		// Date input change.
		$cardsContainer.on( 'change', '.qc-card-date-after, .qc-card-date-before', function() {
			schedulePreviewCount();
		} );

		// Meta row changes.
		$cardsContainer.on( 'change', '.qc-meta-key, .qc-meta-compare', function() {
			schedulePreviewCount();
		} );
		$cardsContainer.on( 'input', '.qc-meta-value', function() {
			schedulePreviewCount();
		} );

		// Limit change.
		$cardsContainer.on( 'change', '.qc-card-limit', function() {
			schedulePreviewCount();
		} );

		// =====================================================================
		//  POST CARD SYSTEM
		// =====================================================================

		/**
		 * Build a post card element from a post data object.
		 *
		 * @param {Object}  post     Post data with id, title, thumbnail, type.
		 * @param {boolean} isNew    Whether this is a new (unsaved) post.
		 * @return {jQuery} The constructed card element.
		 */
		function buildPostCard( post, isNew ) {
			var thumbnailHtml;
			var statusClass = isNew ? 'qc-new' : 'qc-locked';

			if ( post.thumbnail ) {
				thumbnailHtml = '<img src="' + escAttr( post.thumbnail ) + '" alt="' + escAttr( post.title ) + '" />';
			} else {
				thumbnailHtml = '<div class="qc-no-thumbnail">No Image</div>';
			}

			var badgeHtml = isNew ? '<span class="qc-new-badge">' + escHtml( qcData.i18n.newPost ) + '</span>' : '';

			return $(
				'<div class="qc-post-item ' + statusClass + '" data-post-id="' + escAttr( post.id ) + '" role="listitem" tabindex="0">' +
					badgeHtml +
					'<div class="qc-post-thumbnail">' + thumbnailHtml + '</div>' +
					'<span class="qc-post-title">' + escHtml( post.title ) + '</span>' +
					'<span class="qc-post-type">' + escHtml( post.type ) + '</span>' +
					'<button type="button" class="qc-remove-post" title="Remove" aria-label="Remove ' + escAttr( post.title ) + '">&times;</button>' +
				'</div>'
			);
		}

		// -----------------------------------------------------------------
		// Merge results into grid (locked/new logic)
		// -----------------------------------------------------------------

		/**
		 * Merge new query results into the grid.
		 * Locked posts stay in place. New posts are appended below.
		 *
		 * @param {Array}  newPosts Array of post data objects from AJAX.
		 * @param {Object} [cardMap] Map of card index => array of contributed post IDs.
		 */
		function mergeResultsIntoGrid( newPosts, cardMap ) {
			if ( ! newPosts || newPosts.length === 0 ) {
				showNotice( qcData.i18n.noResults, 'error' );
				updateCardContributionLabels();
				return;
			}

			// Get current IDs in the grid.
			var currentIds = getCuratedIds();
			var addedCount = 0;

			// Build a set of added IDs for tracking card contributions.
			var actuallyAdded = {};

			$.each( newPosts, function( index, post ) {
				// Skip if already in grid (locked or otherwise).
				if ( currentIds.indexOf( post.id ) !== -1 ) {
					return; // continue
				}
				// Append as new.
				var $card = buildPostCard( post, true );
				$card.hide().appendTo( $grid ).fadeIn( 200 );
				currentIds.push( post.id );
				actuallyAdded[ post.id ] = true;
				addedCount++;
			} );

			// Track card contributions — only IDs that were actually added.
			if ( cardMap ) {
				$.each( cardMap, function( cardIdx, ids ) {
					var contributed = [];
					$.each( ids, function( i, id ) {
						if ( actuallyAdded[ id ] ) {
							contributed.push( id );
						}
					} );
					if ( contributed.length > 0 ) {
						cardContributions[ cardIdx ] = contributed;
					}
				} );
			}

			if ( addedCount > 0 ) {
				$emptyState.hide();
				updateHiddenInput();
				initSortable();
				markDirty();
				var msg = qcData.i18n.postsLoaded.replace( '%d', addedCount );
				showNotice( msg + ' (new)', 'success' );
			} else {
				showNotice( qcData.i18n.allResultsExist, 'success' );
			}

			updateCardContributionLabels();
		}

		// -----------------------------------------------------------------
		// Card contribution labels + undo
		// -----------------------------------------------------------------

		/**
		 * Update contribution labels on each query card header.
		 * Shows "Added X posts (Remove)" for cards that contributed new posts.
		 */
		function updateCardContributionLabels() {
			$cardsContainer.find( '.qc-query-card' ).each( function( i ) {
				var $card = $( this );
				var $header = $card.find( '.qc-query-card-header' );

				// Remove existing contribution label.
				$header.find( '.qc-card-contribution' ).remove();

				var contributed = cardContributions[ i ];
				if ( contributed && contributed.length > 0 ) {
					var label = qcData.i18n.addedPosts.replace( '%d', contributed.length );
					var html = '<span class="qc-card-contribution">' +
						escHtml( label ) +
						' <a href="#" class="qc-undo-card-results" data-card-index="' + i + '">' +
							escHtml( qcData.i18n.removeCardResults ) +
						'</a>' +
					'</span>';
					$header.find( '.qc-remove-card' ).before( html );
				}
			} );
		}

		/**
		 * Clear all card contribution tracking state and labels.
		 */
		function clearCardContributions() {
			cardContributions = {};
			$cardsContainer.find( '.qc-card-contribution' ).remove();
		}

		// -----------------------------------------------------------------
		// Sortable initialization
		// -----------------------------------------------------------------

		/**
		 * (Re-)initialize jQuery UI Sortable on the results grid.
		 */
		function initSortable() {
			if ( $grid.hasClass( 'ui-sortable' ) ) {
				$grid.sortable( 'destroy' );
			}

			$grid.sortable( {
				cursor: 'move',
				placeholder: 'qc-post-placeholder',
				opacity: 0.6,
				tolerance: 'pointer',
				update: function() {
					updateHiddenInput();
					markDirty();
				}
			} );
		}

		// -----------------------------------------------------------------
		// Hidden input sync
		// -----------------------------------------------------------------

		/**
		 * Read the current DOM order and write it back to the hidden input.
		 */
		function updateHiddenInput() {
			var ids = [];
			$grid.find( '.qc-post-item' ).each( function() {
				ids.push( $( this ).data( 'post-id' ) );
			} );
			$hiddenInput.val( ids.join( ',' ) );
			updateResultsCount();
		}

		// -----------------------------------------------------------------
		// Save order via AJAX
		// -----------------------------------------------------------------

		/**
		 * Persist the current curated order to the database.
		 */
		function saveOrder() {
			var postIds = getCuratedIds();
			$saveBtn.prop( 'disabled', true );

			$.post( qcData.ajaxurl, {
				action:   'qc_save_order',
				nonce:    qcData.nonce,
				post_id:  qcData.postId,
				post_ids: postIds
			}, function( response ) {
				if ( response.success ) {
					// All posts become "locked" after save.
					lockedIds = postIds.slice();
					$grid.find( '.qc-post-item' ).removeClass( 'qc-new' ).addClass( 'qc-locked' );
					$grid.find( '.qc-new-badge' ).remove();
					clearCardContributions();
					markClean();
					showNotice( qcData.i18n.postsSaved, 'success' );
				} else {
					$saveBtn.prop( 'disabled', false );
					showNotice( response.data || qcData.i18n.errorSaving, 'error' );
				}
			} ).fail( function() {
				$saveBtn.prop( 'disabled', false );
				showNotice( qcData.i18n.failedSave, 'error' );
			} );
		}

		// -----------------------------------------------------------------
		// Notices
		// -----------------------------------------------------------------

		/**
		 * Show a temporary status banner inside the results wrapper.
		 *
		 * @param {string} message Text to display.
		 * @param {string} type    'success' or 'error'.
		 */
		function showNotice( message, type ) {
			$( '.qc-notice' ).remove();

			var $notice = $( '<div class="qc-notice qc-notice-' + escAttr( type ) + '">' + escHtml( message ) + '</div>' );
			$grid.closest( '.qc-results-wrapper' ).prepend( $notice );

			setTimeout( function() {
				$notice.fadeOut( 300, function() {
					$notice.remove();
				} );
			}, 3000 );
		}

		// -----------------------------------------------------------------
		// Live preview count (debounced)
		// -----------------------------------------------------------------

		/**
		 * Schedule a debounced preview count request.
		 * Cancels any pending request and sets a 500ms timer.
		 */
		function schedulePreviewCount() {
			if ( previewTimer ) {
				clearTimeout( previewTimer );
			}
			previewTimer = setTimeout( fetchPreviewCount, 500 );
		}

		/**
		 * Fetch the approximate result count from the server.
		 */
		function fetchPreviewCount() {
			previewTimer = null;

			// Cancel any in-flight request.
			if ( previewXhr ) {
				previewXhr.abort();
				previewXhr = null;
			}

			var queries = gatherQueryData();

			// Skip if no post type is selected in any card.
			var hasPostType = false;
			$.each( queries, function( i, q ) {
				if ( q.post_type ) {
					hasPostType = true;
					return false;
				}
			} );
			if ( ! hasPostType ) {
				$previewCount.text( '' ).removeClass( 'qc-counting' );
				return;
			}

			$previewCount.text( qcData.i18n.counting ).addClass( 'qc-counting' );

			previewXhr = $.post( qcData.ajaxurl, {
				action:  'qc_count_posts',
				nonce:   qcData.nonce,
				queries: queries
			}, function( response ) {
				previewXhr = null;
				if ( response.success ) {
					var text = qcData.i18n.previewCount.replace( '%d', response.data.count );
					$previewCount.text( text ).removeClass( 'qc-counting' );
				} else {
					$previewCount.text( '' ).removeClass( 'qc-counting' );
				}
			} ).fail( function() {
				previewXhr = null;
				$previewCount.text( '' ).removeClass( 'qc-counting' );
			} );
		}

		// =====================================================================
		//  EVENT HANDLERS
		// =====================================================================

		// -----------------------------------------------------------------
		// Save Posts button
		// -----------------------------------------------------------------

		$saveBtn.on( 'click', function( e ) {
			e.preventDefault();
			saveOrder();
		} );

		// -----------------------------------------------------------------
		// Load Results button
		// -----------------------------------------------------------------

		$loadButton.on( 'click', function( e ) {
			e.preventDefault();

			// Cancel any pending preview.
			if ( previewTimer ) {
				clearTimeout( previewTimer );
				previewTimer = null;
			}
			if ( previewXhr ) {
				previewXhr.abort();
				previewXhr = null;
			}
			$previewCount.text( '' ).removeClass( 'qc-counting' );

			$spinner.show();
			$loadButton.prop( 'disabled', true );
			$refreshButton.prop( 'disabled', true );

			var queries = gatherQueryData();

			$.post( qcData.ajaxurl, {
				action:  'qc_fetch_posts',
				nonce:   qcData.nonce,
				post_id: qcData.postId,
				queries: queries
			}, function( response ) {
				$spinner.hide();
				$loadButton.prop( 'disabled', false );
				$refreshButton.prop( 'disabled', false );

				if ( response.success ) {
					mergeResultsIntoGrid( response.data.posts, response.data.card_map );
					// Enable Refresh button now that we have saved query params.
					$refreshButton.prop( 'disabled', false );
				} else {
					showNotice( response.data || qcData.i18n.errorLoading, 'error' );
				}
			} ).fail( function() {
				$spinner.hide();
				$loadButton.prop( 'disabled', false );
				$refreshButton.prop( 'disabled', false );
				showNotice( qcData.i18n.failedLoad, 'error' );
			} );
		} );

		// -----------------------------------------------------------------
		// Refresh from Query button
		// -----------------------------------------------------------------

		$refreshButton.on( 'click', function( e ) {
			e.preventDefault();

			if ( $( this ).prop( 'disabled' ) ) {
				return;
			}

			$spinner.show();
			$loadButton.prop( 'disabled', true );
			$refreshButton.prop( 'disabled', true );

			// Gather current card data (which may have been pre-populated from saved params).
			var queries = gatherQueryData();

			$.post( qcData.ajaxurl, {
				action:  'qc_fetch_posts',
				nonce:   qcData.nonce,
				post_id: qcData.postId,
				queries: queries
			}, function( response ) {
				$spinner.hide();
				$loadButton.prop( 'disabled', false );
				$refreshButton.prop( 'disabled', false );

				if ( response.success ) {
					mergeResultsIntoGrid( response.data.posts, response.data.card_map );
				} else {
					showNotice( response.data || qcData.i18n.errorLoading, 'error' );
				}
			} ).fail( function() {
				$spinner.hide();
				$loadButton.prop( 'disabled', false );
				$refreshButton.prop( 'disabled', false );
				showNotice( qcData.i18n.failedLoad, 'error' );
			} );
		} );

		// -----------------------------------------------------------------
		// Remove post (delegated)
		// -----------------------------------------------------------------

		$grid.on( 'click', '.qc-remove-post', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var removedId = parseInt( $( this ).closest( '.qc-post-item' ).data( 'post-id' ), 10 );

			$( this ).closest( '.qc-post-item' ).fadeOut( 200, function() {
				$( this ).remove();
				updateHiddenInput();
				markDirty();

				// Update card contributions — remove this ID from tracking.
				$.each( cardContributions, function( idx, ids ) {
					var pos = ids.indexOf( removedId );
					if ( pos !== -1 ) {
						ids.splice( pos, 1 );
						if ( ids.length === 0 ) {
							delete cardContributions[ idx ];
						}
					}
				} );
				updateCardContributionLabels();

				if ( $grid.find( '.qc-post-item' ).length === 0 ) {
					$emptyState.show();
				}
			} );
		} );

		// -----------------------------------------------------------------
		// Keyboard support for post cards
		// -----------------------------------------------------------------

		$grid.on( 'keydown', '.qc-post-item', function( e ) {
			if ( e.key === 'Delete' || e.key === 'Backspace' ) {
				e.preventDefault();
				$( this ).find( '.qc-remove-post' ).trigger( 'click' );
			}
		} );

		// =====================================================================
		//  ADD POSTS MODAL
		// =====================================================================

		/** Open the modal. */
		$addBtn.on( 'click', function( e ) {
			e.preventDefault();
			$modal.fadeIn( 150 );
			$searchInput.val( '' ).focus();
			$searchResults.html( '<p class="qc-search-empty">Type a title and click Search to find posts.</p>' );
		} );

		/** Close the modal. */
		function closeModal() {
			$modal.fadeOut( 150 );
		}

		$modalClose.on( 'click', closeModal );
		$modalBackdrop.on( 'click', closeModal );

		$( document ).on( 'keydown', function( e ) {
			if ( e.key === 'Escape' && $modal.is( ':visible' ) ) {
				closeModal();
			}
		} );

		/** Perform the search. */
		function doSearch() {
			var term = $searchInput.val().trim();
			if ( ! term ) {
				return;
			}

			$searchSpinner.show();
			$searchBtn.prop( 'disabled', true );

			$.post( qcData.ajaxurl, {
				action:    'qc_search_posts',
				nonce:     qcData.nonce,
				search:    term,
				post_type: 'any',
				exclude:   getCuratedIds()
			}, function( response ) {
				$searchSpinner.hide();
				$searchBtn.prop( 'disabled', false );

				if ( response.success && response.data.length > 0 ) {
					renderSearchResults( response.data );
				} else {
					$searchResults.html( '<p class="qc-search-empty">' + escHtml( qcData.i18n.noSearchResults ) + '</p>' );
				}
			} ).fail( function() {
				$searchSpinner.hide();
				$searchBtn.prop( 'disabled', false );
				$searchResults.html( '<p class="qc-search-empty">' + escHtml( qcData.i18n.failedLoad ) + '</p>' );
			} );
		}

		$searchBtn.on( 'click', function( e ) {
			e.preventDefault();
			doSearch();
		} );

		$searchInput.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				doSearch();
			}
		} );

		/**
		 * Render search results inside the modal.
		 *
		 * @param {Array} posts Array of post data from AJAX.
		 */
		function renderSearchResults( posts ) {
			$searchResults.empty();

			$.each( posts, function( index, post ) {
				var thumb;
				if ( post.thumbnail ) {
					thumb = '<img src="' + escAttr( post.thumbnail ) + '" alt="" />';
				} else {
					thumb = '<span class="qc-search-no-thumb">&mdash;</span>';
				}

				var $row = $(
					'<div class="qc-search-result-row" data-post-id="' + escAttr( post.id ) + '">' +
						'<div class="qc-search-thumb">' + thumb + '</div>' +
						'<div class="qc-search-info">' +
							'<strong>' + escHtml( post.title ) + '</strong>' +
							'<span class="qc-search-type">' + escHtml( post.type ) + '</span>' +
						'</div>' +
						'<button type="button" class="button qc-search-add-btn">' + escHtml( qcData.i18n.add ) + '</button>' +
					'</div>'
				);

				$searchResults.append( $row );
			} );
		}

		/**
		 * Handle clicking "Add" on a search result row.
		 */
		$searchResults.on( 'click', '.qc-search-add-btn', function( e ) {
			e.preventDefault();

			var $row   = $( this ).closest( '.qc-search-result-row' );
			var postId = parseInt( $row.data( 'post-id' ), 10 );
			var $btn   = $( this );

			// Prevent duplicate adds.
			var currentIds = getCuratedIds();
			if ( currentIds.indexOf( postId ) !== -1 ) {
				$btn.text( qcData.i18n.added ).prop( 'disabled', true );
				return;
			}

			// Build post data from the search row.
			var post = {
				id:        postId,
				title:     $row.find( '.qc-search-info strong' ).text(),
				type:      $row.find( '.qc-search-type' ).text(),
				thumbnail: $row.find( 'img' ).attr( 'src' ) || ''
			};

			// Append card to the grid as "new".
			var $card = buildPostCard( post, true );
			$card.hide().appendTo( $grid ).fadeIn( 200 );

			$emptyState.hide();
			updateHiddenInput();
			markDirty();

			// Re-init sortable so the new card is draggable.
			initSortable();

			// Visual feedback on the button.
			$btn.text( qcData.i18n.added ).prop( 'disabled', true ).addClass( 'qc-added' );
		} );

		// =====================================================================
		//  CODE SNIPPETS
		// =====================================================================

		$( document ).on( 'click', '.qc-copy-btn', function( e ) {
			e.preventDefault();
			var $btn    = $( this );
			var snippet = $btn.data( 'snippet' );

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( snippet ).then( function() {
					showCopyFeedback( $btn );
				} );
			} else {
				var $temp = $( '<textarea>' ).val( snippet ).appendTo( 'body' ).select();
				document.execCommand( 'copy' );
				$temp.remove();
				showCopyFeedback( $btn );
			}
		} );

		/**
		 * Briefly change a copy button's text to "Copied!" then revert.
		 *
		 * @param {jQuery} $btn The button element.
		 */
		function showCopyFeedback( $btn ) {
			var original = $btn.text();
			$btn.text( 'Copied!' ).addClass( 'qc-copied' );
			setTimeout( function() {
				$btn.text( original ).removeClass( 'qc-copied' );
			}, 1500 );
		}

		// =====================================================================
		//  INITIALIZATION
		// =====================================================================

		// Render saved query cards or one empty card.
		if ( qcData.savedQueries && qcData.savedQueries.queries && qcData.savedQueries.queries.length > 0 ) {
			$.each( qcData.savedQueries.queries, function( i, queryData ) {
				renderQueryCard( queryData );
			} );
			$refreshButton.prop( 'disabled', false );
		} else {
			renderQueryCard();
		}

		// Init sortable if there are items on page load.
		if ( $grid.find( '.qc-post-item' ).length > 0 ) {
			initSortable();
		}

	} );

}( jQuery ) );
