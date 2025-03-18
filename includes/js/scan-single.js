jQuery( $ => {
    // console.log( 'Scan Single JS Loaded...' );

    // Nonce
    var nonce = blnotifier_scan_single.nonce;

    // Scan an individual link
    const scanLink = async ( link, row ) => {
        console.log( `Scanning link (${link})...` );

        // Say it started
        var progress = row.find( '.type' );
        progress.html( `<em>Scanning</em>` );

        // Run the scan
        return await $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_scan_single.ajaxurl,
            data: { 
                action: 'blnotifier_scan', 
                nonce: nonce,
                link: link,
                postID: blnotifier_scan_single.post_id,
                method: 'single'
            }
        } )
    }

    // Scan all link on a post
    const scanLinks = async () => {
        console.log( `Scanning links started...` );

        // Get the link rows
        const linkRows = document.querySelectorAll( '.link-row' );

        // Iter the rows
        for ( var linkRow of linkRows ) {
            linkRow = $( linkRow );

            // Timing
            const start = performance.now();

            // Vars
            var statusType;
            var statusText;
            var statusCode;

            // Get the link
            const link = linkRow.data( 'link' );
            if ( link ) {

                // Scan it
                const data = await scanLink( link, linkRow );
                console.log( data );

                // Status
                if ( data.type == 'success' ) {
                    statusType = data.status.type;
                    statusText = data.status.text;
                    statusCode = data.status.code;
                } else {
                    statusType = 'error';
                    statusText = 'Please try again.';
                    statusCode = 'ERR_FAILED';
                }

            // If no link, skip it
            } else {
                statusType = 'good';
                statusText = 'Skipping missing links';
                statusCode = '200';
            }
            
            // Update table
            linkRow.addClass( statusType );
            linkRow.removeClass( 'pending' );

            if ( statusType == 'broken' ) {
                linkRow.attr( 'title', "If the link works fine and it's still being flagged as broken, then there is an issue with the page's response headers and there's nothing we can do about it. You may use the Omit option on the right to omit it from future scans." );
            } else if ( statusType == 'warning' ) {
                linkRow.attr( 'title', "Warnings mean the link was found, but they may be unsecure or slow to respond. If you are getting too many warnings due to timeouts, try increasing your timeout in Settings. This will just result in longer wait times, but with more accuracy." );
            } else if ( statusCode == 405 ) {
                linkRow.attr( 'title', "405 Method Not Allowed indicates that the target resource doesn't support checking for header responses using our method, but is still telling us that the page exists which is what we actually want to know. So it's fine; nothing to worry about." );
            }
            
            linkRow.find( '.type' ).removeClass( 'dotdotdot' );
            linkRow.find( '.type' ).text( statusType );
            if ( statusCode != 0 ) {
                statusCode = `<a href="https://http.dev/${statusCode}" target="_blank">${statusCode}</a>`;
            }
            linkRow.find( '.code' ).html( statusCode );
            linkRow.find( '.text' ).text( statusText );
            const end = performance.now();
            const seconds = (end - start) / 1000;
            linkRow.find( '.speed' ).text( seconds.toFixed(2) + ' sec' );
            linkRow.find( '.actions' ).show();
        }
        return console.log( `Scanning links complete.` );
    }

    // Do it
    scanLinks();
} )