jQuery( $ => {
    // console.log( 'Scan Full JS Loaded...' );

    // Nonce
    var nonce = blnotifier_scan_multi.nonce;

    // Count posts
    var countPostsDone = 0;
    var countPostsWithLinks = 0;
   
    // Scan an individual link
    const scanLink = async ( link, postID, countDone, countLinks ) => {
        console.log( `Scanning link (${link})...` );

        // Say it started
        var progress = $( `#bln-results-${postID} .progress` );
        progress.html( `<em>Scanning <span class="done">${countDone}</span>/<span class="total">${countLinks}</span></em>` );

        // Run the scan
        return await $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_scan_multi.ajaxurl,
            data: { 
                action: 'blnotifier_scan', 
                nonce: nonce,
                link: link,
                postID: postID,
            }
        } )
    }

    // Scan all link on a post
    const scanLinks = async ( linkSection, postID, countLinks ) => {
        console.log( `Scanning links for ${postID} starting...` );

        // Timing
        const start = performance.now();

        // Count done
        var countDone = 0;

        // The counts container
        const counts = $( `#bln-counts-${postID}` );

        // Show the counts container
        if ( counts.is( ':hidden' ) ) {
            counts.show();
        }

        // Get the link rows
        const linkRows = linkSection.querySelectorAll( '.link' );

        // Iter the rows
        for ( var linkRow of linkRows ) {
            linkRow = $( linkRow );

            // Vars
            var statusType;
            var statusText;
            var statusCode;

            // Get the link
            const link = linkRow.data( 'link' );
            if ( link ) {

                // Scan it
                const data = await scanLink( link, postID, countDone, countLinks );
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
            if ( statusType != 'good' ) {

                // Add status to link row
                linkRow.find( '.status' ).html( `<span class="type">${statusType}</span> (${statusCode} - ${statusText})` );

                // Show the link row
                linkRow.show();

                // Increase the count
                counts.find( `.count-${statusType}-links strong`).html( function (i, val) { return +val + 1; } );

                // Add class
                counts.find( `.count-${statusType}-links`).addClass( 'found' );
            }

            // Increase percent on scan button
            countPostsDone++;
            const percent = ( countPostsDone / countPostsWithLinks ) * 100;
            $( '#bln-run-scan .done' ).html( ' ' + percent.toFixed(0) + '%' );
            if ( percent == 100 ) {
                $( '#bln-run-scan .text' ).html( 'Scan Complete' );
            }

            // Increase scanned count
            countDone++;
            $( `#bln-results-${postID} .progress .done` ).html( countDone );
            $( `#bln-results-${postID} .progress .total` ).html( countLinks );
        }

        // Show the results
        $( `#bln-results-${postID} .progress` ).html( `<em>Scan Complete</em>` ).removeClass( 'dotdotdot' );
        $( `#bln-links-${postID} .warning .actions, #bln-links-${postID} .broken .actions` ).show();

        // Stop timing
        const end = performance.now();
        const seconds = ( end - start ) / 1000;
        counts.find( '.timing' ).html( seconds.toFixed(2) );
        
        return console.log( `Scanning links for ${postID} complete.` );
    }

    // Scan all posts
    const scanPosts = async () => {
        
        // Get the post link sections
        const linkSections = document.querySelectorAll( '.bln-links' );

        // First count all the link for the button
        for ( const linkSection of linkSections ) {
            const linkCount = linkSection.dataset.totalCount;
            countPostsWithLinks = +countPostsWithLinks + +linkCount;
        }

        // Iter them
        for ( const linkSection of linkSections ) {

            // Get the post ID and total link count
            const postID = linkSection.dataset.postId;
            const linkCount = linkSection.dataset.totalCount;

            // Scan the links
            await scanLinks( linkSection, postID, linkCount );
        }

        return console.log( 'Done with all posts' );
    }

    // Do it
    scanPosts();
} )