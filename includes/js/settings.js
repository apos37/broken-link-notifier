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
} )