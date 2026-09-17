jQuery( $ => {

    const nonce = blnotifier_omits_quick_add.nonce;
    const ajaxUrl = blnotifier_omits_quick_add.ajaxurl;

    const $postTypeSelect = $( '#bln-quick-add-post-type' );
    const $postSelect = $( '#bln-quick-add-post' );
    const $urlField = $( '#tag-name' );

    if ( !$postTypeSelect.length ) {
        return;
    }

    $postTypeSelect.on( 'change', function() {
        const postType = $( this ).val();
        $postSelect.prop( 'disabled', true ).html( '<option value="">' + blnotifier_omits_quick_add.text.loading + '</option>' );

        if ( !postType ) {
            $postSelect.html( '<option value="">' + blnotifier_omits_quick_add.text.choose_post_type + '</option>' );
            return;
        }

        $.post( ajaxUrl, {
            action: 'blnotifier_omits_get_posts',
            nonce: nonce,
            post_type: postType
        }, function( response ) {
            if ( response.success && response.data.items.length ) {
                let options = '<option value="">' + blnotifier_omits_quick_add.text.choose_page + '</option>';
                response.data.items.forEach( function( item ) {
                    options += '<option value="' + item.url + '">' + item.title + '</option>';
                } );
                $postSelect.html( options ).prop( 'disabled', false );
            } else {
                $postSelect.html( '<option value="">' + blnotifier_omits_quick_add.text.no_items_found + '</option>' );
            }
        } );
    } );

    $postSelect.on( 'change', function() {
        const url = $( this ).val();
        if ( url ) {
            $urlField.val( url );
        }
    } );

} );