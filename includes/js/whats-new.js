jQuery( $ => {

    const dismiss = () => {
        $( '#blnotifier-whats-new-overlay' ).fadeOut( 150, function() {
            $( this ).remove();
        } );

        $.post( blnotifier_whats_new.ajaxurl, {
            action: 'blnotifier_dismiss_whats_new',
            nonce: blnotifier_whats_new.nonce
        } );
    }

    $( document ).on( 'click', '#blnotifier-whats-new-close, #blnotifier-whats-new-got-it', function() {
        dismiss();
    } );

    $( document ).on( 'click', '#blnotifier-whats-new-overlay', function( e ) {
        if ( e.target.id === 'blnotifier-whats-new-overlay' ) {
            dismiss();
        }
    } );

} );