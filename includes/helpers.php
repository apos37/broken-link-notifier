<?php
/**
 * Helpers
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Main plugin class.
 */
class BLNOTIFIER_HELPERS {

    /**
     * Get the current tab
     *
     * @return string|false
     */
    public function get_tab() {
        return isset( $_GET[ 'tab' ] ) ? sanitize_key( $_GET[ 'tab' ] ) : false; // phpcs:ignore
    } // End get_tab()


    /**
     * Get post type name
     *
     * @param string $post_type
     * @return string
     */
    public function get_post_type_name( $post_type, $singular = false ) {
        $post_type_obj = get_post_type_object( $post_type );
        if ( $singular ) {
            return $post_type_obj->labels->singular_name;
        } else {
            return $post_type_obj->labels->name;
        }
    } // End get_post_type_name()
    

    /**
     * Check if we are pausing frontend scanning
     *
     * @return boolean
     */
    public function is_frontend_scanning_paused() {
        return filter_var( get_option( 'blnotifier_pause_frontend_scanning' ), FILTER_VALIDATE_BOOLEAN );
    } // End is_frontend_scanning_paused()


    /**
     * Check if we are pausing results verification
     *
     * @return boolean
     */
    public function is_results_verification_paused() {
        return filter_var( get_option( 'blnotifier_pause_results_verification' ), FILTER_VALIDATE_BOOLEAN );
    } // End is_results_verification_paused()

    
    /**
     * Get the bad status codes we are using
     *
     * @return array
     */
    public function get_bad_status_codes() {
        $default_codes = [ 666, 308, 400, 404, 408 ];

        $types = filter_var_array( get_option( 'blnotifier_status_codes', [] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( empty( $types ) ) {
            $old_filtered_array = filter_var_array( apply_filters( 'blnotifier_bad_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );
            if ( !empty( $old_filtered_array ) ) {
                foreach ( $old_filtered_array as $code ) {
                    $types[ $code ] = 'broken';
                }
            }
        }

        $codes = [];
        if ( !empty( $types ) ) {
            foreach ( $types as $code => $type ) {
                if ( $type === 'broken' ) {
                    $codes[] = $code;
                }
            }
        } else {
            $codes = $default_codes;
        }
        
        return $codes;
    } // End get_bad_status_codes()


    /**
     * Get the warning status codes we are using
     *
     * @return array
     */
    public function get_warning_status_codes( $force_enable = false ) {
        $default_codes = [ 0, 413 ];

        $types = filter_var_array( get_option( 'blnotifier_status_codes', [] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( empty( $types ) ) {
            $old_filtered_array = filter_var_array( apply_filters( 'blnotifier_warning_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );
            if ( !empty( $old_filtered_array ) ) {
                foreach ( $old_filtered_array as $code ) {
                    $types[ $code ] = 'warning';
                }
            }
        }

        $codes = [];
        if ( !empty( $types ) ) {
            foreach ( $types as $code => $type ) {
                if ( $type === 'warning' ) {
                    $codes[] = $code;
                }
            }
        } else {
            $codes = $default_codes;
        }

        return ( $force_enable || $this->are_warnings_enabled() ) ? $codes : [];
    } // End get_warning_status_codes()


    /**
     * Check if warnings are enabled
     *
     * @return boolean
     */
    public function are_warnings_enabled() {
        $has_updated_settings = get_option( 'blnotifier_has_updated_settings' );
        $enabled = get_option( 'blnotifier_enable_warnings' );
        if ( ( $has_updated_settings && $enabled ) || ( !$has_updated_settings ) ) {
            return true;
        } else {
            return false;
        }
    } // End are_warnings_enabled()


    /**
     * Get post types to include in settings
     *
     * @return array
     */
    public function get_post_types() {
        $post_types = get_post_types( [ 'show_ui' => true ], 'names' );
        unset( $post_types[ (new BLNOTIFIER_RESULTS)->post_type ] );
        if ( isset( $post_types[ 'help-docs' ] ) ) { unset( $post_types[ 'help-docs' ] ); }
        if ( isset( $post_types[ 'help-doc-imports' ] ) ) { unset( $post_types[ 'help-doc-imports' ] ); }
        return $post_types;
    } // End get_post_types()


    /**
     * Get the allowed Multi-Scan post types
     *
     * @return array
     */
    public function get_allowed_multiscan_post_types() {
        $allowed = get_option( 'blnotifier_post_types' );
        return !empty( $allowed ) ? array_keys( $allowed ) : [ 'post', 'page' ];
    } // End get_allowed_multiscan_post_types()


    /**
     * Get the omitted Multi-Scan post types
     *
     * @return array
     */
    public function get_omitted_multiscan_post_types() {
        $all = array_keys( $this->get_post_types() );
        $allowed = $this->get_allowed_multiscan_post_types();
        $omitted = array_diff( $all, $allowed );
        return filter_var_array( apply_filters( 'blnotifier_omitted_multiscan_post_types', $omitted ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_omitted_multiscan_post_types()


    /**
     * Get the omitted post types for page load scans
     * Same as those that are selected for the Multi-Scan, but allows for separate filtering
     *
     * @return array
     */
    public function get_omitted_pageload_post_types() {
        $post_types = $this->get_omitted_multiscan_post_types();
        return filter_var_array( apply_filters( 'blnotifier_omitted_pageload_post_types', $post_types ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_omitted_pageload_post_types()


    /**
     * Get query strings that we should remove on source url
     *
     * @return array
     */
    public function get_qs_to_remove_from_source() {
        $qs = [ 'blinks', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_term' ];
        return filter_var_array( apply_filters( 'blnotifier_remove_source_qs', $qs ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_qs_to_remove_from_source()


    /**
     * Get all the URL Schemes to ignore in the pre-check
     * Last updated: 3/7/24
     *
     * @return array
     */
    public function get_url_schemes() {
        // Official: https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml
        $official = [ 'aaa', 'aaas', 'about', 'acap', 'acct', 'acd', 'acr', 'adiumxtra', 'adt', 'afp', 'afs', 'aim', 'amss', 'android', 'appdata', 'apt', 'ar', 'ark', 'at', 'attachment', 'aw', 'barion', 'bb', 'beshare', 'bitcoin', 'bitcoincash', 'blob', 'bolo', 'brid', 'browserext', 'cabal', 'calculator', 'callto', 'cap', 'cast', 'casts', 'chrome', 'chrome-extension', 'cid', 'coap', 'coap+tcp', 'coap+ws', 'coaps', 'coaps+tcp', 'coaps+ws', 'com-eventbrite-attendee', 'content', 'content-type', 'crid', 'cstr', 'cvs', 'dab', 'dat', 'data', 'dav', 'dhttp', 'diaspora', 'dict', 'did', 'dis', 'dlna-playcontainer', 'dlna-playsingle', 'dns', 'dntp', 'doi', 'dpp', 'drm', 'drop', 'dtmi', 'dtn', 'dvb', 'dvx', 'dweb', 'ed2k', 'eid', 'elsi', 'embedded', 'ens', 'ethereum', 'example', 'facetime', 'fax', 'feed', 'feedready', 'fido', 'file', 'filesystem', 'finger', 'first-run-pen-experience', 'fish', 'fm', 'ftp', 'fuchsia-pkg', 'geo', 'gg', 'git', 'gitoid', 'gizmoproject', 'go', 'gopher', 'graph', 'grd', 'gtalk', 'h323', 'ham', 'hcap', 'hcp', 'hxxp', 'hxxps', 'hydrazone', 'hyper', 'iax', 'icap', 'icon', 'im', 'imap', 'info', 'iotdisco', 'ipfs', 'ipn', 'ipns', 'ipp', 'ipps', 'irc', 'irc6', 'ircs', 'iris', 'iris.beep', 'iris.lwz', 'iris.xpc', 'iris.xpcs', 'isostore', 'itms', 'jabber', 'jar', 'jms', 'keyparc', 'lastfm', 'lbry', 'ldap', 'ldaps', 'leaptofrogans', 'lid', 'lorawan', 'lpa', 'lvlt', 'machineProvisioningProgressReporter', 'magnet', 'mailserver', 'mailto', 'maps', 'market', 'matrix', 'message', 'microsoft.windows.camera', 'microsoft.windows.camera.multipicker', 'microsoft.windows.camera.picker', 'mid', 'mms', 'modem', 'mongodb', 'moz', 'ms-access', 'ms-appinstaller', 'ms-browser-extension', 'ms-calculator', 'ms-drive-to', 'ms-enrollment', 'ms-excel', 'ms-eyecontrolspeech', 'ms-gamebarservices', 'ms-gamingoverlay', 'ms-getoffice', 'ms-help', 'ms-infopath', 'ms-inputapp', 'ms-launchremotedesktop', 'ms-lockscreencomponent-config', 'ms-media-stream-id', 'ms-meetnow', 'ms-mixedrealitycapture', 'ms-mobileplans', 'ms-newsandinterests', 'ms-officeapp', 'ms-people', 'ms-project', 'ms-powerpoint', 'ms-publisher', 'ms-remotedesktop', 'ms-remotedesktop-launch', 'ms-restoretabcompanion', 'ms-screenclip', 'ms-screensketch', 'ms-search', 'ms-search-repair', 'ms-secondary-screen-controller', 'ms-secondary-screen-setup', 'ms-settings', 'ms-settings-airplanemode', 'ms-settings-bluetooth', 'ms-settings-camera', 'ms-settings-cellular', 'ms-settings-cloudstorage', 'ms-settings-connectabledevices', 'ms-settings-displays-topology', 'ms-settings-emailandaccounts', 'ms-settings-language', 'ms-settings-location', 'ms-settings-lock', 'ms-settings-nfctransactions', 'ms-settings-notifications', 'ms-settings-power', 'ms-settings-privacy', 'ms-settings-proximity', 'ms-settings-screenrotation', 'ms-settings-wifi', 'ms-settings-workplace', 'ms-spd', 'ms-stickers', 'ms-sttoverlay', 'ms-transit-to', 'ms-useractivityset', 'ms-virtualtouchpad', 'ms-visio', 'ms-walk-to', 'ms-whiteboard', 'ms-whiteboard-cmd', 'ms-word', 'msnim', 'msrp', 'msrps', 'mss', 'mt', 'mtqp', 'mumble', 'mupdate', 'mvn', 'mvrp', 'mvrps', 'news', 'nfs', 'ni', 'nih', 'nntp', 'notes', 'num', 'ocf', 'oid', 'onenote', 'onenote-cmd', 'opaquelocktoken', 'openid', 'openpgp4fpr', 'otpauth', 'p1', 'pack', 'palm', 'paparazzi', 'payment', 'payto', 'pkcs11', 'platform', 'pop', 'pres', 'prospero', 'proxy', 'pwid', 'psyc', 'pttp', 'qb', 'query', 'quic-transport', 'redis', 'rediss', 'reload', 'res', 'resource', 'rmi', 'rsync', 'rtmfp', 'rtmp', 'rtsp', 'rtsps', 'rtspu', 'sarif', 'secondlife', 'secret-token', 'service', 'session', 'sftp', 'sgn', 'shc', 'shttp', 'sieve', 'simpleledger', 'simplex', 'sip', 'sips', 'skype', 'smb', 'smp', 'sms', 'smtp', 'snews', 'snmp', 'soap.beep', 'soap.beeps', 'soldat', 'spiffe', 'spotify', 'ssb', 'ssh', 'starknet', 'steam', 'stun', 'stuns', 'submit', 'svn', 'swh', 'swid', 'swidpath', 'tag', 'taler', 'teamspeak', 'tel', 'teliaeid', 'telnet', 'tftp', 'things', 'thismessage', 'tip', 'tn3270', 'tool', 'turn', 'turns', 'tv', 'udp', 'unreal', 'upt', 'urn', 'ut2004', 'uuid-in-package', 'v-event', 'vemmi', 'ventrilo', 'ves', 'videotex', 'vnc', 'view-source', 'vscode', 'vscode-insiders', 'vsls', 'w3', 'wais', 'web3', 'wcr', 'webcal', 'web+ap', 'wifi', 'wpid', 'ws', 'wss', 'wtai', 'wyciwyg', 'xcon', 'xcon-userid', 'xfire', 'xmlrpc.beep', 'xmlrpc.beeps', 'xmpp', 'xftp', 'xrcp', 'xri', 'ymsgr' ];

        // Unofficial: https://en.wikipedia.org/wiki/List_of_URI_schemes
        $unofficial = [ 'admin', 'app', 'freeplane', 'javascript', 'jdbc', 'msteams', 'ms-spd', 'odbc', 'psns', 'rdar', 's3', 'trueconf', 'slack', 'stratum', 'viber', 'zoommtg', 'zoomus' ];

        // Return them
        $all_schemes = array_unique( array_merge( $official, $unofficial ) );
        return filter_var_array( apply_filters( 'blnotifier_url_schemes', $all_schemes ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_url_schemes()


    /**
     * Get the html link sources from the html
     *
     * @return array
     */
    public function get_html_link_sources() {
        $el = [ 
            'a'      => 'href',
            'iframe' => 'src',
            'video'  => 'src',
        ];
        $has_updated_settings = get_option( 'blnotifier_has_updated_settings' );
        $incl_images = get_option( 'blnotifier_include_images' );
        if ( ( $has_updated_settings && $incl_images ) || ( !$has_updated_settings ) ) {
            $el[ 'img' ] = 'src';
        }
        return filter_var_array( apply_filters( 'blnotifier_html_link_sources', $el ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_html_link_sources()


    /**
     * Determine if a URL should force the HEAD request method.
     *
     * @return array
     */
    public function get_force_head_file_types() {
        $file_types = [ 
            // Image formats
            'gif', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'bmp', 'tiff', 'ico', 'avif',

            // Video formats
            'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v',

            // Audio formats
            'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'
        ];

        $docs_use_head = filter_var( get_option( 'blnotifier_documents_use_head' ), FILTER_VALIDATE_BOOLEAN );
        if ( $docs_use_head ) {
            $doc_types = [ 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt', 'ods', 'epub', 'mobi' ];
            $file_types = array_merge( $file_types, $doc_types );
        }

        return apply_filters( 'blnotifier_force_head_file_types', $file_types, $docs_use_head );
    } // End get_force_head_file_types()


    /**
     * Strings to replace on the link
     *
     * @param string $link
     * @param boolean $reverse
     * @return string
     */
    public function str_replace_on_link( $link, $reverse = false ) {
        $strings_to_replace = [
            '×' => 'x'
        ];
        $strings_to_replace = filter_var_array( apply_filters( 'blnotifier_strings_to_replace', $strings_to_replace ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( !$reverse ) {
            foreach ( $strings_to_replace as $search => $replace ) {
                $link = str_replace( $search, $replace, $link );
            }
        } else {
            foreach ( $strings_to_replace as $search => $replace ) {
                $link = str_replace( $replace, $search, $link );
            }
        }
        return $link;
    } // End str_replace_on_link()


    /**
     * Get current URL with query string
     *
     * @param boolean $params
     * @param boolean $domain
     * @return string
     */
    public function get_current_url( $params = true, $domain = true ) {
        // Are we including the domain?
        if ( $domain == true ) {

            // Get the protocol
            $protocol = isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off' ? 'https' : 'http';

            // Get the domain
            $domain_without_protocol = sanitize_text_field( $_SERVER[ 'HTTP_HOST' ] );

            // Domain with protocol
            $domain = $protocol.'://'.$domain_without_protocol;

        } elseif ( $domain == 'only' ) {

            // Get the domain
            $domain = sanitize_text_field( $_SERVER[ 'HTTP_HOST' ] );
            return $domain;

        } else {
            $domain = '';
        }

        // Get the URI
        $uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );

        // Put it together
        $full_url = $domain.$uri;

        // Are we including query string params?
        if ( !$params ) {
            return strtok( $full_url, '?' );
            
        } else {
            return $full_url;
        }
    } // End get_current_url()


    /**
     * Get list of suggested broken link checkers
     *
     * @return array
     */
    public function get_suggested_offsite_checkers() {
        $links = [ 
            'Dead Link Checker' => 'https://www.deadlinkchecker.com/website-dead-link-checker.asp',
            'Dr Link Check'     => 'https://www.drlinkcheck.com/',
            'Sitechecker'       => 'https://sitechecker.pro/broken-links/',
        ];
        return filter_var_array( apply_filters( 'blnotifier_suggested_offsite_checkers', $links ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_suggested_offsite_checkers()


    /**
     * Count broken links in results
     *
     * @return void
     */
    public function count_broken_links() {
        $broken_links = get_posts( [
            'posts_per_page'    => -1,
            'post_status'       => 'publish',
            'post_type'         => 'blnotifier-results',
            'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'   => 'type',
                    'value' => 'broken',
                ]
            ],
            'fields' => 'ids'
        ] );
        return count( $broken_links );
    } // End count_broken_links()


    /**
     * Count number of posts by status
     *
     * @param string $post_status
     * @param string $post_type
     * @return int
     */
    public function count_posts_by_status( $post_status = 'publish', $post_type = 'post' ) {
        $count_posts = wp_count_posts( $post_type );
        if ( $count_posts ) {
            return $count_posts->$post_status;
        }
        return 0;
    } // End count_posts_by_status()


    /**
     * Time how long it takes to complete a function (in seconds)
     * $HELPERS = new BLNOTIFIER_HELPERS;
     * $start = $HELPERS->start_timer();
     *      run functions
     * $total_time = $HELPERS->stop_timer( $start );
     * $sec_per_link = round( ( $total_time / $count_links ), 2 );
     *
     * @param string $start_or_stop
     * @return int|bool
     */
    public function start_timer() {
        $time = microtime();
        $time = explode( ' ', $time );
        $time = $time[1] + $time[0];
        return $time;
    } // End start_timer()

    public function stop_timer( $start ) {
        $time = microtime();
        $time = explode( ' ', $time );
        $time = $time[1] + $time[0];
        $finish = $time;
        $total_time = round( ( $finish - $start ), 2 );
        return $total_time;
    } // End stop_timer()


    /**
     * Convert timezone
     * 
     * @param string $date
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function convert_timezone( $date = null, $format = 'F j, Y g:i A T', $timezone = null ) {
        // Get today as default
        if ( is_null( $date ) ) {
            $date = gmdate( 'Y-m-d H:i:s' );
        }

        // Get the date in UTC time
        $date = new DateTime( $date, new DateTimeZone( 'UTC' ) );

        // Get the timezone string
        if ( !is_null( $timezone ) ) {
            $timezone_string = $timezone;
        } else {
            $timezone_string = wp_timezone_string();
        }

        // Set the timezone to the new one
        $date->setTimezone( new DateTimeZone( $timezone_string ) );

        // Format it the way we way
        $new_date = $date->format( $format );

        // Return it
        return $new_date;
    } // End convert_timezone()


    /**
     * Include s if count is not 1
     *
     * @param int $count
     * @return string
     */
    public function include_s( $count ) {
        $s = $count == 1 ? '' : 's';
        return $s;
    } // End include_s()


    /**
     * Mark a select option as selected
     *
     * @param string $option
     * @param string $the_key
     * @return string
     */
    public function is_selected( $option, $value ) {
        return ( $option == $value ) ? ' selected' : '';
    } // End is_selected()


    /**
     * Add a WP Plugin Info Card
     *
     * @param string $slug
     * @return string
     */
    public function plugin_card( $slug ) {
        // Set the args
        $args = [ 
            'slug'                => $slug, 
            'fields'              => [
                'last_updated'    => true,
                'tested'          => true,
                'active_installs' => true
            ]
        ];
        
        // Fetch the plugin info from the wp repository
        $response = wp_remote_post(
            'http://api.wordpress.org/plugins/info/1.0/',
            [
                'body'        => [
                    'action'  => 'plugin_information',
                    'request' => serialize( (object)$args )
                ]
            ]
        );

        // If there is no error, continue
        if ( !is_wp_error( $response ) ) {

            // Unserialize
            $returned_object = unserialize( wp_remote_retrieve_body( $response ) );   
            if ( $returned_object ) {
                
                // Last Updated
                $last_updated = $returned_object->last_updated;
                $last_updated = $this->time_elapsed_string( $last_updated );

                // Compatibility
                $compatibility = $returned_object->tested;

                // Add incompatibility class
                global $wp_version;
                if ( $compatibility == $wp_version ) {
                    $is_compatible = '<span class="compatibility-compatible"><strong>Compatible</strong> with your version of WordPress</span>';
                } else {
                    $is_compatible = '<span class="compatibility-untested">Untested with your version of WordPress</span>';
                }

                // Get all the installed plugins
                $plugins = get_plugins();

                // Check if this plugin is installed
                $is_installed = false;
                foreach ( $plugins as $key => $plugin ) {
                    if ( $plugin[ 'TextDomain' ] == $slug ) {
                        $is_installed = $key;
                    }
                }

                // Check if it is also active
                $is_active = false;
                if ( $is_installed && is_plugin_active( $is_installed ) ) {
                    $is_active = true;
                }

                // Check if the plugin is already active
                if ( $is_active ) {
                    $install_link = 'role="link" aria-disabled="true"';
                    $php_notice = '';
                    $install_text = 'Active';

                // Check if the plugin is installed but not active
                } elseif ( $is_installed ) {
                    $install_link = 'href="'.admin_url( 'plugins.php' ).'"';
                    $php_notice = '';
                    $install_text = 'Go to Activate';

                // Check for php requirement
                } elseif ( phpversion() < $returned_object->requires_php ) {
                    $install_link = 'role="link" aria-disabled="true"';
                    $php_notice = '<div class="php-incompatible"><em><strong>Requires PHP Version '.$returned_object->requires_php.'</strong> — You are currently on Version '.phpversion().'</em></div>';
                    $install_text = 'Incompatible';

                // If we're good to go, add the link
                } else {

                    // Get the admin url for the plugin install page
                    if ( is_multisite() ) {
                        $admin_url = network_admin_url( 'plugin-install.php' );
                    } else {
                        $admin_url = admin_url( 'plugin-install.php' );
                    }

                    // Vars
                    $install_link = 'href="'.$admin_url.'?s='.esc_attr( $returned_object->name ).'&tab=search&type=term"';
                    $php_notice = '';
                    $install_text = 'Get Now';
                }
                
                // Short Description
                $pos = strpos( $returned_object->sections[ 'description' ], '.');
                $desc = substr( $returned_object->sections[ 'description' ], 0, $pos + 1 );

                // Rating
                $rating = $this->get_five_point_rating( 
                    $returned_object->ratings[1], 
                    $returned_object->ratings[2], 
                    $returned_object->ratings[3], 
                    $returned_object->ratings[4], 
                    $returned_object->ratings[5] 
                );

                // Link guts
                $link_guts = 'href="https://wordpress.org/plugins/'.esc_attr( $slug ).'/" target="_blank" aria-label="More information about '.$returned_object->name.' '.$returned_object->version.'" data-title="'.$returned_object->name.' '.$returned_object->version.'"';
                ?>
                <style>
                .plugin-card {
                    float: none !important;
                    margin-left: 0 !important;
                }
                .plugin-card .ws_stars {
                    display: inline-block;
                }
                .php-incompatible {
                    padding: 12px 20px;
                    background-color: #D1231B;
                    color: #FFFFFF;
                    border-top: 1px solid #dcdcde;
                    overflow: hidden;
                }
                #wpbody-content .plugin-card .plugin-action-buttons a.install-now[aria-disabled="true"] {
                    color: #CBB8AD !important;
                    border-color: #CBB8AD !important;
                }
                .plugin-action-buttons {
                    list-style: none !important;   
                }
                </style>
                <div class="plugin-card plugin-card-<?php echo esc_attr( $slug ); ?>">
                    <div class="plugin-card-top">
                        <div class="name column-name">
                            <h3>
                                <a <?php echo wp_kses_post( $link_guts ); ?>>
                                    <?php echo esc_html( $returned_object->name ); ?> 
                                    <img src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH ).esc_attr( $slug  ); ?>.png" class="plugin-icon" alt="<?php echo esc_html( $returned_object->name ); ?> Thumbnail">
                                </a>
                            </h3>
                        </div>
                        <div class="action-links">
                            <ul class="plugin-action-buttons">
                                <li><a class="install-now button" data-slug="<?php echo esc_attr( $slug ); ?>" <?php echo wp_kses_post( $install_link ); ?> aria-label="<?php echo esc_attr( $install_text );?>" data-name="<?php echo esc_html( $returned_object->name ); ?> <?php echo esc_html( $returned_object->version ); ?>"><?php echo esc_attr( $install_text );?></a></li>
                                <li><a <?php echo wp_kses_post( $link_guts ); ?>>More Details</a></li>
                            </ul>
                        </div>
                        <div class="desc column-description">
                            <p><?php echo wp_kses_post( $desc ); ?></p>
                            <p class="authors"> <cite>By <?php echo wp_kses_post( $returned_object->author ); ?></cite></p>
                        </div>
                    </div>
                    <div class="plugin-card-bottom">
                        <div class="vers column-rating">
                            <div class="star-rating"><span class="screen-reader-text"><?php echo esc_attr( abs( $rating ) ); ?> star rating based on <?php echo absint( $returned_object->num_ratings ); ?> ratings</span>
                                <?php echo wp_kses_post( $this->convert_to_stars( abs( $rating ) ) ); ?>
                            </div>					
                            <span class="num-ratings" aria-hidden="true">(<?php echo absint( $returned_object->num_ratings ); ?>)</span>
                        </div>
                        <div class="column-updated">
                            <strong>Last Updated:</strong> <?php echo esc_html( $last_updated ); ?>
                        </div>
                        <div class="column-downloaded" data-downloads="<?php echo esc_html( number_format( $returned_object->downloaded ) ); ?>">
                            <?php echo esc_html( number_format( $returned_object->active_installs ) ); ?>+ Active Installs
                        </div>
                        <div class="column-compatibility">
                            <?php echo wp_kses_post( $is_compatible ); ?>				
                        </div>
                    </div>
                    <?php echo wp_kses_post( $php_notice ); ?>
                </div>
                <?php
            }
        }
    } // End plugin_card()


    /**
     * Convert time to elapsed string
     *
     * @param [type] $datetime
     * @param boolean $full
     * @return string
     */
    public function time_elapsed_string( $datetime, $full = false ) {
        $now = new DateTime;
        $ago = new DateTime( $datetime );
        $diff = $now->diff( $ago );

        $diff->w = floor( $diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ( $string as $k => &$v ) {
            if ( $diff->$k ) {
                $v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
            } else {
                unset( $string[$k] );
            }
        }

        if ( !$full ) $string = array_slice( $string, 0, 1 );
        return $string ? implode( ', ', $string ) . ' ago' : 'just now';
    } // End time_elapsed_string()


    /**
     * Convert 5-point rating to plugin card stars
     *
     * @param int|float $r
     * @return string
     */
    public function convert_to_stars( $r ) {
        $f = '<div class="star star-full" aria-hidden="true"></div>';
        $h = '<div class="star star-half" aria-hidden="true"></div>';
        $e = '<div class="star star-empty" aria-hidden="true"></div>';
        
        $stars = $e.$e.$e.$e.$e;
        if ( $r > 4.74 ) {
            $stars = $f.$f.$f.$f.$f;
        } elseif ( $r > 4.24 && $r < 4.75 ) {
            $stars = $f.$f.$f.$f.$h;
        } elseif ( $r > 3.74 && $r < 4.25 ) {
            $stars = $f.$f.$f.$f.$e;
        } elseif ( $r > 3.24 && $r < 3.75 ) {
            $stars = $f.$f.$f.$h.$e;
        } elseif ( $r > 2.74 && $r < 3.25 ) {
            $stars = $f.$f.$f.$e.$e;
        } elseif ( $r > 2.24 && $r < 2.75 ) {
            $stars = $f.$f.$h.$e.$e;
        } elseif ( $r > 1.74 && $r < 2.25 ) {
            $stars = $f.$f.$e.$e.$e;
        } elseif ( $r > 1.24 && $r < 1.75 ) {
            $stars = $f.$h.$e.$e.$e;
        } elseif ( $r > 0.74 && $r < 1.25 ) {
            $stars = $f.$e.$e.$e.$e;
        } elseif ( $r > 0.24 && $r < 0.75 ) {
            $stars = $h.$e.$e.$e.$e;
        } else {
            $stars = $stars;
        }

        return '<div class="ws_stars">'.$stars.'</div>';
    } // End convert_to_stars()


    /**
     * Get 5-point rating from 5 values
     *
     * @param int|float $r1
     * @param int|float $r2
     * @param int|float $r3
     * @param int|float $r4
     * @param int|float $r5
     * @return float
     */
    public function get_five_point_rating ( $r1, $r2, $r3, $r4, $r5 ) {
        // Calculate them on a 5-point rating system
        $r5b = round( $r5 * 5, 0 );
        $r4b = round( $r4 * 4, 0 );
        $r3b = round( $r3 * 3, 0 );
        $r2b = round( $r2 * 2, 0 );
        $r1b = $r1;
        
        $total = round( $r1 + $r2 + $r3 + $r4 + $r5, 0 );
        if ( $total == 0 ) {
            $r = 0;
        } else {
            $r = round( ( $r1b + $r2b + $r3b + $r4b + $r5b ) / $total, 2 );
        }

        return $r;
    } // End get_five_point_rating()


    /**
     * Check if a link is on YouTube, if so return ID
     * Does not check if the video is valid
     *
     * @param string $link
     * @return boolean
     */
    public function is_youtube_link( $link ) {
        // The id
        $id = false;

        // Get the host
        $parse = wp_parse_url( $link );
        if ( isset( $parse[ 'host' ] ) && isset( $parse[ 'path' ] ) ) {
            $host = $parse[ 'host' ];
            $path = $parse[ 'path' ];

            // Make sure it's on youtube
            if ( $host && in_array( $host, [ 'youtube.com', 'www.youtube.com', 'youtu.be' ] ) ) {
                
                // '/embed/'
                if ( strpos( $path, '/embed/' ) !== false ) {
                    $id = str_replace( '/embed/', '', $path );
                    if ( strpos( $id, '&' ) !== false ) {
                        $id = substr( $id, 0, strpos( $id, '&' ) );
                    }

                // '/v/'
                } elseif ( strpos( $path, '/v/' ) !== false ) {
                    $id = str_replace( '/v/', '', $path );
                    if ( strpos( $id, '&' ) !== false ) {
                        $id = substr( $id, 0, strpos( $id, '&' ) );
                    }

                // '/watch'
                } elseif ( strpos( $path, '/watch' ) !== false && isset( $parse[ 'query' ] ) ) {
                    parse_str( $parse[ 'query' ], $queries );
                    if ( isset( $queries[ 'v' ] ) ) {
                        $id = $queries[ 'v' ];
                    }
                }
            }
        }

        // If id
        if ( $id ) {

            // Create a watch url
            return 'https://www.youtube.com/watch?v='.$id;
        }

        // We got nothin'
        return false;
    } // End is_youtube_link()


    /**
     * Check if the link is an X/Twitter link
     *
     * @param string $link
     * @return boolean
     */
    public function is_x_link( $link ) {
        if ( ! $link ) {
            return false;
        }
    
        $host = parse_url( $link, PHP_URL_HOST );
        $host = strtolower( preg_replace( '/^www\./', '', $host ) );
    
        $possible_links = [
            'x.com',
            'x.co',
            'twitter.com',
            'mobile.twitter.com',
            'm.twitter.com',
            't.co'
        ];

        return in_array( $host, $possible_links, true );
    } // End is_x_link()


    /**
     * Get the user agent
     *
     * @param string $link
     * @return string
     */
    public function get_user_agent( $link ) {
        // Default user agent
        $default_user_agent = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' );

        // Saved option
        $user_agent_option = sanitize_text_field( get_option( 'blnotifier_user_agent' ) );
        if ( $user_agent_option ) {
            $user_agent_option = str_replace( '{blog_version}', get_bloginfo( 'version' ), $user_agent_option );
            $user_agent_option = str_replace( '{blog_url}', get_bloginfo( 'url' ), $user_agent_option );
        }
        
        // Check if it's the default
        $is_default = ( $default_user_agent == $user_agent_option );
        
        // Twitter
        if ( ( !$user_agent_option || $is_default ) && $this->is_x_link( $link ) ) {
            return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        }

        // Return default
        return $default_user_agent;
    } // End get_user_agent()


    /**
     * Extract links from content
     *
     * @param [type] $content
     * @return array
     */
    public function extract_links( $content ) {
        // Array that will contain our extracted links.
        $matches = [];
    
        // Get html link sources
        $html_link_sources = $this->get_html_link_sources();
        if ( !empty( $html_link_sources ) ) {
    
            // Fetch the DOM once
            $htmlDom = new DOMDocument;
    
            // Specify the encoding with an XML declaration
            $utf8_content = '<?xml encoding="UTF-8">' . $content;
    
            // Suppress warnings and load the content with proper encoding
            @$htmlDom->loadHTML( $utf8_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    
            // Remove the XML encoding declaration node if present
            foreach ( $htmlDom->childNodes as $item ) {
                if ( $item->nodeType == XML_PI_NODE ) {
                    $htmlDom->removeChild( $item );
                }
            }
    
            // Look for each source
            foreach ( $html_link_sources as $tag => $html_link_source ) {
                $links = $htmlDom->getElementsByTagName( $tag );
    
                // Loop through the DOMNodeList.
                if ( !empty( $links ) ) {
                    foreach ( $links as $link ) {
    
                        // Get the link in the href attribute.
                        $linkHref = $this->sanitize_link( $link->getAttribute( $html_link_source ) );
    
                        // Add the link to our array.
                        $matches[] = $linkHref;
                    }
                }
            }
        }
    
        // Return
        return $matches;
    } // End extract_links()


    /**
     * Sanitize the link
     *
     * @param string $link
     * @return string
     */
    public function sanitize_link( $link ) {
        return htmlspecialchars( $link, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false );
    } // End sanitize_link()


    /**
     * Get all status codes
     *
     * @return array
     */
    public function get_status_codes() {
        // Possible Codes
        return [
            0   => [
                'msg'  => 'No Response',
                'desc' => 'The client did not receive any response from the server, often due to a connection issue.',
                'official' => false
            ],
            100 => [
                'msg'  => 'Continue',
                'desc' => 'This interim response indicates that the client should continue the request or ignore the response if the request is already finished.',
            ],
            103 => [
                'msg'  => 'Early Hints',
                'desc' => 'This status code is primarily intended to be used with the <code>Link</code> header, letting the user agent start preloading resources while the server prepares a response or preconnect to an origin from which the page will need resources.',
            ],
            200 => [
                'msg'  => 'OK',
                'desc' => 'The request succeeded. The result and meaning of "success" depends on the HTTP method. <code>GET</code>: The resource has been fetched and transmitted in the message body. <code>HEAD</code>: Representation headers are included in the response without any message body.',
            ],
            202 => [
                'msg'  => 'Accepted',
                'desc' => 'The request has been received but not yet acted upon. It is noncommittal, since there is no way in HTTP to later send an asynchronous response indicating the outcome of the request. It is intended for cases where another process or server handles the request, or for batch processing.',
            ],
            203 => [
                'msg'  => 'Non-Authoritative Information',
                'desc' => 'This response code means the returned metadata is not exactly the same as is available from the origin server, but is collected from a local or a third-party copy. This is mostly used for mirrors or backups of another resource. Except for that specific case, the <code>200 OK</code> response is preferred to this status.',
            ],
            204 => [
                'msg'  => 'No Content',
                'desc' => 'There is no content to send for this request, but the headers are useful. The user agent may update its cached headers for this resource with the new ones.',
            ],
            207 => [
                'msg'  => 'Multi-Status',
                'desc' => 'Conveys information about multiple resources, for situations where multiple status codes might be appropriate.',
            ],
            208 => [
                'msg'  => 'Already Reported',
                'desc' => 'Used inside a <code>' . htmlentities( '<dav:propstat>' ) . '</code> response element to avoid repeatedly enumerating the internal members of multiple bindings to the same collection.',
            ],
            218 => [
                'msg'  => 'This is fine',
                'desc' => 'Used by Apache Web Server.',
                'official' => false
            ],
            226 => [
                'msg'  => 'IM Used',
                'desc' => 'The server has fulfilled a <code>GET</code> request for the resource, and the response is a representation of the result of one or more instance-manipulations applied to the current instance.',
            ],
            300 => [
                'msg'  => 'Multiple Choices',
                'desc' => 'In agent-driven content negotiation, the request has more than one possible response and the user agent or user should choose one of them. There is no standardized way for clients to automatically choose one of the responses, so this is rarely used.',
            ],
            301 => [
                'msg'  => 'Redirected: Moved Permanently',
                'desc' => 'The URL of the requested resource has been changed permanently. The new URL is given in the response.',
            ],
            302 => [
                'msg'  => 'Redirected: Found',
                'desc' => 'This response code means that the URI of requested resource has been changed temporarily. Further changes in the URI might be made in the future, so the same URI should be used by the client in future requests.',
            ],
            303 => [
                'msg'  => 'See Other',
                'desc' => 'The server sent this response to direct the client to get the requested resource at another URI with a GET request.',
            ],
            304 => [
                'msg'  => 'Not Modified',
                'desc' => 'This is used for caching purposes. It tells the client that the response has not been modified, so the client can continue to use the same cached version of the response.',
            ],
            305 => [
                'msg'  => 'Use Proxy',
                'desc' => 'Defined in a previous version of the HTTP specification to indicate that a requested response must be accessed by a proxy. It has been deprecated due to security concerns regarding in-band configuration of a proxy.',
            ],
            306 => [
                'msg'  => 'Switch Proxy',
                'desc' => 'This response code is no longer used; but is reserved. It was used in a previous version of the HTTP/1.1 specification.',
            ],
            307 => [
                'msg'  => 'Temporary Redirect',
                'desc' => 'The server sends this response to direct the client to get the requested resource at another URI with the same method that was used in the prior request. This has the same semantics as the 302 Found response code, with the exception that the user agent must not change the HTTP method used: if a <code>GET</code> was used in the first request, a <code>GET</code> must be used in the redirected request.',
            ],
            308 => [
                'msg'  => 'Permanent Redirect',
                'desc' => 'This means that the resource is now permanently located at another URI, specified by the Location response header. This has the same semantics as the 301 Moved Permanently HTTP response code, with the exception that the user agent must not change the HTTP method used: if a <code>GET</code> was used in the first request, a <code>GET</code> must be used in the second request.',
            ],
            400 => [
                'msg'  => 'Bad Request',
                'desc' => 'The server cannot or will not process the request due to something that is perceived to be a client error (e.g., malformed request syntax, invalid request message framing, or deceptive request routing).',
            ],
            401 => [
                'msg'  => 'Unauthorized',
                'desc' => 'Although the HTTP standard specifies "unauthorized", semantically this response means "unauthenticated". That is, the client must authenticate itself to get the requested response.',
            ],
            402 => [
                'msg'  => 'Payment Required',
                'desc' => 'The initial purpose of this code was for digital payment systems, however this status code is rarely used and no standard convention exists.',
            ],
            403 => [
                'msg'  => 'Forbidden or Unsecure',
                'desc' => 'The client does not have access rights to the content; that is, it is unauthorized, so the server is refusing to give the requested resource. Unlike <code>401 Unauthorized</code>, the client\'s identity is known to the server.',
            ],
            404 => [
                'msg'  => 'Not Found',
                'desc' => 'The server cannot find the requested resource. In the browser, this means the URL is not recognized. In an API, this can also mean that the endpoint is valid but the resource itself does not exist. Servers may also send this response instead of <code>403 Forbidden</code> to hide the existence of a resource from an unauthorized client. This response code is probably the most well known due to its frequent occurrence on the web.',
            ],
            405 => [
                'msg'  => 'Method Not Allowed',
                'desc' => 'The request method is known by the server but is not supported by the target resource.',
            ],
            406 => [
                'msg'  => 'Not Acceptable',
                'desc' => 'This response is sent when the web server, after performing server-driven content negotiation, doesn\'t find any content that conforms to the criteria given by the user agent.',
            ],
            407 => [
                'msg'  => 'Proxy Authentication Required',
                'desc' => 'This is similar to <code>401 Unauthorized</code> but authentication is needed to be done by a proxy.',
            ],
            408 => [
                'msg'  => 'Request Timeout',
                'desc' => 'This response is sent on an idle connection by some servers, even without any previous request by the client. It means that the server would like to shut down this unused connection. This response is used much more since some browsers use HTTP pre-connection mechanisms to speed up browsing. Some servers may shut down a connection without sending this message.',
            ],
            409 => [
                'msg'  => 'Conflict',
                'desc' => 'This response is sent when a request conflicts with the current state of the server. In WebDAV remote web authoring, <code>409</code> responses are errors sent to the client so that a user might be able to resolve a conflict and resubmit the request.',
            ],
            410 => [
                'msg'  => 'Gone',
                'desc' => 'This response is sent when the requested content has been permanently deleted from server, with no forwarding address. Clients are expected to remove their caches and links to the resource. The HTTP specification intends this status code to be used for "limited-time, promotional services". APIs should not feel compelled to indicate resources that have been deleted with this status code.',
            ],
            411 => [
                'msg'  => 'Length Required',
                'desc' => 'Server rejected the request because the Content-Length header field is not defined and the server requires it.',
            ],
            412 => [
                'msg'  => 'Precondition Failed',
                'desc' => 'In conditional requests, the client has indicated preconditions in its headers which the server does not meet.',
            ],
            413 => [
                'msg'  => 'Payload Too Large',
                'desc' => 'The request body is larger than limits defined by server. The server might close the connection or return a Retry-After header field. This usually happens if the link is to a large file.',
            ],
            414 => [
                'msg'  => 'URI Too Long',
                'desc' => 'The URI requested by the client is longer than the server is willing to interpret.',
            ],
            415 => [
                'msg'  => 'Unsupported Media Type',
                'desc' => 'The media format of the requested data is not supported by the server, so the server is rejecting the request.',
            ],
            416 => [
                'msg'  => 'Range Not Satisfiable',
                'desc' => 'The ranges specified by the <copde>Range</copde> header field in the request cannot be fulfilled. It\'s possible that the range is outside the size of the target resource\'s data.',
            ],
            417 => [
                'msg'  => 'Expectation Failed',
                'desc' => 'The expectation indicated by the <code>Expect</code> request header field cannot be met by the server.',
            ],
            418 => [
                'msg'  => 'I\'m a Teapot',
                'desc' => 'The server refuses the attempt to brew coffee with a teapot.',
            ],
            421 => [
                'msg'  => 'Misdirected Request',
                'desc' => 'The request was directed at a server that is not able to produce a response. This can be sent by a server that is not configured to produce responses for the combination of scheme and authority that are included in the request URI.',
            ],
            422 => [
                'msg'  => 'Unprocessable Entity',
                'desc' => 'The request was well-formed but was unable to be followed due to semantic errors.',
            ],
            423 => [
                'msg'  => 'Locked',
                'desc' => 'The resource that is being accessed is locked.',
            ],
            424 => [
                'msg'  => 'Failed Dependency',
                'desc' => 'The request failed due to failure of a previous request.',
            ],
            425 => [
                'msg'  => 'Too Early',
                'desc' => 'Indicates that the server is unwilling to risk processing a request that might be replayed.',
            ],
            426 => [
                'msg'  => 'Upgrade Required',
                'desc' => 'The server refuses to perform the request using the current protocol but might be willing to do so after the client upgrades to a different protocol. The server sends an <code>Upgrade</code> header in a <code>426</code> response to indicate the required protocol(s).',
            ],
            429 => [
                'msg'  => 'Too Many Requests',
                'desc' => 'The user has sent too many requests in a given amount of time (rate limiting).',
            ],
            430 => [
                'msg'  => 'Request Header Fields Too Large',
                'desc' => 'Used by Shopify.',
                'official' => false
            ],
            431 => [
                'msg'  => 'Request Header Fields Too Large',
                'desc' => 'The server is unwilling to process the request because its header fields are too large. The request may be resubmitted after reducing the size of the request header fields.',
            ],
            440 => [
                'msg'  => 'Login Time-out',
                'desc' => 'Used by IIS.',
                'official' => false
            ],
            444 => [
                'msg'  => 'No Response',
                'desc' => 'Used by NGINX.',
                'official' => false
            ],
            450 => [
                'msg'  => 'Blocked by Windows Parental Controls',
                'desc' => 'Used by Microsoft.',
                'official' => false
            ],
            451 => [
                'msg'  => 'Unavailable For Legal Reasons',
                'desc' => 'The user agent requested a resource that cannot legally be provided, such as a web page censored by a government.',
            ],
            494 => [
                'msg'  => 'Request header too large',
                'desc' => 'Used by NGINX.',
                'official' => false
            ],
            495 => [
                'msg'  => 'SSL Certificate Error',
                'desc' => 'Used by NGINX.',
                'official' => false
            ],
            496 => [
                'msg'  => 'SSL Certificate Required',
                'desc' => 'Used by NGINX.',
                'official' => false
            ],
            497 => [
                'msg'  => 'HTTP Request Sent to HTTPS Port',
                'desc' => 'Used by NGINX.',
                'official' => false
            ],
            498 => [
                'msg'  => 'Invalid Token',
                'desc' => 'Used by Esri.',
                'official' => false
            ],
            499 => [
                'msg'  => 'Token Required',
                'desc' => 'Used by Esri.',
                'official' => false
            ],
            500 => [
                'msg'  => 'Internal Server Error',
                'desc' => 'The server has encountered a situation it does not know how to handle. This error is generic, indicating that the server cannot find a more appropriate <code>5XX</code> status code to respond with.',
            ],
            501 => [
                'msg'  => 'Not Implemented',
                'desc' => 'The request method is not supported by the server and cannot be handled. The only methods that servers are required to support (and therefore that must not return this code) are GET and HEAD.',
            ],
            502 => [
                'msg'  => 'Bad Gateway',
                'desc' => 'This error response means that the server, while working as a gateway to get a response needed to handle the request, got an invalid response.',
            ],
            503 => [
                'msg'  => 'Service Unavailable',
                'desc' => 'The server is not ready to handle the request. Common causes are a server that is down for maintenance or that is overloaded. Note that together with this response, a user-friendly page explaining the problem should be sent. This response should be used for temporary conditions and the <code>Retry-After</code> HTTP header should, if possible, contain the estimated time before the recovery of the service. The webmaster must also take care about the caching-related headers that are sent along with this response, as these temporary condition responses should usually not be cached.',
            ],
            504 => [
                'msg'  => 'Gateway Timeout',
                'desc' => 'This error response is given when the server is acting as a gateway and cannot get a response in time.',
            ],
            505 => [
                'msg'  => 'HTTP Version Not Supported',
                'desc' => 'The HTTP version used in the request is not supported by the server.',
            ],
            506 => [
                'msg'  => 'Variant Also Negotiates',
                'desc' => 'The server has an internal configuration error: during content negotiation, the chosen variant is configured to engage in content negotiation itself, which results in circular references when creating responses.',
            ],
            507 => [
                'msg'  => 'Insufficient Storage',
                'desc' => 'The method could not be performed on the resource because the server is unable to store the representation needed to successfully complete the request.',
            ],
            508 => [
                'msg'  => 'Loop Detected',
                'desc' => 'The server detected an infinite loop while processing the request.',
            ],
            510 => [
                'msg'  => 'Not Extended',
                'desc' => 'The client request declares an HTTP Extension (RFC 2774) that should be used to process the request, but the extension is not supported.',
            ],
            511 => [
                'msg'  => 'Network Authentication Required',
                'desc' => 'Indicates that the client needs to authenticate to gain network access.',
            ],
            520 => [
                'msg'  => 'Web Server Returned an Unknown Error',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            521 => [
                'msg'  => 'Web Server Is Down',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            522 => [
                'msg'  => 'Connection Timed Out',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            523 => [
                'msg'  => 'Origin Is Unreachable',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            524 => [
                'msg'  => 'A Timeout Occurred',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            525 => [
                'msg'  => 'SSL Handshake Failed',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            526 => [
                'msg'  => 'Invalid SSL Certificate',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            527 => [
                'msg'  => 'Railgun Error',
                'desc' => 'Used by Cloudflare.',
                'official' => false
            ],
            529 => [
                'msg'  => 'Site is Overloaded',
                'desc' => 'Used by Qualys in the SSLLabs.',
                'official' => false
            ],
            530 => [
                'msg'  => 'Site is Frozen',
                'desc' => 'Used by Pantheon web platform.',
                'official' => false
            ],
            598 => [
                'msg'  => 'Network Read Timeout Error',
                'desc' => 'Informal convention.',
                'official' => false
            ],
            666 => [
                'msg'  => 'Invalid URL or Could Not Resolve Host',
                'desc' => 'Used by Broken Link Notifier for when a valid URL was not provided or the server responds with <code>cURL error 6: Could not resolve host</code>.',
                'official' => false
            ],
            999 => [
                'msg'  => 'Scanning Not Permitted',
                'desc' => 'A non-standard code.',
                'official' => false
            ]
        ];        
    } // End get_status_codes()


    /**
     * Check a URL to see if it Exists
     *
     * @param string $url
     * @param integer|null $timeout
     * @return array
     */
    public function check_url_status_code( $url, $timeout = null ) {
        // Get timeout
        if ( is_null( $timeout ) ) {
            $timeout = absint( get_option( 'blnotifier_timeout', 5 ) );
        }

        // Get the 'allow_redirects' option and sanitize it
        $allow_redirects = filter_var( get_option( 'blnotifier_allow_redirects' ), FILTER_VALIDATE_BOOLEAN );

        // Determine the request method based on allow_redirects option
        $request_method = $allow_redirects ? 'GET' : 'HEAD';

        // Force giving head for images, videos, and audio files
        if ( $request_method == 'GET' ) {
            $file_extension = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
            if ( in_array( $file_extension, $this->get_force_head_file_types() ) ) {
                $request_method = 'HEAD';
            }
        }
        
        // User agent
        $user_agent = $this->get_user_agent( $url );

        // Add the home url
        if ( str_starts_with( $url, '/' ) ) {
            $link = home_url().$url;
        } else {
            $link = $url;
        }

        // Check if from youtube
        if ( $watch_url = $this->is_youtube_link( $link ) ) {
            $link = 'https://www.youtube.com/oembed?format=json&url='.$watch_url;
        }

        // The request args
        // See https://developer.wordpress.org/reference/classes/WP_Http/request/
        $http_request_args = apply_filters( 'blnotifier_http_request_args', [
            'method'      => $request_method,
            'timeout'     => $timeout,
            'redirection' => absint( get_option( 'blnotifier_max_redirects', 5 ) ),
            'httpversion' => '1.1',
            'sslverify'   => filter_var( get_option( 'blnotifier_ssl_verify', true ), FILTER_VALIDATE_BOOLEAN ),
            'user-agent'  => $user_agent
        ], $url );

        // Check the link
        $response = wp_remote_get( $link, $http_request_args );
        if ( !is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );    
            $error = 'Unknown';
        } else {
            $code = 0;
            $error = $response->get_error_message();
        }

        // Let's make invalid URL 0 codes broken
        if ( $code === 0 && ( $error == 'A valid URL was not provided.' || strpos( $error, 'cURL error 6: Could not resolve host' ) !== false ) ) {
            $code = 666;
        }

        // Possible Codes
        $codes = $this->get_status_codes();

        // Files too large
        if ( $request_method == 'GET' ) {
            $content_length = wp_remote_retrieve_header( $response, 'content-length' );
            if ( $content_length && $content_length > 10 * 1024 * 1024 ) { // 10 MB
                $code = 413;
            }
        }
        
        // Bad links
        if ( in_array( $code, $this->get_bad_status_codes() ) ) {
            $type = 'broken';

        // Warnings
        } elseif ( in_array( $code, $this->get_warning_status_codes() ) ) {
            $type = 'warning';

        // Good links
        } else {
            $type = 'good';
        }

        // Filter status
        $status = apply_filters( 'blnotifier_status', [
            'type' => $type,
            'code' => $code,
            'text' => ( $code !== 0 && ( isset( $codes[ $code ] ) && $codes[ $code ][ 'msg' ] != '' ) ) ? $codes[ $code ][ 'msg' ] : $error,
            'link' => $url
        ] );

        // Return the array
        return $status;
    } // End check_url_status_code

    
    /**
     * Check if a URL is broken or unsecure
     *
     * @param string $link
     * @return array
     */
    public function check_link( $link ) {
        // Filter the link
        $link = apply_filters( 'blnotifier_link_before_prechecks', $link );

        // String replace
        $link = $this->str_replace_on_link( $link );

        // Handle protocol-relative URLs (those starting with //)
        if ( !is_array( $link ) && str_starts_with( $link, '//' ) ) {

            // Get the current protocol (http or https)
            $protocol = isset( $_SERVER[ 'HTTPS' ] ) && sanitize_text_field( $_SERVER[ 'HTTPS' ] ) === 'on' ? 'https' : 'http';
            
            // Prepend the protocol to the link
            $link = $protocol . ':' . $link;
        }

        // Assuming the link is okay
        $status = [
            'type' => 'good',
            'code' => 200,
            'text' => 'OK',
            'link' => $link
        ];

        // Handle the filtered link if false
        if ( !$link ) {
            return [
                'type' => 'omitted',
                'code' => 200,
                'text' => 'No link found',
                'link' => 'Unknown'
            ];

        // Handle the filtered link if in-proper array
        } elseif ( is_array( $link ) && ( !isset( $link[ 'type' ] ) || !isset( $link[ 'code' ] ) || !isset( $link[ 'text' ] ) ) ) {
            $missing = [];
            if ( !isset( $link[ 'type' ] ) ) { $missing[] = 'type'; }
            if ( !isset( $link[ 'code' ] ) ) { $missing[] = 'code'; }
            if ( !isset( $link[ 'text' ] ) ) { $missing[] = 'text'; }

            return [
                'type' => 'broken',
                'code' => 0,
                'text' => 'Did not pass pre-check filter: missing ' . implode( ', ' . $missing ),
                'link' => $link
            ];
    
        // Return the filtered link as a status if proper array
        } elseif ( is_array( $link ) ) {
            return $link;

        // Skip null links
        } elseif ( $link && strlen( trim( $link ) ) == 0 ) {
            $status[ 'text' ] = 'Skipping null';
            return $status;
        
        // Skip if it is a hashtag / anchor link / query string
        } elseif ( $link[0] == '#' || $link[0] == '?' ) {
            $status[ 'text' ] = 'Skipping: starts with '.$link[0];
            return $status;
     
        // Skip if omitted
        } elseif ( (new BLNOTIFIER_OMITS)->is_omitted( $link, 'links' ) ) {
            $status[ 'text' ] = 'Omitted';
            $status[ 'type' ] = 'omitted';
            return $status;
        
        // If the link is blank
        } elseif ( $link == '' ) {
            $status = [
                'type' => 'broken',
                'code' => 0,
                'text' => 'Empty link',
                'link' => $link
            ];
            
        // If the match is local, easy check
        } elseif ( str_starts_with( $link, home_url() ) || ( str_starts_with( $link, '/' ) && !str_starts_with( $link, '//' ) ) ) {
           
            // Check locally first
            if ( !url_to_postid( $link ) ) {                

                // It may be redirected or an archive page, so let's check status anyway
                return $this->check_url_status_code( $link );
            }

        // Otherwise
        } else {

            // Skip url schemes
            foreach ( $this->get_url_schemes() as $scheme ) {
                if ( str_starts_with( $link, $scheme.':' ) ) {
                    $status[ 'text' ] = 'Skipping: Non-Http URL Schema';
                    return $status;
                }
            }

            // Return the status
            return $this->check_url_status_code( $link );
        }

        // Return the good status
        return $status;
    } // End check_link


    /**
     * Get the clean link regardless of status
     *
     * @param int|WP_Post $post
     * @return string
     */
    public function get_clean_permalink( $post ) {
        // Check if $post is a numeric ID and retrieve the post object if so
        if ( is_numeric( $post ) ) {
            $post = get_post( $post );
        }
    
        // Initialize permalink variable
        $permalink = '';
    
        if ( in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft' ] ) ) {
            // Clone the current post object to avoid modifying the global $post
            $my_post = clone $post;
        
            // Change the post status to 'publish' for URL generation
            $my_post->post_status = 'publish';
        
            // Sanitize the post name (slug) using the post title if it's empty
            $my_post->post_name = sanitize_title(
                $my_post->post_name ? $my_post->post_name : $my_post->post_title,
                $my_post->ID
            );
        
            // Get the permalink using the modified post object
            $permalink = get_permalink( $my_post );

        } else {
            // For published or other post statuses, get the permalink normally
            $permalink = get_permalink( $post );
        }
    
        return $permalink;
    } // End get_clean_permalink()    

}


/**
 * Add string comparison functions to earlier versions of PHP
 *
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
if ( version_compare( PHP_VERSION, 8.0, '<=' ) && !function_exists( 'str_starts_with' ) ) {
    function str_starts_with ( $haystack, $needle ) {
        return strpos( $haystack , $needle ) === 0;
    }
}
if ( version_compare( PHP_VERSION, 8.0, '<=' ) && !function_exists( 'str_ends_with' ) ) {
    function str_ends_with( $haystack, $needle ) {
        return $needle !== '' && substr( $haystack, -strlen( $needle ) ) === (string)$needle;
    }
} 
if ( version_compare( PHP_VERSION, 8.0, '<=' ) && !function_exists( 'str_contains' ) ) {
    function str_contains( $haystack, $needle ) {
        return $needle !== '' && mb_strpos( $haystack, $needle ) !== false;
    }
}