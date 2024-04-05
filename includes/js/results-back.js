jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    // Add target _blank to title links
    $( 'a.row-title' ).attr( 'target', '_blank' );

    // Clear filters
    $( '#link-type-filter' ).on( 'change', function( e ) {
        $( '#code-filter' ).val( '' );
    } );
    $( '#code-filter' ).on( 'change', function( e ) {
        $( '#link-type-filter' ).val( '' );
    } );
} )