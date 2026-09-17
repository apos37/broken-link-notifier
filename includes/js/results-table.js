jQuery( $ => {
    const nonceTable = blnotifier_results_table.nonce_table;
    const nonceBulk = blnotifier_results_table.nonce_bulk;
    const ajaxUrl = blnotifier_results_table.ajaxurl;
    const exportNonce = blnotifier_results_table.export_nonce;

    let currentPage = 1;
    let currentFilter = 'all';
    let verifyingActive = false;
    let verifyPaused = false;

    const updateStatusCounts = ( counts ) => {
        $( '.bln-status-filter' ).each( function() {
            const filterKey = $( this ).data( 'filter' );
            const countMap = {
                'all': counts.total_all,
                'broken': counts.total_broken,
                'internal-broken': counts.internal_broken,
                'external-broken': counts.external_broken,
                'warning': counts.total_warning,
                'internal-warning': counts.internal_warning,
                'external-warning': counts.external_warning
            };
            if ( filterKey in countMap ) {
                $( this ).find( '.count' ).text( '(' + countMap[ filterKey ] + ')' );
            }
        } );
    }

    const updateApplyState = () => {
        $( '.bln-bulk-action' ).each( function() {
            const bulkAction = $( this ).val();
            const hasSelection = $( '.bln-row-checkbox:checked' ).length > 0;
            $( this ).siblings( '.bln-apply-bulk' ).prop( 'disabled', !bulkAction || !hasSelection );
        } );
    }

    const getPerPage = () => $( '.bln-results-per-page' ).first().val();
    const getBulkAction = () => $( '.bln-bulk-action' ).first().val();

    const fetchTable = ( page = 1 ) => {
        const perPage = getPerPage();

        $( '#bln-results-table tbody' ).html( '<tr><td colspan="7"><em>' + blnotifier_results_table.text.loading + '</em></td></tr>' );

        $.post( ajaxUrl, {
            action: 'blnotifier_results_table',
            nonce: nonceTable,
            filter: currentFilter,
            page: page,
            per_page: perPage
        }, function( response ) {
            if ( response.success ) {
                $( '#bln-results-table tbody' ).html( response.data.rows );
                updateStatusCounts( response.data.counts );
                currentPage = response.data.page;
                renderPagination( response.data.page, response.data.total_pages, response.data.total );
                $( '.bln-select-all' ).prop( 'checked', false );
                updateApplyState();

                // If verification is active, keep going on this new page automatically
                if ( verifyingActive ) {
                    verifyPaused = false;
                    verifyVisibleRows();
                }
            }
        } );
    }

    const renderPagination = ( page, totalPages, totalItems ) => {
        $( '.bln-results-pagination' ).each( function() {
            const container = $( this );
            container.empty();

            const countLabel = $( '<span class="displaying-num"></span>' ).text( totalItems + ' item' + ( totalItems == 1 ? '' : 's' ) );

            const wrapper = $( '<span class="pagination-links"></span>' );

            const atFirst = page <= 1;
            const atLast = page >= totalPages;

            // First page
            if ( atFirst ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>' ) );
            } else {
                const firstBtn = $( '<button type="button" class="first-page button bln-results-first"><span class="screen-reader-text">' + blnotifier_results_table.text.first_page + '</span><span aria-hidden="true">&laquo;</span></button>' );
                wrapper.append( firstBtn );
            }

            // Prev page
            if ( atFirst ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>' ) );
            } else {
                const prevBtn = $( '<button type="button" class="prev-page button bln-results-prev"><span class="screen-reader-text">' + blnotifier_results_table.text.previous_page + '</span><span aria-hidden="true">&lsaquo;</span></button>' );
                wrapper.append( prevBtn );
            }

            // Paging input
            const pagingInput = $( '<span class="paging-input"></span>' );
            pagingInput.append( $( '<label for="bln-current-page-selector" class="screen-reader-text">Current Page</label>' ) );
            const currentPageInput = $( '<input class="current-page bln-current-page" id="bln-current-page-selector" type="text" name="paged" size="1" aria-describedby="table-paging">' ).val( page );
            pagingInput.append( currentPageInput );
            pagingInput.append( $( '<span class="tablenav-paging-text"></span>' ).html( ' of <span class="total-pages">' + totalPages + '</span>' ) );
            wrapper.append( pagingInput );

            // Next page
            if ( atLast ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>' ) );
            } else {
                const nextBtn = $( '<button type="button" class="next-page button bln-results-next"><span class="screen-reader-text">' + blnotifier_results_table.text.next_page + '</span><span aria-hidden="true">&rsaquo;</span></button>' );
                wrapper.append( nextBtn );
            }

            // Last page
            if ( atLast ) {
                wrapper.append( $( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>' ) );
            } else {
                const lastBtn = $( '<button type="button" class="last-page button bln-results-last"><span class="screen-reader-text">' + blnotifier_results_table.text.last_page + '</span><span aria-hidden="true">&raquo;</span></button>' );
                wrapper.append( lastBtn );
            }

            container.append( countLabel, wrapper );
        } );
    }

    // Status filter link clicks
    $( document ).on( 'click', '.bln-status-filter', function( e ) {
        e.preventDefault();
        currentFilter = $( this ).data( 'filter' );
        $( '.bln-status-filter' ).removeClass( 'current' ).removeAttr( 'aria-current' );
        $( this ).addClass( 'current' ).attr( 'aria-current', 'page' );
        updateExportUrl();
        fetchTable( 1 );
    } );

    // Per page change (top and bottom stay in sync)
    $( document ).on( 'change', '.bln-results-per-page', function() {
        const value = $( this ).val();
        $( '.bln-results-per-page' ).val( value );
        fetchTable( 1 );
    } );

    // Bulk action select changing (top and bottom stay in sync)
    $( document ).on( 'change', '.bln-bulk-action', function() {
        const value = $( this ).val();
        $( '.bln-bulk-action' ).val( value );
        updateApplyState();
    } );

    // Pagination clicks
    $( document ).on( 'click', '.bln-results-first', function() {
        fetchTable( 1 );
    } );
    $( document ).on( 'click', '.bln-results-prev', function() {
        fetchTable( currentPage - 1 );
    } );
    $( document ).on( 'click', '.bln-results-next', function() {
        fetchTable( currentPage + 1 );
    } );
    $( document ).on( 'click', '.bln-results-last', function() {
        const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
        fetchTable( totalPages );
    } );

    // Manual page number entry
    $( document ).on( 'keypress', '.bln-current-page', function( e ) {
        if ( e.which === 13 ) {
            e.preventDefault();
            const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
            let target = parseInt( $( this ).val(), 10 );
            if ( isNaN( target ) || target < 1 ) target = 1;
            if ( target > totalPages ) target = totalPages;
            fetchTable( target );
        }
    } );
    $( document ).on( 'blur', '.bln-current-page', function() {
        const totalPages = parseInt( $( this ).closest( '.pagination-links' ).find( '.total-pages' ).text(), 10 );
        let target = parseInt( $( this ).val(), 10 );
        if ( isNaN( target ) || target < 1 ) target = 1;
        if ( target > totalPages ) target = totalPages;
        if ( target !== currentPage ) {
            fetchTable( target );
        }
    } );
    // Select all (top and bottom stay in sync)
    $( document ).on( 'change', '.bln-select-all', function() {
        const checked = $( this ).prop( 'checked' );
        $( '.bln-select-all' ).prop( 'checked', checked );
        $( '.bln-row-checkbox' ).prop( 'checked', checked );
        updateApplyState();
    } );

    // Row checkbox toggling
    $( document ).on( 'change', '.bln-row-checkbox', function() {
        updateApplyState();
    } );

    // Bulk apply
    $( document ).on( 'click', '.bln-apply-bulk', function() {
        const bulkAction = getBulkAction();
        const ids = $( '.bln-row-checkbox:checked' ).map( function() { return $( this ).val(); } ).get();

        if ( !bulkAction || !ids.length ) {
            return;
        }

        const confirmMsg = bulkAction === 'clear'
            ? blnotifier_results_table.text.clear_results
            : blnotifier_results_table.text.omit_items;

        if ( !confirm( confirmMsg ) ) {
            return;
        }

        const buttons = $( '.bln-apply-bulk' );
        buttons.prop( 'disabled', true ).val( blnotifier_results_table.text.applying );

        $.post( ajaxUrl, {
            action: 'blnotifier_results_bulk',
            nonce: nonceBulk,
            bulk_action: bulkAction,
            ids: ids
        }, function( response ) {
            buttons.val( 'Apply' );
            if ( response.success ) {
                fetchTable( currentPage );
            } else {
                alert( response.data && response.data.msg ? response.data.msg : blnotifier_results_table.text.bulk_action_failed );
                buttons.prop( 'disabled', false );
            }
        } );
    } );

    /**
     * Verify whatever rows are currently visible on the page
     */
    const verifyVisibleRows = async () => {
        const linkSpans = document.querySelectorAll( '.bln-verify' );
        let anyRemoved = false;

        for ( const linkSpan of linkSpans ) {

            if ( verifyPaused ) {
                return;
            }

            const link = linkSpan.dataset.link;
            const linkID = linkSpan.dataset.linkId;
            const code = linkSpan.dataset.code;
            const type = linkSpan.dataset.type;
            const sourceID = linkSpan.dataset.sourceId;
            const method = linkSpan.dataset.method;

            $( linkSpan ).addClass( 'scanning' ).html( '<em class="dotdotdot">' + blnotifier_results_table.text.verifying + '</em>' );

            const data = await window.scanLink( link, linkID, code, type, sourceID, method );

            var statusType, statusText, statusCode;
            if ( data && data.type == 'success' ) {
                statusType = data.status.type;
                statusText = data.status.text;
                statusCode = data.status.code;
            } else {
                statusType = 'error';
                statusText = data.msg;
                statusCode = 'ERR_FAILED';
            }

            var text;
            if ( statusType == 'good' || statusType == 'omitted' || statusType == 'n/a' ) {
                text = ( statusType == 'n/a' )
                    ? '<em>' + blnotifier_results_table.text.no_source + '</em>'
                    : statusType == 'good' ? '<em>' + blnotifier_results_table.text.link_good + '</em>'
                    : '<em>' + blnotifier_results_table.text.link_omitted + '</em>';

                $( `#link-${linkID}` ).addClass( 'omitted' );
                $( `#link-${linkID} .bln-type` ).addClass( statusType ).text( statusType );
                $( `#link-${linkID} .bln_type code` ).html( 'Code: ' + statusCode );
                $( `#link-${linkID} .bln_type .message` ).text( statusText );
                $( `#link-${linkID} .link .row-actions` ).remove();
                $( `#link-${linkID} .source .row-actions` ).remove();

                anyRemoved = true;

            } else if ( code != statusCode || type != statusType ) {
                if ( statusCode == 'ERR_FAILED' ) {
                    text = `${blnotifier_results_table.text.failed_to_remove} ${statusText}`;
                } else if ( code != statusCode ) {
                    text = `${blnotifier_results_table.text.diff_code}  ${blnotifier_results_table.text.old_code} ${code}; ${blnotifier_results_table.text.new_code} ${statusCode}.`;
                } else {
                    text = `${blnotifier_results_table.text.diff_type} ${blnotifier_results_table.text.old_type} ${type}; ${blnotifier_results_table.text.new_type} ${statusType}.`;
                }
                $( `#link-${linkID} .bln-type` ).attr( 'class', `bln-type ${statusType}` ).text( statusType );
                var codeLink = blnotifier_results_table.text.code + ': ' + statusCode;
                if ( statusCode != 0 && statusCode != 666 ) {
                    codeLink = `<a href="https://http.dev/${statusCode}" target="_blank">${blnotifier_results_table.text.code}: ${statusCode}</a>`;
                }
                $( `#link-${linkID} .bln_type code` ).html( codeLink );
                $( `#link-${linkID} .bln_type .message` ).text( statusText );
            } else {
                text = `Still showing ${statusType}.`;
            }

            $( linkSpan ).removeClass( 'scanning' ).addClass( statusType ).html( text );
        }

        // Resync once, after the whole batch finishes, instead of per-row
        if ( anyRemoved ) {
            fetchTable( currentPage );
        }
    }

    // Update the export button URL based on the current filter
    const updateExportUrl = () => {
        const $btn = $( '#bln-export-results' );
        if ( !$btn.length ) return;

        $btn.attr( 'href', ajaxUrl.replace( 'admin-ajax.php', 'admin.php' ) + '?' + $.param( {
            export: 'results',
            filter: currentFilter,
            _wpnonce: exportNonce
        } ) );
    }

    // Verify / Pause toggle
    $( document ).on( 'click', '#bln-toggle-verification', function() {
        const button = $( this );
        const label = button.text().trim();

        if ( label === blnotifier_results_table.text.verify_link_statuses ) {
            verifyingActive = true;
            verifyPaused = false;
            button.text( blnotifier_results_table.text.pause_verification );
            verifyVisibleRows();
        } else {
            verifyingActive = false;
            verifyPaused = true;
            button.text( blnotifier_results_table.text.verify_link_statuses );
        }
    } );

    // Dismiss the verification notice
    $( document ).on( 'click', '#bln-verify-notice-dismiss', function() {
        $( '#bln-verify-notice-wrap' ).fadeOut( 150, function() {
            $( this ).remove();
        } );

        $.post( ajaxUrl, {
            action: 'blnotifier_dismiss_verify_notice',
            nonce: blnotifier_results_table.dismiss_notice_nonce
        } );
    } );

    // Render pagination for the server-rendered initial table
    renderPagination( 1, blnotifier_results_table.initial_total_pages, blnotifier_results_table.initial_total );
    updateExportUrl();

} );