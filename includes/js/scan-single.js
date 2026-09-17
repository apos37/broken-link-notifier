jQuery( $ => {
    // console.log( 'Scan Single JS Loaded...' );

    // Nonce
    var nonce = blnotifier_scan_single.nonce;

    // Scan an individual link
    const scanLink = async ( link, row ) => {
        console.log( `${blnotifier_scan_single.text.scanning_link} (${link})...` );

        // Say it started
        var progress = row.find( '.type' );
        progress.html( `<em>${blnotifier_scan_single.text.scanning}</em>` );

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
                    statusText = blnotifier_scan_single.text.please_try_again;
                    statusCode = 'ERR_FAILED';
                }

            // If no link, skip it
            } else {
                statusType = 'good';
                statusText = blnotifier_scan_single.text.skipping_missing_links;
                statusCode = '200';
            }
            
            // Update table
            linkRow.addClass( statusType );
            linkRow.removeClass( 'pending' );

            if ( statusType == 'broken' ) {
                linkRow.attr( 'title', blnotifier_scan_single.text.title_broken );
            } else if ( statusType == 'warning' ) {
                linkRow.attr( 'title', blnotifier_scan_single.text.title_warning );
            } else if ( statusCode == 405 ) {
                linkRow.attr( 'title', blnotifier_scan_single.text.title_405 );
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
        return console.log( blnotifier_scan_single.text.scanning_complete );
    }

    // Do it
    scanLinks();
} )