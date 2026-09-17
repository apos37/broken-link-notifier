jQuery( $ => {
    // console.log( 'Omits JS Loaded...' );

    // Nonce
    const nonce = blnotifier_omit.nonce;

    // Scan type
    const scanType = blnotifier_omit.scan_type;
    const omitSelector = scanType == 'scan-results' ? '.omit-link a' : '.omit-link';

    // Listen for omitting links
    $( document ).on( 'click', omitSelector, function( e ) {
        e.preventDefault();
        var row;
        var link;
        if ( scanType == 'scan-results' ) {
            const linkID = $( this ).closest( 'tr' ).data( 'link-id' );
            $( `#link-${linkID}` ).addClass( 'omitted' );
            $( this ).replaceWith( blnotifier_omit.text.omitted );
            link = $( this ).data( 'link' );
        } else if ( scanType == 'scan-single' ) {
            row = $( this ).parent().parent();
            row.addClass( 'pending omitted' );
            row.find( '.type' ).text( blnotifier_omit.text.omitted );
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
    if ( scanType == 'scan-multi' || scanType == 'scan-results' ) {
        $( document ).on( 'click', '.omit-page', function( e ) {
            e.preventDefault();
            const link = $( this ).data( 'link' );
            if ( scanType == 'scan-results' ) {
                $( this ).parent().html( blnotifier_omit.text.omitted );
            } else {
                $( this ).parent().hide();
            }
            if ( scanType == 'scan-multi' ) {
                const postID = $( this ).data( 'post-id' );
                $( `#bln-${postID}` ).html( '<em>' + blnotifier_omit.text.omitted + '</em>' );
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
                    console.log( link + ' has been omitted.' );

                    // The results table already deleted this from the DB server-side
                    // (BLNOTIFIER_OMITS::add() calls BLNOTIFIER_RESULTS::remove() for 'scan-results')
                    // so refresh the table/cards to reflect it instead of leaving a stale row.
                    if ( page == 'scan-results' && typeof window.blnRefreshResultsTable === 'function' ) {
                        window.blnRefreshResultsTable();
                    }

                    return true;
                    
                // Failure
                } else if ( response.type == 'error' ) {
                    console.log( 'Omitting failed. Please contact plugin developer.' );
                }
            }
        } )
    } // End checkLink()
} )