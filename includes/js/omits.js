jQuery( $ => {
    // console.log( 'Omits JS Loaded...' );

    // Nonce
    const nonce = blnotifier_omit.nonce;

    // Scan type
    const scanType = blnotifier_omit.scan_type;

    // Listen for omitting links
    $( '.omit-link' ).on( 'click', function( e ) {
        e.preventDefault();
        var row;
        var link;
        if ( scanType == 'scan-results' ) {
            const postID = $( this ).data( 'post-id' );
            $( `#post-${postID}` ).addClass( 'omitted' );
            $( this ).replaceWith( 'Omitted' );
            link = $( this ).data( 'link' );
        } else if ( scanType == 'scan-single' ) {
            row = $( this ).parent().parent();
            row.addClass( 'pending omitted' );
            row.find( '.type' ).text( 'Omitted' );
            row.find( '.code' ).text( '' );
            row.find( '.text' ).text( '' );
            row.find( '.speed' ).text( '' );
            row.find( '.actions' ).hide();
            link = row.data( 'link' );
        } else {
            row = $( this ).parent().parent();
            row.addClass( 'omitted' );
            row.find( '.actions' ).hide();
            link = row.data( 'link' );
        }
        omit( nonce, link, 'links', scanType );
    } );

    // Listen for omitting pages
    if ( scanType == 'scan-full' || scanType == 'scan-results' ) {
        $( '.omit-page' ).on( 'click', function( e ) {
            e.preventDefault();
            const link = $( this ).data( 'link' );
            if ( scanType == 'scan-results' ) {
                $( this ).parent().html( 'Omitted' );
            } else {
                $( this ).parent().hide();
            }
            if ( scanType == 'scan-full' ) {
                const postID = $( this ).data( 'post-id' );
                $( `#bln-${postID}` ).html( '<em>Omitted</em>' );
            }
            omit( nonce, link, 'pages', scanType );
        } );
    }
    
    /**
     * Omit
     */
    function omit( nonce, link, type, page ) {
        $.ajax( {
            type : 'post',
            dataType : 'json',
            url : blnotifier_omit.ajaxurl,
            data : { 
                action: 'blnotifier_omit', 
                nonce: nonce,
                link: link,
                type: type,
                page: page
            },
            success: function( response ) {
                // Success
                if ( response.type == 'success' ) {
                    
                    // Update table
                    console.log( link + ' has been omitted.' );
                    return true;
                    
                // Failure
                } else if ( response.type == 'error' ) {
                    console.log( 'Omitting failed. Please contact plugin developer.' );
                }
            }
        } )
    } // End checkLink()
} )