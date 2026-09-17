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
        console.log( blnotifier_front_end.text.checking_paused );
        const blink = urlParams.get( 'blink' );
        $.each( elements, function( tag, attr ) {
            $( tag ).not( '#wpadminbar ' + tag ).each( function( index ) {
                const link = $( this ).attr( attr );
                if ( link !== undefined && link.includes( blink ) ) {
                    $( this ).addClass( 'glowText' );
                    if ( $( this ).is( ':hidden' ) ) {
                        var msg = blnotifier_front_end.text.links_hidden;
                        console.log( msg );
                        alert( msg );
                    } else {
                        console.log( blnotifier_front_end.text.glow_yellow );
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
            console.log( '%c🔗 ' + blnotifier_front_end.text.plugin_name + ' %c ' + blnotifier_front_end.text.fetching_links,
                'background: #1D2327; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px 0 0 4px;',
                'background: #f0f0f1; color: #1D2327; padding: 4px 10px; border-radius: 0 4px 4px 0;'
            );
        }

        // Show a progress message every 10 seconds while scanning. 
        var scanTicker = setInterval( function() { 
            if ( blnotifier_front_end.show_in_console ) { 
                console.log( '%c🔗 ' + blnotifier_front_end.text.plugin_name + ' %c ' + blnotifier_front_end.text.still_scanning, 'background: #1D2327; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px 0 0 4px;', 'background: #f0f0f1; color: #1D2327; padding: 4px 10px; border-radius: 0 4px 4px 0;' ); 
            } }, 10000 
        );

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
                        const brokenCount = response.results && response.results.broken ? Object.values( response.results.broken ).reduce( ( sum, arr ) => sum + arr.length, 0 ) : 0;
                        const warningCount = response.results && response.results.warning ? Object.values( response.results.warning ).reduce( ( sum, arr ) => sum + arr.length, 0 ) : 0;
                        const goodCount = response.results && response.results.good ? Object.values( response.results.good ).reduce( ( sum, arr ) => sum + arr.length, 0 ) : 0;

                        let statusLabel = '%c🔗 ' + blnotifier_front_end.text.plugin_name + ' — ' + blnotifier_front_end.text.scan_complete;
                        const statusStyles = [
                            'background: #1D2327; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px 0 0 4px;'
                        ];

                        if ( brokenCount > 0 ) {
                            statusLabel += ` %c⚠ ${brokenCount} broken`;
                            statusStyles.push( 'background: #dc3545; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px;' );
                        }

                        if ( warningCount > 0 ) {
                            statusLabel += ` %c⚠ ${warningCount} warning${warningCount == 1 ? '' : 's'}`;
                            statusStyles.push( 'background: #dba617; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px;' );
                        }

                        if ( goodCount > 0 ) {
                            statusLabel += ` %c✓ ${goodCount} good`;
                            statusStyles.push( 'background: #008a20; color: #ffffff; font-weight: bold; padding: 4px 10px; border-radius: 4px;' );
                        }

                        console.log( statusLabel, ...statusStyles );

                        console.group( '%c' + blnotifier_front_end.text.details, 'color: #667085; font-style: italic;' );
                        console.log( {
                            scanned: response.scanned,
                            results: response.results,
                            warnings_enabled: response.warnings_enabled ? blnotifier_front_end.text.warnings_enabled : blnotifier_front_end.text.warnings_disabled,
                            status_codes: response.status_codes || {},
                            message: response.msg || null,
                            timing: response.timing
                        } );
                        console.groupEnd();
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
                    var errorMsg = response.msg ? response.msg : blnotifier_front_end.text.unknown_error;
                    console.error( blnotifier_front_end.text.scan_failed + ': ' + errorMsg );
                }
            },
            complete: function() { 
                // Stop the progress ticker when the scan finishes.
                clearInterval( scanTicker );
            }
        } )
    }
} )