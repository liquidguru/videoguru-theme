/* VideoGuru Companion: the "select all" box on the import review list. */
( function () {
	var all = document.getElementById( 'vgcompanion-all' );
	if ( ! all ) {
		return;
	}
	all.addEventListener( 'change', function () {
		document.querySelectorAll( '.vgcompanion-pick:not(:disabled)' ).forEach( function ( box ) {
			box.checked = all.checked;
		} );
	} );
}() );
