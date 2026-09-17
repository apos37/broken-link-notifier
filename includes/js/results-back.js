jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    // Nonces
    var nonceRescan = blnotifier_back_end.nonce_rescan;
    var nonceReplace = blnotifier_back_end.nonce_replace;
    var nonceDelete = blnotifier_back_end.nonce_delete;


    /**
     * SCAN
     */
   
    // Scan an individual link
    const scanLink = async ( link, linkID, code, type, sourceID, method ) => {
        console.log( `${blnotifier_back_end.text.scanning_link} (${link})...` );

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


    /**
     * REDUCE COUNT IN ADMIN BAR
     */

    function reduceCount() {
        if ( typeof window.blnRefreshResultsTable === 'function' ) {
            window.blnRefreshResultsTable();
        }
    }


    /**
     * EXPOSE FUNCTIONS TO GLOBAL SCOPE
     */
    window.scanLink = scanLink;
    window.reduceCount = reduceCount;


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
        console.log( `${blnotifier_back_end.text.saving_link} (${newLink})...`, { oldLink, sourceID, linkID } );
    
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
                    if ( response.data.details ) {
                        console.log( blnotifier_back_end.text.details + ':', response.data.details );
                    }

                    // Replace the old data-link in the Replace Link attribute
                    $( `#link-${linkID} .link .row-actions .replace-link a` ).data( 'link', newLink ).attr( 'data-link', newLink );

                    // Replace the source blink
                    let viewPageLink = $( `tr#link-${linkID} .source .row-actions .view a` );
                    if ( viewPageLink.length ) {
                        let currentHref = viewPageLink.attr( 'href' );
                        let newBlinkUrl = encodeURIComponent( newLink );
                        let updatedHref = currentHref.replace( /(blink=)[^\&]*/, `$1${newBlinkUrl}` );
                        viewPageLink.attr( 'href', updatedHref ); 
                    }

                    // Update the type
                    $( `#link-${linkID} .bln-type` ).addClass( 'fixed' ).text( 'Replaced' );
                    $( `#link-${linkID} .type .code` ).remove();
                    $( `#link-${linkID} .type .message` ).html( `${blnotifier_back_end.text.link_replace}<br>${blnotifier_back_end.text.old_link}: ${oldLink}` );

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
                    // Alert the specific msg from the PHP side
                    let errorMsg = ( response.data && response.data.msg ) ? response.data.msg : blnotifier_back_end.text.unknown_error;
                    alert( 'Update Failed: ' + errorMsg );
                    
                    // Revert the link text in the UI since the DB didn't update
                    newLinkElement.replaceWith( `<a href="${oldLink}" class="link-url" target="_blank" rel="noopener">${oldLink}</a>` );
                }
            },
            error: function() {
                alert( blnotifier_back_end.text.server_request_failed );
                // Revert the link text in the UI
                newLinkElement.replaceWith( `<a href="${oldLink}" class="link-url" target="_blank" rel="noopener">${oldLink}</a>` );
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
                alert( blnotifier_back_end.text.something_went_wrong );
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
        if ( !confirm( `${blnotifier_back_end.text.confirm_delete_page} ${postTitle}?` ) ) {
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
                alert( blnotifier_back_end.text.something_went_wrong );
            }
        } );
    } );

} )