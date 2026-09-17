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

    // Listen for status code changes
    $( '.status-row input' ).on( 'change', function( e ) {
        const $row = $( this ).closest( '.status-row' );
        const type = $( this ).val();

        $row.removeClass( 'good warning broken' ).addClass( type );
        $row.find( '.type' ).text( type.toUpperCase() );

        updateStatusCodeSummaries();
    } ); 

    // API Key Generation, Copying, and Clearing
    $( document ).on( 'click', '#blnotifier-generate-key', function() {
        let chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let new_key = Array.from( crypto.getRandomValues( new Uint8Array( 48 ) ) )
            .map( val => chars[ val % chars.length ] )
            .join( '' );

        $( '#blnotifier-api-key-display' ).removeClass( 'no-key' ).addClass( 'has-key' ).text( new_key );
        $( '#blnotifier_api_key' ).val( new_key );
        $( '#blnotifier-copy-key, #blnotifier-clear-key' ).prop( 'disabled', false );
        markDirty();
    } );

    $( document ).on( 'click', '#blnotifier-copy-key', function() {
        let key = $( '#blnotifier_api_key' ).val();
        if ( !key ) return;
        navigator.clipboard.writeText( key ).then( () => {
            let btn = $( this );
            let original = btn.text();
            btn.text( 'Copied!' );
            setTimeout( () => btn.text( original ), 2000 );
        } );
    } );

    $( document ).on( 'click', '#blnotifier-clear-key', function() {
        if ( !confirm( blnotifier_settings.text.confirm_clear_api ) ) {
            return;
        }
        $( '#blnotifier-api-key-display' ).removeClass( 'has-key' ).addClass( 'no-key' ).html( '<em>' + blnotifier_settings.text.no_api_generated + '</em>' );
        $( '#blnotifier_api_key' ).val( '' );
        $( this ).prop( 'disabled', true );
        $( '#blnotifier-copy-key' ).prop( 'disabled', true );
        markDirty();
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

        btn.prop( 'disabled', true ).html( '<span class="spinner is-active" style="float:none;margin:-10px 4px -6px 0;"></span>' + blnotifier_settings.text.sending );

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
                    btn.prop( 'disabled', false ).text( blnotifier_settings.text.send_test );
                }, 5000 );
            },
            error: function() {
                btn.html( '✗ Error' );
                setTimeout( () => {
                    btn.prop( 'disabled', false ).text( blnotifier_settings.text.send_test );
                }, 5000 );
            }
        } );
    } );

    // Accordion Toggle
    $( document ).on( 'click', '.blnotifier-accordion-toggle', function() {
        const accordion = $( this ).closest( '.blnotifier-accordion' );
        accordion.toggleClass( 'is-open' );
        accordion.find( '.blnotifier-accordion-panel' ).slideToggle( 150 );
    } );


    // Dirty state tracking
    const saveReminder = $( '#blnotifier-save-reminder' );
    let isDirty = false;

    function markDirty() {
        if ( !isDirty ) {
            isDirty = true;
            $( '#blnotifier-save-status' ).remove();
            saveReminder.fadeIn( 150 );
        }
    }

    $( window ).on( 'beforeunload', function( e ) {
        if ( isDirty ) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    } );

    $( document ).on( 'change input', '.blnotifier-settings-grid [name]', function() {
        markDirty();
    } );

    // Save button and status handling
    const saveNonce = blnotifier_settings.save_nonce;
    const $saveButton = $( '#blnotifier-save-settings' );
    const originalSaveText = $saveButton.text();

    function showSaving() {
        $saveButton.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + blnotifier_settings.text.saving );
        $( '#blnotifier-save-status' ).remove();
    }

    function showResult( message, success = true ) {
        $saveButton.prop( 'disabled', false ).text( originalSaveText );
        const $status = $( '<span id="blnotifier-save-status"></span>' ).text( message );
        $status.css( { color: success ? 'green' : 'red' } );
        $saveButton.after( $status );
    }

    function gatherSettingsData() {
        return $( '#blnotifier-settings-form' ).find( ':input' ).serialize();
    }

    function saveSettings() {
        if ( document.activeElement && typeof document.activeElement.blur === 'function' ) {
            document.activeElement.blur();
        }

        showSaving();

        $.ajax( {
            url: blnotifier_settings.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: gatherSettingsData() + '&action=blnotifier_save_settings&nonce=' + encodeURIComponent( saveNonce ),
            success: function( response ) {
                if ( response.success ) {
                    showResult( response.data && response.data.msg ? response.data.msg : blnotifier_settings.text.settings_saved );
                    isDirty = false;
                    saveReminder.hide();
                } else {
                    showResult( response.data && response.data.msg ? response.data.msg : blnotifier_settings.text.error_saving, false );
                }
            },
            error: function() {
                showResult( blnotifier_settings.text.error_saving, false );
            }
        } );
    }

    $saveButton.on( 'click', saveSettings );

    $( document ).on( 'keydown', function( event ) {
        if ( ( event.ctrlKey || event.metaKey ) && event.key === 's' ) {
            event.preventDefault();
            saveSettings();
        }
    } );

    // Show/hide notification fields based on their enable checkbox
    function updateNotificationFieldVisibility() {
        $( '[data-toggle]' ).each( function() {
            const $el = $( this );
            const toggleId = $el.data( 'toggle' );
            const isEnabled = $( '#' + toggleId ).is( ':checked' );
            $el.toggleClass( 'blnotifier-hidden', !isEnabled );
        } );
    }

    updateNotificationFieldVisibility();

    $( document ).on( 'change', '#blnotifier_enable_emailing, #blnotifier_enable_discord, #blnotifier_enable_slack, #blnotifier_enable_msteams', function() {
        updateNotificationFieldVisibility();
    } );

    function updateStatusCodeSummaries() {
        const broken = [];
        const warning = [];

        $( '.status-row' ).each( function() {
            const $row = $( this );
            const code = $row.find( '.code' ).text().trim();
            const checkedType = $row.find( 'input:checked' ).val();

            if ( checkedType === 'broken' ) {
                broken.push( code );
            } else if ( checkedType === 'warning' ) {
                warning.push( code );
            }
        } );

        $( '#bln-broken-codes-summary' ).text( broken.length ? broken.join( ', ' ) : 'None' );
        $( '#bln-warning-codes-summary' ).text( warning.length ? warning.join( ', ' ) : 'None' );
    }

    // --- CLEAR CACHE ---
    $( document ).on( 'click', '#blnotifier-clear-cache', function() {
        const button = $( this );
        const originalText = button.text();
        button.prop( 'disabled', true ).html( '<span class="blnotifier-spinner-inline"></span>' + blnotifier_settings.text.clearing );

        $.post( blnotifier_settings.ajaxurl, {
            action: 'blnotifier_clear_cache',
            nonce: blnotifier_settings.clear_cache_nonce
        }, function( response ) {
            button.prop( 'disabled', false ).text( originalText );
            if ( response.success ) {
                $( '#blnotifier-cache-count' ).text( blnotifier_settings.text.caching_no_links );
            } else {
                alert( blnotifier_settings.text.cannot_clear_cache );
            }
        } );
    } );

    // --- DOWNLOAD SETTINGS ---
    $( '#blnotifier-download-settings-btn' ).on( 'click', function( e ) {
        e.preventDefault();

        const data = {};
        blnotifier_settings.fields.forEach( function( field ) {
            const $field = $( '#' + field.name );

            let value;
            switch ( field.type ) {
                case 'checkbox':
                    value = $field.is( ':checked' ) ? 1 : 0;
                    break;
                case 'checkboxes':
                    value = $( '[name^="' + field.name + '["]:checked' ).map( function() {
                        return $( this ).attr( 'name' ).match( /\[(.+)\]/ )[ 1 ];
                    } ).get();
                    break;
                case 'status_codes':
                    value = {
                        broken: [],
                        warning: []
                    };
                    $( '.status-row input[type="radio"]:checked' ).each( function() {
                        const code = $( this ).closest( '.status-row' ).find( '.code' ).text().trim();
                        const val = $( this ).val();
                        if ( val === 'broken' ) {
                            value.broken.push( code );
                        } else if ( val === 'warning' ) {
                            value.warning.push( code );
                        }
                    } );
                    break;
                default:
                    value = $field.val();
            }

            data[ field.name ] = value;
        } );

        const blob = new Blob( [ JSON.stringify( data, null, 4 ) ], { type: 'application/json' } );
        const url = URL.createObjectURL( blob );
        const a = document.createElement( 'a' );
        a.href = url;
        a.download = 'broken-link-notifier-settings.json';
        a.click();
        URL.revokeObjectURL( url );
    } );

    // --- UPLOAD SETTINGS ---
    $( '#blnotifier-upload-settings' ).on( 'change', function( e ) {
        const file = e.target.files[ 0 ];
        if ( !file ) return;

        const reader = new FileReader();
        reader.onload = function( event ) {
            try {
                const uploadedSettings = JSON.parse( event.target.result );

                blnotifier_settings.fields.forEach( function( field ) {
                    if ( !Object.prototype.hasOwnProperty.call( uploadedSettings, field.name ) ) return;

                    const value = uploadedSettings[ field.name ];

                    switch ( field.type ) {
                        case 'checkbox':
                            $( '#' + field.name ).prop( 'checked', !!value );
                            break;
                        case 'checkboxes':
                            $( '[name^="' + field.name + '["]' ).prop( 'checked', false );
                            if ( Array.isArray( value ) ) {
                                value.forEach( function( key ) {
                                    $( '[name="' + field.name + '[' + key + ']"]' ).prop( 'checked', true );
                                } );
                            }
                            break;
                        case 'status_codes':
                            $( '.status-row' ).each( function() {
                                const $row = $( this );
                                const code = $row.find( '.code' ).text().trim();
                                let type = 'good';
                                if ( value.broken && value.broken.includes( code ) ) {
                                    type = 'broken';
                                } else if ( value.warning && value.warning.includes( code ) ) {
                                    type = 'warning';
                                }
                                $row.find( 'input[value="' + type + '"]' ).prop( 'checked', true ).trigger( 'change' );
                            } );
                            updateStatusCodeSummaries();
                            break;
                        default:
                            $( '#' + field.name ).val( value );
                    }
                } );

                updateNotificationFieldVisibility();
                markDirty();

                $( '#blnotifier-upload-settings-filename' ).text( file.name ).show();

            } catch ( err ) {
                alert( blnotifier_settings.text.invalid_json );
                $( '#blnotifier-upload-settings' ).val( '' );
            }
        };

        reader.readAsText( file );
    } );

    // --- RESET ALL SETTINGS ---
    $( '#blnotifier-reset-settings' ).on( 'click', function( e ) {
        e.preventDefault();

        if ( !confirm( blnotifier_settings.text.confirm_reset_all ) ) {
            return;
        }

        blnotifier_settings.fields.forEach( function( field ) {
            const $field = $( '#' + field.name );

            switch ( field.type ) {
                case 'checkbox':
                    $field.prop( 'checked', !!field.default );
                    break;
                case 'checkboxes':
                    $( '[name^="' + field.name + '["]' ).prop( 'checked', false );
                    if ( Array.isArray( field.default ) ) {
                        field.default.forEach( function( key ) {
                            $( '[name="' + field.name + '[' + key + ']"]' ).prop( 'checked', true );
                        } );
                    }
                    break;
                case 'status_codes':
                    $( '.status-row' ).each( function() {
                        const $row = $( this );
                        const code = $row.find( '.code' ).text().trim();
                        let type = 'good';
                        if ( field.default_broken && field.default_broken.includes( code ) ) {
                            type = 'broken';
                        } else if ( field.default_warning && field.default_warning.includes( code ) ) {
                            type = 'warning';
                        }
                        $row.find( 'input[value="' + type + '"]' ).prop( 'checked', true ).trigger( 'change' );
                    } );
                    updateStatusCodeSummaries();
                    break;
                default:
                    $field.val( field.default !== null ? field.default : '' );
            }
        } );

        markDirty();
    } );
    
} );