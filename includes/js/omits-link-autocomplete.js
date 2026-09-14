jQuery( $ => {

    const nonce = blnotifier_omits_link_autocomplete.nonce;
    const ajaxUrl = blnotifier_omits_link_autocomplete.ajaxurl;

    const $urlField = $( '#tag-name' );
    if ( !$urlField.length ) {
        return;
    }

    let searchTimer = null;
    let activeIndex = -1;

    const $wrap = $( '<div id="bln-link-autocomplete-wrap"></div>' );
    $urlField.wrap( $wrap );
    const $suggestions = $( '<div id="bln-link-suggestions"></div>' );
    $urlField.after( $suggestions );

    const hideSuggestions = () => {
        $suggestions.hide().empty();
        activeIndex = -1;
    }

    const renderSuggestions = ( items ) => {
        $suggestions.empty();

        if ( !items.length ) {
            hideSuggestions();
            return;
        }

        items.forEach( function( item ) {
            const row = $( '<div class="bln-link-suggestion-item"></div>' )
                .attr( 'data-link', item.link )
                .html( '<span class="bln-link-suggestion-url"></span><span class="bln-link-suggestion-type"></span>' );

            row.find( '.bln-link-suggestion-url' ).text( item.link );
            row.find( '.bln-link-suggestion-type' ).text( '(' + item.type + ')' );

            $suggestions.append( row );
        } );

        $suggestions.show();
    }

    $urlField.on( 'input', function() {
        const value = $( this ).val().trim();

        if ( value.length < 2 ) {
            hideSuggestions();
            return;
        }

        clearTimeout( searchTimer );
        searchTimer = setTimeout( function() {
            $.post( ajaxUrl, {
                action: 'blnotifier_omits_search_links',
                nonce: nonce,
                search: value
            }, function( response ) {
                if ( response.success ) {
                    renderSuggestions( response.data.items );
                }
            } );
        }, 300 );
    } );

    $suggestions.on( 'click', '.bln-link-suggestion-item', function() {
        $urlField.val( $( this ).data( 'link' ) );
        hideSuggestions();
    } );

    $urlField.on( 'keydown', function( e ) {
        const items = $suggestions.find( '.bln-link-suggestion-item' );
        if ( !items.length || $suggestions.is( ':hidden' ) ) {
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
        if ( !$( e.target ).closest( '#bln-link-autocomplete-wrap' ).length ) {
            hideSuggestions();
        }
    } );

} );