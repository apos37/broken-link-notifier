jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    // Which elements to check
    const elements = blnotifier_front_end.elements;

    /**
     * Highlight broken link on page
     */

    // Get the url query strings
    const queryString = window.location.search;
    const urlParams = new URLSearchParams( queryString );

    // Only continue if a broken link is being searched
    if ( urlParams.has( 'blink' ) ) {
        console.log( 'Looking for highlights; checking for broken links paused.' );
        const blink = urlParams.get( 'blink' );
        $.each( elements, function( tag, attr ) {
            $( tag ).not( '#wpadminbar ' + tag ).each( function( index ) {
                const link = $( this ).attr( attr );
                if ( link !== undefined && link.includes( blink ) ) {
                    $( this ).addClass( 'glowText' );
                    if ( $( this ).is( ':hidden' ) ) {
                        var msg = 'It looks like one or more of the links are hidden. To find them, try searching for it in your browser\'s Developer console.';
                        console.log( msg );
                        alert( msg );
                    } else {
                        console.log( 'The element should glow yellow if it is visible on the page. If you do not see it on the page, then it is hidden somewhere. Check any JavaScript elements, too. You can try searching for it in your browser\'s Developer console.' );
                    }
                }
            } )
        } );
        

    /**
     * Or find broken links on page after load (we don't want to notify if we're looking into it already)
     */
    } else {

        // Notice
        if ( blnotifier_front_end.show_in_console ) {
            console.log( '%c Fetching links using the Broken Link Notifier Plugin... ', 'background: #2570AC; color: white' );
        }

        // Fetch the links
        var headerLinks = [];
        var contentLinks = [];
        var footerLinks = [];

        $.each( elements, function( tag, attr ) {
            $( tag ).each( function( index ) {
                const link = $( this ).attr( attr );
                const inAdminBar = $( this ).parents( '#wpadminbar' ).length;
                const inHeader = $( this ).parents( 'header' ).length;
                const inFooter = $( this ).parents( 'footer' ).length;
                if ( link !== undefined && !inAdminBar ) {
                    if ( blnotifier_front_end.scan_header && inHeader ) {
                        headerLinks.push( link );
                    } else if ( blnotifier_front_end.scan_footer && inFooter ) {
                        footerLinks.push( link );
                    } else if ( !inHeader && !inFooter ) {
                        contentLinks.push( link );
                    }
                }
            } )
        } );

        // Console log
        if ( blnotifier_front_end.show_in_console ) {
            if ( blnotifier_front_end.scan_header ) {
                console.log( '%c Header links found: ', 'background: #222; color: #bada55' );
                console.log( headerLinks );
            }
            console.log( '%c Content links found: ', 'background: #222; color: #bada55' );
            console.log( contentLinks );
            if ( blnotifier_front_end.scan_header ) {
                console.log( '%c Footer links found: ', 'background: #222; color: #bada55' );
                console.log( footerLinks );
            }
            console.log( '%c Scanning for broken links... please wait. This may take a few minutes if there are a lot of links.', 'background: #2570AC; color: white' );
        }

        // Nonce
        var nonce = blnotifier_front_end.nonce;

        // Start the ajax
        $.ajax( {
            type : 'post',
            dataType : 'json',
            url : blnotifier_front_end.ajaxurl,
            data : { 
                action: 'blnotifier_blinks', 
                nonce: nonce,
                scan_header: blnotifier_front_end.scan_header,
                scan_footer: blnotifier_front_end.scan_footer,
                source_url: window.location.href,
                header_links: headerLinks,
                content_links: contentLinks,
                footer_links: footerLinks,
            },
            success: function( response ) {
                // Success
                if ( response.type == 'success' ) {
                    if ( blnotifier_front_end.show_in_console ) {

                        // Console
                        console.log( '%c Broken Link Scan Results: ', 'background: #2570AC; color: white' );
                        if ( response.notify ) {
                            console.error( response.notify );
                        } else {
                            console.info( 'No broken links found. :)' );
                        }
                        console.log( `%c ${response.timing} `, 'background: #2570AC; color: white' );
                    }

                    // Highlight all on page
                    if ( urlParams.has( 'blinks' ) && urlParams.get( 'blinks' ) == 'true' ) {
                        $.each( response.notify, function( s_index, section ) {
                            $.each( section, function( a_index, a ) {
                                $.each( elements, function( tag, attr ) {
                                    $( tag ).each( function( el_index ) {
                                        var href = $( this ).attr( attr );
                                        if ( a.link == href ) {
                                            $( this ).addClass( 'glowText' );
                                        }
                                    } );
                                } );
                            } );
                        } );
                    }

                // Failure
                } else if ( response.type == 'error' ) {
                    console.log( 'Scan failed. Please contact plugin developer.' );
                }
            }
        } )
    }
} )