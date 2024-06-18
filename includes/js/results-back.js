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

    // Add a rescan button
    // $( '.wrap > a.page-title-action' ).after( `<a id="bln-rescan" href="#" class="page-title-action" style="margin-left: 10px;"><span class="text">Re-Scan Links</span><span class="done"></span></a>` );

    // Nonce
    var nonce = blnotifier_back_end.nonce;
   
    // Scan an individual link
    const scanLink = async ( link, postID, code ) => {
        console.log( `Scanning link (${link})...` );

        // Say it started
        var span = $( `#bln-verify-${postID}` );
        span.addClass( 'scanning' ).html( `<em>Verifying</em>` );

        // Run the scan
        return await $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: { 
                action: 'blnotifier_rescan', 
                nonce: nonce,
                link: link,
                postID: postID,
                code: code
            }
        } )
    }

    // Rescan all links
    const reScanLinks = async () => {
        
        // Get the post link spans
        const linkSpans = document.querySelectorAll( '.bln-verify' );

        // First count all the link for the button
        for ( const linkSpan of linkSpans ) {
            const link = linkSpan.dataset.link;
            const postID = linkSpan.dataset.postId;
            const code = linkSpan.dataset.code;

            // Scan it
            const data = await scanLink( link, postID, code );
            console.log( data );

            // Status
            var statusType;
            var statusText;
            var statusCode;
            if ( data && data.type == 'success' ) {
                statusType = data.status.type;
                statusText = data.status.text;
                statusCode = data.status.code;
            } else {
                statusType = 'error';
                statusText = data.msg;
                statusCode = 'ERR_FAILED';
            }

            // Text and actions
            var text;
            if ( statusType == 'good' ) {
                text = '<em>Link is good, removing from list...</em>';
                $( `#post-${postID}` ).addClass( 'omitted' );
                $( `#post-${postID} .bln-type` ).addClass( statusType ).text( statusType );
                $( `#post-${postID} .bln_type code` ).html( 'Code: ' + statusCode );
                $( `#post-${postID} .bln_type .message` ).text( statusText );
                $( `#post-${postID} .title .row-actions` ).remove();
                $( `#post-${postID} .bln_source .row-actions` ).remove();
            } else if ( code != statusCode ) {
                if ( statusCode == 'ERR_FAILED' ) {
                    text = `Failed to remove link. ${statusText}`;
                } else {
                    text = `Link is still bad, but showing a different code. Old code was ${code}; new code is ${statusCode}.`;
                }
                $( `#post-${postID} .bln-type` ).attr( 'class', `bln-type ${statusType}`).text( statusType );
                var codeLink = 'Code: ' + statusCode;
                if ( statusCode != 0 && statusCode != 666 ) {
                    codeLink = `<a href="https://http.dev/${statusCode}" target="_blank">Code: ${statusCode}</a>`;
                }
                $( `#post-${postID} .bln_type code` ).html( codeLink );
                $( `#post-${postID} .bln_type message` ).text( statusText );
            } else {
                text = `Still showing ${statusType}.`;
            }

            // Update the page
            $( `#bln-verify-${postID}` ).removeClass( 'scanning' ).addClass( statusType ).html( text );
        }

        return console.log( 'Done with all links' );
    }

    // Do it
    reScanLinks();
} )