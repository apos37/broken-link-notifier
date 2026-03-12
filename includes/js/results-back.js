jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    // Nonces
    var nonceRescan = blnotifier_back_end.nonce_rescan;
    var nonceReplace = blnotifier_back_end.nonce_replace;
    var nonceDelete = blnotifier_back_end.nonce_delete;


    /**
     * RE-SCAN
     */
   
    // Scan an individual link
    const scanLink = async ( link, linkID, code, type, sourceID, method ) => {
        console.log( `Scanning link (${link})...` );

        // Say it started
        var span = $( `#bln-verify-${linkID}` );
        span.addClass( 'scanning' ).html( `<em>Verifying</em>` );

        // Run the scan
        return await $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: { 
                action: 'blnotifier_rescan', 
                nonce: nonceRescan,
                link: link,
                linkID: linkID,
                code: code,
                type: type,
                sourceID: sourceID,
                method: method
            }
        } );
    }

    // Rescan all links
    const reScanLinks = async () => {
        
        // Get the post link spans
        const linkSpans = document.querySelectorAll( '.bln-verify' );

        // First count all the link for the button
        for ( const linkSpan of linkSpans ) {
            const link = linkSpan.dataset.link;
            const linkID = linkSpan.dataset.linkId;
            const code = linkSpan.dataset.code;
            const type = linkSpan.dataset.type;
            const sourceID = linkSpan.dataset.sourceId;
            const method = linkSpan.dataset.method;

            // Scan it
            const data = await scanLink( link, linkID, code, type, sourceID, method );
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
            if ( statusType == 'good' || statusType == 'omitted' || statusType == 'n/a' ) {
                if ( statusType == 'n/a' ) {
                    text = '<em>Source no longer exists, removing from list...</em>';
                } else {
                    text = '<em>Link is ' + statusType + ', removing from list...</em>';
                }
                
                $( `#link-${linkID}` ).addClass( 'omitted' );
                $( `#link-${linkID} .bln-type` ).addClass( statusType ).text( statusType );
                $( `#link-${linkID} .bln_type code` ).html( 'Code: ' + statusCode );
                $( `#link-${linkID} .bln_type .message` ).text( statusText );
                $( `#link-${linkID} .link .row-actions` ).remove();
                $( `#link-${linkID} .source .row-actions` ).remove();

                // Also reduce count in admin bar
                reduceCount();

            } else if ( code != statusCode || type != statusType ) {
                if ( statusCode == 'ERR_FAILED' ) {
                    text = `Failed to remove link. ${statusText}`;
                } else if ( code != statusCode ) {
                    text = `Link is still bad, but showing a different code. Old code was ${code}; new code is ${statusCode}.`;
                } else {
                    text = `Link is still bad, but showing a different type. Old type was ${type}; new type is ${statusType}.`;
                }
                $( `#link-${linkID} .bln-type` ).attr( 'class', `bln-type ${statusType}`).text( statusType );
                var codeLink = 'Code: ' + statusCode;
                if ( statusCode != 0 && statusCode != 666 ) {
                    codeLink = `<a href="https://http.dev/${statusCode}" target="_blank">Code: ${statusCode}</a>`;
                }
                $( `#link-${linkID} .bln_type code` ).html( codeLink );
                $( `#link-${linkID} .bln_type .message` ).text( statusText );
            } else {
                text = `Still showing ${statusType}.`;
            }

            // Update the page
            $( `#bln-verify-${linkID}` ).removeClass( 'scanning' ).addClass( statusType ).html( text );
        }

        return console.log( 'Done with all links' );
    }

    // Do it
    if ( blnotifier_back_end.verifying ) {
        reScanLinks();
    }


    /**
     * REPLACE LINK
     */

    $( document ).on( 'click', '.replace-link a', function ( e ) {
        e.preventDefault();
    
        let linkElement = $( this ).closest( 'td' ).find( '.link-url' );
        let oldLink = $( this ).data( 'link' );
        let linkID = $( this ).closest( 'tr' ).data( 'link-id' );
        let sourceID = $( this ).closest( 'tr' ).find( '.source' ).data( 'source-id' );
    
        // Get the current link text
        let currentLink = linkElement.text();
        
        // Create an input field with the current link value
        let inputField = $( `<input type="text" class="edit-link-input" value="${currentLink}" />` );
    
        // Replace the link with the input field
        linkElement.replaceWith( inputField );
    
        // Focus on the input field
        inputField.focus();
    
        // Handle input field blur (when user clicks outside)
        inputField.on( 'blur', function () {
            saveLink( $(this), oldLink, sourceID, linkID );
        } );
    
        // Handle Enter key press
        inputField.on( 'keypress', function ( e ) {
            if ( e.which === 13 ) { // Enter key
                $( this ).blur(); // Trigger blur event to save the new link
            }
        } );
    } );
    
    // Function to save the link and replace the input field with a new link
    function saveLink( inputField, oldLink, sourceID, linkID ) {
        let newLink = inputField.val().trim();
    
        // If the new link is empty, revert to the original
        if ( newLink === '' || newLink === oldLink || !sourceID ) {
            inputField.replaceWith( `<a href="${oldLink}" class="link-url" target="_blank" rel="noopener">${oldLink}</a>` );
            return;
        }
    
        // Create a new link element with the updated text
        let newLinkElement = $( `<a href="${newLink}" class="link-url" target="_blank" rel="noopener">${newLink}</a>` );
    
        // Replace the input field with the new link
        inputField.replaceWith( newLinkElement );
    
        // Send an AJAX request to save the new link in the database
        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: {
                action: 'blnotifier_replace_link', 
                nonce: nonceReplace,
                linkID: linkID,
                oldLink: oldLink,
                newLink: newLink,
                sourceID: sourceID
            },
            success: function( response ) {
                if ( response.success ) {
                    console.log( 'Link updated successfully.' );

                    // Replace the old data-link in the Replace Link attribute
                    $( `#link-${linkID} .link .row-actions .replace-link a` ).data( 'link', newLink ).attr( 'data-link', newLink );

                    // Replace the source blink
                    let viewPageLink = $( `tr#link-${linkID} .source .row-actions .view a` );
                    let currentHref = viewPageLink.attr( 'href' );
                    let newBlinkUrl = encodeURIComponent( newLink );
                    let updatedHref = currentHref.replace( /(blink=)[^\&]*/, `$1${newBlinkUrl}` );
                    viewPageLink.attr( 'href', updatedHref ); 

                    // Update the type
                    $( `#link-${linkID} .bln-type` ).addClass( 'fixed' ).text( 'Replaced' );
                    $( `#link-${linkID} .bln_type code` ).remove();
                    $( `#link-${linkID} .bln_type .message` ).html( `The old link has been replaced. Result will be removed.<br>Old link: ${oldLink}` );

                    // Remove omit link action
                    $( `#link-${linkID} .link .row-actions .clear-result` ).remove();
                    $( `#link-${linkID} .link .row-actions .omit-link` ).remove();

                    // Update the Verify column
                    $( `#bln-verify-${linkID}` ).text( `N/A` );

                    let rowActions = $( `#link-${linkID} .link .row-actions` );
                    if ( rowActions.children().length === 1 ) {
                        rowActions.html( rowActions.html().replace(' | ', '' ) );
                    }

                    // Reduce admin bar count
                    reduceCount();
                    
                } else {
                    alert( response.data );
                }
            },
            error: function() {
                alert('Something went wrong. Please try again.');
            }
        } );
    }


    /**
     * CLEAR RESULT ACTION
     */

    $( document ).on( 'click', '.clear-result a', function ( e ) {
        e.preventDefault();

        let button = $( this );
        let link = button.data( 'link' );
        let linkID = $( this ).closest( 'tr' ).data( 'link-id' );

        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: { 
                action: 'blnotifier_delete_result',
                nonce: nonceDelete,
                link: link,
                linkID: linkID
            },
            success: function ( response ) {
                if ( response.success ) {
                    button.closest( 'tr' ).fadeOut( 'fast', function () {
                        $( this ).remove();
                        reduceCount();
                    } );

                } else {
                    alert( response.data );
                }
            },
            error: function () {
                alert( 'Something went wrong. Please try again.' );
            }
        } );
    } );


    /**
     * DELETE SOURCE ACTION
     */

    $( document ).on( 'click', '.delete-source', function ( e ) {
        e.preventDefault();

        let button = $( this );
        let sourceID = button.data( 'source-id' );
        let postTitle = button.data( 'source-title' );

        // Show confirmation dialog
        if ( !confirm( `Are you sure you want to delete the page entitled ${postTitle}?` ) ) {
            return;
        }

        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: { 
                action: 'blnotifier_delete_source',
                nonce: nonceDelete,
                sourceID: sourceID
            },
            success: function ( response ) {
                if ( response.success ) {
                    $( 'tr' ).each( function () {
                        let row = $( this );
                        let rowSourceID = row.find( '.source[data-source-id]' ).attr( 'data-source-id' );
    
                        if ( rowSourceID == sourceID ) {
                            row.fadeOut( 'fast', function () {
                                $( this ).remove();
                                reduceCount();
                            } );
                        }
                    } );
                } else {
                    alert( response.data );
                }
            },
            error: function () {
                alert( 'Something went wrong. Please try again.' );
            }
        } );
    } );

    
    /**
     * REDUCE COUNT IN ADMIN BAR
     */

    function reduceCount() {
        // Count broken links currently on the page
        var countBroken = $( 'tr .bln-type.broken' ).length;
        var countAll = $( 'tr .bln-type' ).length;

        // Update admin bar
        var adminBarEl = $( '#wp-admin-bar-blnotifier-notify' );
        if ( adminBarEl.length ) {
            adminBarEl.find( '.blnotifier-count-indicator' ).text( countBroken );
        }

        // Update admin menu
        var adminMenuEl = $( 'li.toplevel_page_broken-link-notifier' );
        if ( adminMenuEl.length ) {
            adminMenuEl.find( '.awaiting-mod' ).text( countBroken );
        }

        // Update page total counter
        var pageCountEl = $( '#bln-total-broken-links' );
        if ( pageCountEl.length ) {
            pageCountEl.text( countAll );
        }
    }


    /**
     * TRASH SELECTED BUTTON
     */
    function updateDeleteButton() {
        const checkedCount = $( '.bln-row-checkbox:checked' ).length;
        $( '#bln-delete-selected' ).prop( 'disabled', checkedCount === 0 );
    }

    $( document ).on( 'change', '.bln-row-checkbox', function() {
        updateDeleteButton();
    } );

    $( '#cb-select-all-1' ).on( 'change', function() {
        const checked = $( this ).prop( 'checked' );
        $( '.bln-row-checkbox' ).prop( 'checked', checked );
        updateDeleteButton();
    } );
} )