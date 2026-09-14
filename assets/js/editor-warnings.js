/**
 * Shows publish warnings the server raised on the save that just happened.
 *
 * A block cannot travel this way: `rest_pre_insert` fails the request and the
 * editor shows that error itself. A warning has no such channel, because the
 * save succeeded, so the gate attaches it to the post's REST response and this
 * reads it back off the saved record.
 *
 * Polling the store rather than subscribing to a save event: there is no public
 * "save finished" hook, and `isSavingPost` going false is the documented way to
 * spot the transition. The comparison is on the warning text, so re-saving a
 * post with the same problem does not stack a second identical notice.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.data.subscribe ) {
		return;
	}

	var FIELD = 'mai_publish_warnings';
	var NOTICE_ID = 'mai-publish-requirements-warnings';

	var wasSaving = false;
	var lastShown = '';

	wp.data.subscribe( function () {
		var editor = wp.data.select( 'core/editor' );

		if ( ! editor ) {
			return;
		}

		var isSaving = editor.isSavingPost() || editor.isAutosavingPost();

		// Only look at the moment a save finishes. Reading on every tick would
		// re-show the notice the reader just dismissed.
		if ( isSaving ) {
			wasSaving = true;
			return;
		}

		if ( ! wasSaving ) {
			return;
		}

		wasSaving = false;

		if ( editor.didPostSaveRequestFail && editor.didPostSaveRequestFail() ) {
			return;
		}

		var warnings = editor.getCurrentPost()[ FIELD ];

		if ( ! warnings || ! warnings.length ) {
			return;
		}

		var text = warnings.join( '; ' );

		if ( text === lastShown ) {
			return;
		}

		lastShown = text;

		wp.data.dispatch( 'core/notices' ).createNotice(
			'warning',
			text,
			{
				id: NOTICE_ID,
				isDismissible: true,
			}
		);
	} );
}( window.wp ) );
