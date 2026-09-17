jQuery( $ => {

    const nonce = blnotifier_scan_suggest.nonce;
    const omitsNonce = blnotifier_scan_suggest.omits_nonce;
    const postTypes = blnotifier_scan_suggest.post_types;
    const ajaxUrl = blnotifier_scan_suggest.ajaxurl;

    const input = $( '#url-search-input' );
    const hiddenValue = $( '#url-search-value' );
    const suggestionsBox = $( '#bln-scan-suggestions' );

    const $postTypeSelect = $( '#bln-scan-picker-post-type' );
    const $postSelect = $( '#bln-scan-picker-post' );

    $postTypeSelect.on( 'change', function() {
        const postType = $( this ).val();
        $postSelect.prop( 'disabled', true ).html( '<option value="">' + blnotifier_scan_suggest.text.loading + '</option>' );

        if ( !postType ) {
            $postSelect.html( '<option value="">' + blnotifier_scan_suggest.text.choose_post_type + '</option>' );
            return;
        }

        $.post( ajaxUrl, {
            action: 'blnotifier_omits_get_posts',
            nonce: omitsNonce,
            post_type: postType
        }, function( response ) {
            if ( response.success && response.data.items.length ) {
                let options = '<option value="">' + blnotifier_scan_suggest.text.choose_a + postTypes[ postType ] + '...</option>';
                response.data.items.forEach( function( item ) {
                    options += '<option value="' + item.id + '" data-title="' + item.title + '">' + item.title + '</option>';
                } );
                $postSelect.html( options ).prop( 'disabled', false );
            } else {
                $postSelect.html( '<option value="">' + blnotifier_scan_suggest.text.no_items_found + '</option>' );
            }
        } );
    } );

    $postSelect.on( 'change', function() {
        const postId = $( this ).val();
        if ( postId ) {
            hiddenValue.val( postId );
        }
    } );

    let searchTimer = null;
    let activeIndex = -1;

    const looksLikeUrlOrId = ( value ) => {
        return /^https?:\/\//i.test( value ) || value.startsWith( '/' ) || /^\d+$/.test( value );
    }

    const hideSuggestions = () => {
        suggestionsBox.hide().empty();
        activeIndex = -1;
    }

    const renderSuggestions = ( items ) => {
        suggestionsBox.empty();

        if ( !items.length ) {
            hideSuggestions();
            return;
        }

        items.forEach( function( item ) {
            const row = $( '<div class="bln-suggestion-item"></div>' )
                .attr( 'data-id', item.id )
                .attr( 'data-title', item.title )
                .html( '<span class="bln-suggestion-title"></span><span class="bln-suggestion-meta"></span>' );

            row.find( '.bln-suggestion-title' ).text( item.title );
            row.find( '.bln-suggestion-meta' ).text( '(' + item.type + ' - ' + item.status + ')' );

            suggestionsBox.append( row );
        } );

        suggestionsBox.show();
    }

    input.on( 'input', function() {
        const value = $( this ).val().trim();
        hiddenValue.val( value );

        if ( value === '' || looksLikeUrlOrId( value ) ) {
            hideSuggestions();
            return;
        }

        clearTimeout( searchTimer );
        searchTimer = setTimeout( function() {
            $.post( ajaxUrl, {
                action: 'blnotifier_scan_suggest',
                nonce: nonce,
                search: value
            }, function( response ) {
                if ( response.success ) {
                    renderSuggestions( response.data.items );
                }
            } );
        }, 300 );
    } );

    suggestionsBox.on( 'click', '.bln-suggestion-item', function() {
        const id = $( this ).data( 'id' );
        const title = $( this ).data( 'title' );

        input.val( title );
        hiddenValue.val( id );
        hideSuggestions();
    } );

    input.on( 'keydown', function( e ) {
        const items = suggestionsBox.find( '.bln-suggestion-item' );
        if ( !items.length || suggestionsBox.is( ':hidden' ) ) {
            return;
        }

        if ( e.key === 'ArrowDown' ) {
            e.preventDefault();
            activeIndex = Math.min( activeIndex + 1, items.length - 1 );
            items.removeClass( 'active' ).eq( activeIndex ).addClass( 'active' );
        } else if ( e.key === 'ArrowUp' ) {
            e.preventDefault();
            activeIndex = Math.max( activeIndex - 1, 0 );
            items.removeClass( 'active' ).eq( activeIndex ).addClass( 'active' );
        } else if ( e.key === 'Enter' ) {
            if ( activeIndex > -1 ) {
                e.preventDefault();
                items.eq( activeIndex ).trigger( 'click' );
            }
        } else if ( e.key === 'Escape' ) {
            hideSuggestions();
        }
    } );

    $( document ).on( 'click', function( e ) {
        if ( !$( e.target ).closest( '#bln-scan-search-wrap' ).length ) {
            hideSuggestions();
        }
    } );

    let clickedSubmitButton = null;

    $( '#bln-scan-picker-button' ).on( 'click', function() {
        $( '#scan-source-field' ).val( 'picker' );
    } );

    $( '#url-search-button' ).on( 'click', function() {
        $( '#scan-source-field' ).val( 'text' );
    } );

    $( document ).on( 'click', 'button[type="submit"]', function() {
        clickedSubmitButton = this;
    } );

    $( 'form' ).on( 'submit', function() {
        if ( clickedSubmitButton ) {
            $( clickedSubmitButton ).prop( 'disabled', true ).html( '<span class="bln-spinner-inline"></span>' + blnotifier_scan_suggest.text.scanning );
        }
    } );

} );