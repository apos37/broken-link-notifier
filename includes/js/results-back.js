jQuery( $ => {
    // console.log( 'Broken Link Notifier JS Loaded...' );

    // Add target _blank to title links
    $( 'a.row-title' ).attr( 'target', '_blank' );

    // Clear filters
    $( '#link-type-filter' ).on( 'change', function( e ) {
        $( '#code-filter' ).val( '' );
    } );
    $( '#code-filter' ).on( 'change', function( e ) {
        $( '#link-type-filter' ).val( '' );
    } );

    // Nonces
    var nonceRescan = blnotifier_back_end.nonce_rescan;
    var nonceReplace = blnotifier_back_end.nonce_replace;
    var nonceDelete = blnotifier_back_end.nonce_delete;


    /**
     * RE-SCAN
     */
   
    // Scan an individual link
    const scanLink = async ( link, postID, code, type, sourceID, method ) => {
        console.log( `Scanning link (${link})...` );

        // Say it started
        var span = $( `#bln-verify-${postID}` );
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
                postID: postID,
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
            const postID = linkSpan.dataset.postId;
            const code = linkSpan.dataset.code;
            const type = linkSpan.dataset.type;
            const sourceID = linkSpan.dataset.sourceId;
            const method = linkSpan.dataset.method;

            // Scan it
            const data = await scanLink( link, postID, code, type, sourceID, method );
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
                
                $( `#post-${postID}` ).addClass( 'omitted' );
                $( `#post-${postID} .bln-type` ).addClass( statusType ).text( statusType );
                $( `#post-${postID} .bln_type code` ).html( 'Code: ' + statusCode );
                $( `#post-${postID} .bln_type .message` ).text( statusText );
                $( `#post-${postID} .title .row-actions` ).remove();
                $( `#post-${postID} .bln_source .row-actions` ).remove();

                // Also reduce count in admin bar
                reduceAdminBarCount();

            } else if ( code != statusCode || type != statusType ) {
                if ( statusCode == 'ERR_FAILED' ) {
                    text = `Failed to remove link. ${statusText}`;
                } else if ( code != statusCode ) {
                    text = `Link is still bad, but showing a different code. Old code was ${code}; new code is ${statusCode}.`;
                } else {
                    text = `Link is still bad, but showing a different type. Old type was ${type}; new type is ${statusType}.`;
                }
                $( `#post-${postID} .bln-type` ).attr( 'class', `bln-type ${statusType}`).text( statusType );
                var codeLink = 'Code: ' + statusCode;
                if ( statusCode != 0 && statusCode != 666 ) {
                    codeLink = `<a href="https://http.dev/${statusCode}" target="_blank">Code: ${statusCode}</a>`;
                }
                $( `#post-${postID} .bln_type code` ).html( codeLink );
                $( `#post-${postID} .bln_type message` ).text( statusText );
            } else {
                text = `Still showing ${statusType}.`;
            }

            // Update the page
            $( `#bln-verify-${postID}` ).removeClass( 'scanning' ).addClass( statusType ).html( text );
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

    $( document ).on( 'click', '.replace-link', function ( e ) {
        e.preventDefault();
    
        let linkElement = $( this ).closest( 'td' ).find( '.row-title' );
        let oldLink = linkElement.attr( 'href' );
        let postID = $( this ).data( 'post-id' );
        let sourceID = $( this ).data( 'source-id' );
    
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
            saveLink( $(this), oldLink, sourceID, postID );
        } );
    
        // Handle Enter key press
        inputField.on( 'keypress', function ( e ) {
            if ( e.which === 13 ) { // Enter key
                $( this ).blur(); // Trigger blur event to save the new link
            }
        } );
    } );
    
    // Function to save the link and replace the input field with a new link
    function saveLink( inputField, oldLink, sourceID, postID ) {
        let newLink = inputField.val().trim();
    
        // If the new link is empty, revert to the original
        if ( newLink === '' || newLink === oldLink || !sourceID ) {
            inputField.replaceWith( `<a class="row-title" href="${oldLink}" target="_blank">${oldLink}</a>` );
            return;
        }
        console.log( oldLink, newLink );
    
        // Create a new link element with the updated text
        let newLinkElement = $( `<a class="row-title" href="${newLink}" target="_blank">${newLink}</a>` );
    
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
                resultID: postID,
                oldLink: oldLink,
                newLink: newLink,
                sourceID: sourceID
            },
            success: function( response ) {
                if ( response.success ) {
                    console.log( 'Link updated successfully.' );

                    // Replace the source blink
                    let viewPageLink = $( `tr#post-${postID} .column-bln_source .row-actions .view a` );
                    let currentHref = viewPageLink.attr( 'href' );
                    let newBlinkUrl = encodeURIComponent( newLink );
                    let updatedHref = currentHref.replace( /(blink=)[^\&]*/, `$1${newBlinkUrl}` );
                    viewPageLink.attr( 'href', updatedHref ); 

                    // Update the type
                    $( `#post-${postID} .bln-type` ).addClass( 'fixed' ).text( 'Replaced' );
                    $( `#post-${postID} .bln_type code` ).remove();
                    $( `#post-${postID} .bln_type .message` ).html( `The old link has been replaced. Result will be removed.<br>Old link: ${oldLink}` );

                    // Remove omit link action
                    $( `#post-${postID} .column-title .row-actions .clear` ).remove();
                    $( `#post-${postID} .column-title .row-actions .omit` ).remove();

                    // Update the Verify column
                    $( `#bln-verify-${postID}` ).text( `N/A` );

                    let rowActions = $( `#post-${postID} .column-title .row-actions` );
                    if ( rowActions.children().length === 1 ) {
                        rowActions.html( rowActions.html().replace(' | ', '' ) );
                    }

                    // Reduce admin bar count
                    reduceAdminBarCount();
                    
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

    $( document ).on( 'click', '.clear-result', function ( e ) {
        e.preventDefault();

        let button = $( this );
        let postID = button.data( 'post-id' );

        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_back_end.ajaxurl,
            data: { 
                action: 'blnotifier_delete_result',
                nonce: nonceDelete,
                postID: postID
            },
            success: function ( response ) {
                if ( response.success ) {
                    button.closest( 'tr' ).fadeOut( 'fast', function () {
                        $( this ).remove();
                    } );

                    // Reduce admin bar count
                    reduceAdminBarCount();

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
        console.log( sourceID );
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
                        let rowSourceID = row.find( '.row-actions[data-source-id]' ).attr( 'data-source-id' );
    
                        if ( rowSourceID == sourceID ) {
                            row.fadeOut( 'fast', function () {
                                $( this ).remove();
                            } );

                            // Reduce admin bar count
                            reduceAdminBarCount();
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

    function reduceAdminBarCount() {
        var adminBarEl = $( '#wp-admin-bar-blnotifier-notify' );
        if ( adminBarEl.length ) {
            var adminBarCountEl = adminBarEl.find( '.awaiting-mod' );
            var adminBarCount = parseInt( adminBarCountEl.text(), 10 );
    
            if ( !isNaN( adminBarCount ) && adminBarCount > 0 ) {
                adminBarCountEl.text( adminBarCount - 1 );
            }
        }
    }
    
} )