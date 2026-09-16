jQuery( $ => {

    const nonce = blnotifier_link_browser.nonce;
    const checkNonce = blnotifier_link_browser.check_nonce;
    const ajaxUrl = blnotifier_link_browser.ajaxurl;
    const scanDelay = blnotifier_link_browser.scan_delay_ms || 0;
    const exportNonce = blnotifier_link_browser.export_nonce;

    let currentPage = 1;
    let currentFilter = 'all';
    let searchTimer = null;
    let scanQueue = [];
    let scanIndex = 0;
    let redirectsOmitted = 0;

    const fetchTable = ( page = 1 ) => {
        const kind = $( '#bln-link-browser-kind' ).val();
        const search = $( '#bln-link-browser-search' ).val();
        const perPage = $( '#bln-link-browser-per-page' ).val();

        $( '#bln-link-browser-table tbody' ).html( '<tr><td colspan="5"><em>Loading...</em></td></tr>' );

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_table',
            nonce: nonce,
            search: search,
            filter: currentFilter,
            kind: kind,
            page: page,
            per_page: perPage
        }, function( response ) {
            if ( response.success ) {
                $( '#bln-link-browser-table tbody' ).html( response.data.rows );
                $( '.bln-lb-status-filter' ).each( function() {
                    const filterKey = $( this ).data( 'filter' );
                    const countMap = { 'all': response.data.counts.total, 'internal': response.data.counts.internal, 'external': response.data.counts.external };
                    if ( filterKey in countMap ) {
                        $( this ).find( '.count' ).text( '(' + countMap[ filterKey ] + ')' );
                    }
                } );
                currentPage = response.data.page;
                renderPagination( response.data.page, response.data.total_pages, response.data.total );
            }
            updateExportUrl();
        } );
    }

    const renderPagination = ( page, totalPages, totalItems ) => {
        $( '.bln-link-browser-pagination' ).each( function() {
            const container = $( this );
            container.empty();

            const countLabel = $( '<span class="displaying-num"></span>' ).text( totalItems + ' item' + ( totalItems == 1 ? '' : 's' ) );

            const wrapper = $( '<span class="pagination-links"></span>' );

            const atFirst = page <= 1;
            const atLast = page >= totalPages;

            if ( atFirst ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>' ) );
            } else {
                wrapper.append( $( '<button type="button" class="first-page button bln-lb-first"><span class="screen-reader-text">First page</span><span aria-hidden="true">&laquo;</span></button>' ) );
            }

            if ( atFirst ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>' ) );
            } else {
                wrapper.append( $( '<button type="button" class="prev-page button bln-lb-prev"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">&lsaquo;</span></button>' ) );
            }

            const pagingInput = $( '<span class="paging-input"></span>' );
            pagingInput.append( $( '<label for="bln-lb-current-page-selector" class="screen-reader-text">Current Page</label>' ) );
            const currentPageInput = $( '<input class="current-page bln-lb-current-page" id="bln-lb-current-page-selector" type="text" name="paged" size="1" aria-describedby="table-paging">' ).val( page );
            pagingInput.append( currentPageInput );
            pagingInput.append( $( '<span class="tablenav-paging-text"></span>' ).html( ' of <span class="total-pages">' + totalPages + '</span>' ) );
            wrapper.append( pagingInput );

            if ( atLast ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>' ) );
            } else {
                wrapper.append( $( '<button type="button" class="next-page button bln-lb-next"><span class="screen-reader-text">Next page</span><span aria-hidden="true">&rsaquo;</span></button>' ) );
            }

            if ( atLast ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>' ) );
            } else {
                wrapper.append( $( '<button type="button" class="last-page button bln-lb-last"><span class="screen-reader-text">Last page</span><span aria-hidden="true">&raquo;</span></button>' ) );
            }

            container.append( countLabel, wrapper );
        } );
    }

    const scanNext = () => {
        if ( scanIndex >= scanQueue.length ) {
            $.post( ajaxUrl, {
                action: 'blnotifier_link_browser_finish',
                nonce: nonce
            }, function( response ) {
                $( '#bln-scan-spinner' ).hide();
                $( '#bln-link-browser-progress' ).hide();

                if ( response.success ) {
                    const total = response.data.counts.total;
                    let message = '<span class="dashicons dashicons-yes-alt"></span> <strong>Scan complete!</strong> Found ' + total + ' link' + ( total == 1 ? '' : 's' ) + '.';
                    if ( redirectsOmitted > 0 ) {
                        message += ' Automatically omitted ' + redirectsOmitted + ' redirecting page' + ( redirectsOmitted == 1 ? '' : 's' ) + '.';
                    }
                    $( '#bln-last-scanned' ).addClass( 'bln-scan-complete' ).html( message ).show();
                    $( '#bln-count-total' ).text( response.data.counts.total );
                    $( '#bln-count-internal' ).text( response.data.counts.internal );
                    $( '#bln-count-external' ).text( response.data.counts.external );
                } else {
                    $( '#bln-last-scanned' ).show();
                }

                fetchTable( 1 );
                $( '#bln-run-link-browser-scan' ).prop( 'disabled', false ).text( 'Discover Links' );
            } );
            return;
        }

        const postId = scanQueue[ scanIndex ];

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_scan_post',
            nonce: nonce,
            postID: postId
        }, function( response ) {
            if ( response.success && response.data.redirect_detected ) {
                redirectsOmitted++;
            }
        } ).always( function() {
            scanIndex++;
            $( '#bln-progress-done' ).text( scanIndex );
            if ( scanDelay > 0 ) {
                setTimeout( scanNext, scanDelay );
            } else {
                scanNext();
            }
        } );
    }

    // Update the export URL based on the current filter, kind, and search input
    const updateExportUrl = () => {
        const $btn = $( '#bln-export-link-browser' );
        if ( !$btn.length ) return;

        const filter = 'all'; // Link Browser's status pills use internal/external as the filter value directly
        const kind = $( '#bln-link-browser-kind' ).val();
        const search = $( '#bln-link-browser-search' ).val();

        $btn.attr( 'href', ajaxUrl.replace( 'admin-ajax.php', 'admin.php' ) + '?' + $.param( {
            export: 'link-browser',
            filter: currentFilter,
            kind: kind,
            search: search,
            _wpnonce: exportNonce
        } ) );
    }

    // Status filter click
    $( document ).on( 'click', '.bln-lb-status-filter', function( e ) {
        e.preventDefault();
        currentFilter = $( this ).data( 'filter' );
        $( '.bln-lb-status-filter' ).removeClass( 'current' ).removeAttr( 'aria-current' );
        $( this ).addClass( 'current' ).attr( 'aria-current', 'page' );
        updateExportUrl();
        fetchTable( 1 );
    } );

    // Kind filter change
    $( document ).on( 'change', '#bln-link-browser-kind', function() {
        fetchTable( 1 );
    } );

    // Per page change
    $( document ).on( 'change', '#bln-link-browser-per-page', function() {
        fetchTable( 1 );
    } );

    // Search input with debounce
    $( document ).on( 'input', '#bln-link-browser-search', function() {
        clearTimeout( searchTimer );
        searchTimer = setTimeout( function() {
            fetchTable( 1 );
        }, 400 );
    } );

    // Pagination clicks
    $( document ).on( 'click', '.bln-lb-first', function() {
        fetchTable( 1 );
    } );
    $( document ).on( 'click', '.bln-lb-prev', function() {
        fetchTable( currentPage - 1 );
    } );
    $( document ).on( 'click', '.bln-lb-next', function() {
        fetchTable( currentPage + 1 );
    } );
    $( document ).on( 'click', '.bln-lb-last', function() {
        const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
        fetchTable( totalPages );
    } );

    $( document ).on( 'keypress', '.bln-lb-current-page', function( e ) {
        if ( e.which === 13 ) {
            e.preventDefault();
            const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
            let target = parseInt( $( this ).val(), 10 );
            if ( isNaN( target ) || target < 1 ) target = 1;
            if ( target > totalPages ) target = totalPages;
            fetchTable( target );
        }
    } );
    $( document ).on( 'blur', '.bln-lb-current-page', function() {
        const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
        let target = parseInt( $( this ).val(), 10 );
        if ( isNaN( target ) || target < 1 ) target = 1;
        if ( target > totalPages ) target = totalPages;
        if ( target !== currentPage ) {
            fetchTable( target );
        }
    } );

    // Toggle pages list
    $( document ).on( 'click', '.pages-toggle', function() {
        $( this ).siblings( '.pages-list' ).slideToggle( 150 );
    } );

    // Check status
    $( document ).on( 'click', '.check-status', function( e ) {
        e.preventDefault();

        const button = $( this );
        const link = button.data( 'link' );
        const postId = button.data( 'post-id' );
        const spinner = button.siblings( '.bln-spinner' );
        const resultBox = button.closest( 'tr' ).find( '.bln-status-result' );

        resultBox.attr( 'class', 'bln-status-result' ).hide();
        spinner.show();

        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: ajaxUrl,
            data: {
                action: 'blnotifier_scan',
                nonce: checkNonce,
                link: link,
                postID: postId,
                method: 'link-browser'
            }
        } ).done( function( response ) {
            if ( response.type == 'success' ) {
                const status = response.status;
                resultBox
                    .attr( 'class', 'bln-status-result ' + status.type )
                    .html( '<span class="bln-status-badge">' + status.type + '</span><span class="bln-status-text">' + status.code + ' - ' + status.text + '</span>' )
                    .show();
            } else {
                resultBox
                    .attr( 'class', 'bln-status-result error' )
                    .html( '<span class="bln-status-badge">Error</span><span class="bln-status-text">' + ( response.msg || 'Error checking link.' ) + '</span>' )
                    .show();
            }
        } ).fail( function() {
            resultBox
                .attr( 'class', 'bln-status-result error' )
                .html( '<span class="bln-status-badge">Error</span><span class="bln-status-text">Server error.</span>' )
                .show();
        } ).always( function() {
            spinner.hide();
        } );
    } );

    // Scan site
    $( '#bln-run-link-browser-scan' ).on( 'click', function() {
        const button = $( this );
        button.prop( 'disabled', true ).text( 'Discovering...' );

        $( '#bln-scan-spinner' ).show();
        $( '#bln-link-browser-progress' ).show();
        $( '#bln-last-scanned' ).hide();
        $( '#bln-progress-done' ).text( '0' );
        redirectsOmitted = 0;

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_get_queue',
            nonce: nonce
        }, function( response ) {
            if ( response.success ) {
                scanQueue = response.data.post_ids;
                scanIndex = 0;
                $( '#bln-progress-total' ).text( scanQueue.length );
                scanNext();
            } else {
                $( '#bln-scan-spinner' ).hide();
                button.prop( 'disabled', false ).text( 'Discover Links' );
                alert( 'Could not start scan.' );
            }
        } );
    } );

    // Omit link from future scans
    $( document ).on( 'click', '.omit-link', function( e ) {
        e.preventDefault();

        if ( !confirm( 'Omit this link from all future scans?' ) ) {
            return;
        }

        const button = $( this );
        const link = button.data( 'link' );
        const linkId = button.data( 'link-id' );
        const spinner = button.siblings( '.bln-spinner' );

        spinner.show();

        $.post( ajaxUrl, {
            action: 'blnotifier_link_browser_omit_link',
            nonce: nonce,
            link: link,
            linkID: linkId
        }, function( response ) {
            spinner.hide();
            if ( response.success ) {
                fetchTable( currentPage );
            } else {
                alert( response.data && response.data.msg ? response.data.msg : 'Could not omit link.' );
            }
        } );
    } );

    // Initial load
    updateExportUrl();
    fetchTable( 1 );

} );