/**
 * Asks "Publish anyway?" before a post goes live, when a Confirm rule is unmet.
 *
 * Hooks `editor.preSavePost`, the filter core awaits before it sends a save.
 * Returning the edits lets the save go ahead; throwing stops it, and core shows
 * its own "Publishing failed." notice followed by our message. So the question
 * sits inside core's save flow rather than around it, and nothing is saved
 * until the author answers.
 *
 * The rules are PHP, so the answer comes from the check route, sent the unsaved
 * title, content, excerpt, featured image and terms. The real save still runs
 * every rule on the server, and a Confirm result there is shown as a warning.
 *
 * With pre-publish checks on, the first Publish click only opens that sidebar
 * and saves nothing, so the question comes on the sidebar's Publish button.
 * Catching the first click would mean hooking core's button by class name.
 *
 * Fails open. If the check request fails, the post publishes and the server's
 * after-save warning still appears, so a network hiccup never stops a publish.
 * On WordPress without `applyFiltersAsync` the filter never runs, with the same
 * result.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.hooks.applyFiltersAsync || ! wp.apiFetch || ! wp.element || ! wp.element.createRoot ) {
		return;
	}

	var __ = wp.i18n.__;
	var el = wp.element.createElement;
	var LIVE = [ 'publish', 'future' ];
	var TAXONOMY_FIELDS = [ 'categories', 'tags' ];

	/**
	 * Shows the question and resolves true for "Publish anyway", false for
	 * Cancel. ConfirmDialog is core's own, still marked experimental, so the
	 * stable Modal stands in if it is ever missing.
	 */
	function ask( message ) {
		return new Promise( function ( resolve ) {
			var host = document.createElement( 'div' );
			var root;

			document.body.appendChild( host );
			root = wp.element.createRoot( host );

			function answer( value ) {
				root.unmount();
				host.remove();
				resolve( value );
			}

			var components = wp.components;
			var confirmLabel = __( 'Publish anyway', 'mai-publish-requirements' );
			var cancelLabel = __( 'Cancel', 'mai-publish-requirements' );

			if ( components.__experimentalConfirmDialog ) {
				root.render( el( components.__experimentalConfirmDialog, {
					isOpen: true,
					confirmButtonText: confirmLabel,
					cancelButtonText: cancelLabel,
					onConfirm: function () { answer( true ); },
					onCancel: function () { answer( false ); },
				}, message ) );
				return;
			}

			root.render( el( components.Modal, {
				title: __( 'Publish anyway?', 'mai-publish-requirements' ),
				onRequestClose: function () { answer( false ); },
			},
				el( 'p', null, message ),
				el( components.Flex, { justify: 'flex-end' },
					el( components.Button, { variant: 'tertiary', onClick: function () { answer( false ); } }, cancelLabel ),
					el( components.Button, { variant: 'primary', onClick: function () { answer( true ); } }, confirmLabel )
				)
			) );
		} );
	}

	wp.hooks.addFilter( 'editor.preSavePost', 'mai-publish-requirements/confirm', function ( edits, options ) {
		if ( options && options.isAutosave ) {
			return edits;
		}

		var editor = wp.data.select( 'core/editor' );
		var current = editor.getCurrentPost();

		// Only the moment a post goes live. Save draft sends no status, and an
		// update to a live post is covered by the after-save warning.
		if ( LIVE.indexOf( edits.status ) === -1 || LIVE.indexOf( current.status ) !== -1 ) {
			return edits;
		}

		var data = {
			id: current.id,
			status: edits.status,
			title: editor.getEditedPostAttribute( 'title' ),
			content: editor.getEditedPostContent(),
			excerpt: editor.getEditedPostAttribute( 'excerpt' ),
			featured_media: editor.getEditedPostAttribute( 'featured_media' ),
		};

		TAXONOMY_FIELDS.forEach( function ( field ) {
			var value = editor.getEditedPostAttribute( field );

			if ( Array.isArray( value ) ) {
				data[ field ] = value;
			}
		} );

		return wp.apiFetch( { path: '/mai-publish-requirements/v1/check', method: 'POST', data: data } )
			.then( function ( response ) {
				return response && response.message ? response.message : '';
			}, function () {
				return '';
			} )
			.then( function ( message ) {
				if ( ! message ) {
					return edits;
				}

				return ask( message ).then( function ( confirmed ) {
					if ( confirmed ) {
						return edits;
					}

					// Put the status back. Left at "publish", the editor stays half
					// way to publishing: Save draft disappears and Cmd+S asks again.
					wp.data.dispatch( 'core/editor' ).editPost( { status: current.status } );

					throw new Error( __( 'You chose not to publish yet.', 'mai-publish-requirements' ) );
				} );
			} );
	} );
}( window.wp ) );
