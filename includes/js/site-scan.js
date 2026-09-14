jQuery( $ => {

    const nonce = blnotifier_site_scan.nonce;
    const linkBrowserNonce = blnotifier_site_scan.link_browser_nonce;
    const ajaxUrl = blnotifier_site_scan.ajaxurl;
    const scanDelay = blnotifier_site_scan.scan_delay_ms || 0;

    let discoverQueue = [];
    let discoverIndex = 0;
    let checkQueue = [];
    let checkIndex = 0;
    let checkStats = { checked: 0, broken: 0, warning: 0 };
    let redirectsOmitted = 0;

    window.onbeforeunload = null;

    const setScanning = ( isScanning ) => {
        if ( isScanning ) {
            $( '#bln-site-scan-stay-note' ).show();
            window.onbeforeunload = () => 'A scan is still running. Are you sure you want to leave?';
        } else {
            $( '#bln-site-scan-stay-note' ).hide();
            window.onbeforeunload = null;
        }
    }

    /**
     * STEP 1: DISCOVERY
     */

    const discoverNext = () => {
        if ( discoverIndex >= discoverQueue.length ) {
            $.post( ajaxUrl, {
                action: 'blnotifier_link_browser_finish',
                nonce: linkBrowserNonce
            }, function( response ) {
                setScanning( false );
                $( '#bln-discover-spinner' ).hide();
                $( '#bln-discover-progress' ).hide();

                const counts = ( response.success && response.data.counts ) ? response.data.counts : null;
                const total = counts ? counts.total : discoverIndex;

                let message = '<span class="dashicons dashicons-yes-alt"></span> <strong>Scan complete!</strong> Found ' + total + ' link' + ( total == 1 ? '' : 's' ) + '.';
                if ( redirectsOmitted > 0 ) {
                    message += ' Automatically omitted ' + redirectsOmitted + ' redirecting page' + ( redirectsOmitted == 1 ? '' : 's' ) + '.';
                }

                $( '#bln-discovery-status' ).addClass( 'bln-scan-complete' ).html( message );

                if ( counts ) {
                    $( '#bln-discover-summary-total' ).text( counts.total );
                    $( '#bln-discover-summary-internal' ).text( counts.internal );
                    $( '#bln-discover-summary-external' ).text( counts.external );
                    $( '#bln-discover-summary' ).show();
                }

                $( '#bln-discover-links' ).prop( 'disabled', false ).text( 'Rescan for New Links' );
                $( '#bln-check-links' ).prop( 'disabled', false );
            } );
            return;
        }

        const postId = discoverQueue[ discoverIndex ];

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_scan_post',
            nonce: linkBrowserNonce,
            postID: postId
        }, function( response ) {
            if ( response.success && response.data.redirect_detected ) {
                redirectsOmitted++;
            }
        } ).always( function() {
            discoverIndex++;
            $( '#bln-discover-done' ).text( discoverIndex );
            if ( scanDelay > 0 ) {
                setTimeout( discoverNext, scanDelay );
            } else {
                discoverNext();
            }
        } );
    }

    $( '#bln-discover-links' ).on( 'click', function() {
        const button = $( this );
        button.prop( 'disabled', true ).text( 'Discovering...' );
        setScanning( true );

        $( '#bln-discover-spinner' ).show();
        $( '#bln-discover-progress' ).show();
        $( '#bln-discover-done' ).text( '0' );

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_get_queue',
            nonce: linkBrowserNonce
        }, function( response ) {
            if ( !response.success ) {
                setScanning( false );
                button.prop( 'disabled', false ).text( 'Discover Links' );
                alert( 'Could not start discovery.' );
                return;
            }

            discoverQueue = response.data.post_ids;
            discoverIndex = 0;
            $( '#bln-discover-total' ).text( discoverQueue.length );

            // Also store header/footer menu links once per discovery run
            $.post( ajaxUrl, {
                action: 'blnotifier_site_scan_store_menus',
                nonce: nonce
            } ).always( function() {
                discoverNext();
            } );
        } );
    } );

    /**
     * STEP 2: CHECK FOR BROKEN LINKS
     */

    const checkNext = () => {
        if ( checkIndex >= checkQueue.length ) {
            $.post( ajaxUrl, {
                action: 'blnotifier_site_scan_finish',
                nonce: nonce,
                checked: checkStats.checked,
                broken: checkStats.broken,
                warning: checkStats.warning
            }, function() {
                setScanning( false );
                $( '#bln-check-spinner' ).hide();
                $( '#bln-check-progress-inline' ).hide();
                $( '#bln-check-progress-wrap' ).hide();
                $( '#bln-check-status' ).addClass( 'bln-scan-complete' ).html( '<span class="dashicons dashicons-yes-alt"></span> <strong>Scan complete!</strong> Checked ' + checkStats.checked + ' link' + ( checkStats.checked == 1 ? '' : 's' ) + '.' );
                $( '#bln-check-links' ).prop( 'disabled', false ).text( 'Check for Broken Links' );
                $( '#bln-summary-checked' ).text( checkStats.checked );
                $( '#bln-summary-broken' ).text( checkStats.broken );
                $( '#bln-summary-warning' ).text( checkStats.warning );
                $( '#bln-check-summary' ).show();
            } );
            return;
        }

        const linkId = checkQueue[ checkIndex ];

        $.post( ajaxUrl, {
            action: 'blnotifier_site_scan_check_link',
            nonce: nonce,
            linkID: linkId
        }, function( response ) {
            checkStats.checked++;
            if ( response.success && response.data.status ) {
                if ( response.data.status.type === 'broken' ) {
                    checkStats.broken++;
                } else if ( response.data.status.type === 'warning' ) {
                    checkStats.warning++;
                }
            }
        } ).always( function() {
            checkIndex++;
            $( '#bln-check-done' ).text( checkIndex );
            const percent = checkQueue.length ? Math.round( ( checkIndex / checkQueue.length ) * 100 ) : 0;
            $( '#bln-progress-bar-fill' ).css( 'width', percent + '%' );
            if ( scanDelay > 0 ) {
                setTimeout( checkNext, scanDelay );
            } else {
                checkNext();
            }
        } );
    }

    $( '#bln-check-links' ).on( 'click', function() {
        const button = $( this );
        button.prop( 'disabled', true ).text( 'Checking...' );
        setScanning( true );

        $( '#bln-check-spinner' ).show();
        $( '#bln-check-progress-inline' ).show();
        $( '#bln-check-progress-wrap' ).show();
        $( '#bln-check-summary' ).hide();
        $( '#bln-check-done' ).text( '0' );
        $( '#bln-progress-bar-fill' ).css( 'width', '0%' );
        checkStats = { checked: 0, broken: 0, warning: 0 };

        $.post( ajaxUrl, {
            action: 'blnotifier_site_scan_get_link_ids',
            nonce: nonce
        }, function( response ) {
            if ( !response.success ) {
                setScanning( false );
                button.prop( 'disabled', false ).text( 'Check for Broken Links' );
                alert( 'Could not start the check.' );
                return;
            }

            checkQueue = response.data.ids;
            checkIndex = 0;
            $( '#bln-check-total' ).text( checkQueue.length );

            if ( !checkQueue.length ) {
                setScanning( false );
                button.prop( 'disabled', false ).text( 'Check for Broken Links' );
                alert( 'No links found. Run Step 1 first.' );
                return;
            }

            checkNext();
        } );
    } );

} );