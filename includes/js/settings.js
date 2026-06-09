jQuery( $ => {
    // console.log( 'Settings JS Loaded...' );

    // On load
    const discordEnabled = $( '#blnotifier_enable_discord' );
    const discordWebhookInput = $( '#blnotifier_discord' );
    if ( discordEnabled.is( ':checked' ) ) {
        discordWebhookInput.prop( 'required', true );
    } else {
        discordWebhookInput.prop( 'required', false );
    }

    const msteamsEnabled = $( '#blnotifier_enable_msteams' );
    const msteamsWebhookInput = $( '#blnotifier_msteams' );
    if ( msteamsEnabled.is( ':checked' ) ) {
        msteamsWebhookInput.prop( 'required', true );
    } else {
        msteamsWebhookInput.prop( 'required', false );
    }

    // Listen for enabling/disabling
    discordEnabled.on( 'click', function( e ) {
        if ( this.checked ) {
            discordWebhookInput.prop( 'required', true );
        } else {
            discordWebhookInput.prop( 'required', false );
        }
    } );

    // Listen for omitting links
    msteamsEnabled.on( 'click', function( e ) {
        if ( this.checked ) {
            msteamsWebhookInput.prop( 'required', true );
        } else {
            msteamsWebhookInput.prop( 'required', false );
        }
    } );

    // Toggle the status codes
    $( '.toggle-link' ).on( 'click', function( e ) {
        e.preventDefault();
        const target = $( '.' + $( this ).data( 'target' ) );
        if ( target.is( ':visible' ) ) {
            target.hide();
            $( this ).text( 'View/Change Status Types' );
        } else {
            target.show();
            $( this ).text( 'Hide Status Types' );
        }
    } );

    // Listen for status code changes
    $( '.status-row input' ).on( 'change', function( e ) {
        const $row = $( this ).closest( '.status-row' );
        const type = $( this ).val();
    
        $row.removeClass( 'good warning broken' ).addClass( type );
        $row.find( '.type' ).text( type.toUpperCase() );
    } );    

    // API Key Generation and Copying
    $( '#blnotifier-generate-key' ).on( 'click', function() {
        let chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let new_key = Array.from( crypto.getRandomValues( new Uint8Array( 48 ) ) )
            .map( val => chars[ val % chars.length ] )
            .join( '' );
        $( '#blnotifier-api-key-display' ).text( new_key ).show();
        $( '#blnotifier_api_key' ).val( new_key );
        $( '#blnotifier-copy-key' ).show();
    } );

    $( '#blnotifier-copy-key' ).on( 'click', function() {
        let key = $( '#blnotifier-api-key-display' ).text();
        if ( !key ) return;
        navigator.clipboard.writeText( key ).then( () => {
            let btn = $( this );
            btn.text( 'Copied!' );
            setTimeout( () => btn.text( 'Copy' ), 2000 );
        } );
    } );

    // Enable/Disable Test Buttons
    $( document ).on( 'input', 'input[id="blnotifier_emails"], input[type="url"][id^="blnotifier_"]', function() {
        let field_id = $( this ).attr( 'id' );
        let btn = $( '.blnotifier-test-btn[data-field="' + field_id + '"]' );
        btn.prop( 'disabled', $( this ).val().trim() === '' );
    } );

    // Handle Test Button Clicks
    $( document ).on( 'click', '.blnotifier-test-btn', function() {
        let btn      = $( this );
        let type     = btn.data( 'type' );
        let field_id = btn.data( 'field' );
        let value    = $( '#' + field_id ).val().trim();

        if ( !value ) return;

        btn.prop( 'disabled', true ).html( '<span class="spinner is-active" style="float:none;margin:-10px 4px -6px 0;"></span>Sending...' );

        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: blnotifier_settings.ajaxurl,
            data: {
                action: 'blnotifier_test_notification',
                nonce:  blnotifier_settings.nonce,
                type:   type,
                value:  value,
            },
            success: function( response ) {
                if ( response.success ) {
                    btn.html( '✓ Sent!' );
                } else {
                    let msg = response.data && response.data.msg ? response.data.msg : 'Failed.';
                    btn.html( '✗ ' + msg );
                }
                setTimeout( () => {
                    btn.prop( 'disabled', false ).text( 'Send Test' );
                }, 5000 );
            },
            error: function() {
                btn.html( '✗ Error' );
                setTimeout( () => {
                    btn.prop( 'disabled', false ).text( 'Send Test' );
                }, 5000 );
            }
        } );
    } );
} )