jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    $( '#link-type-filter' ).on( 'change', function( e ) {
        $( '#code-filter' ).val( '' );
    } );

    $( '#code-filter' ).on( 'change', function( e ) {
        $( '#link-type-filter' ).val( '' );
    } );
} )