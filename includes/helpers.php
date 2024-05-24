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
     * Get the bad status codes we are using
     *
     * @return array
     */
    public function get_bad_status_codes() {
        $default_codes = [ 666, 308, 400, 404, 408 ];
        return filter_var_array( apply_filters( 'blnotifier_bad_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );
    } // End get_bad_status_codes()


    /**
     * Get the warning status codes we are using
     *
     * @return array
     */
    public function get_warning_status_codes() {
        $default_codes = [ 0 ];
        $default_codes = filter_var_array( apply_filters( 'blnotifier_warning_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );
        if ( $this->are_warnings_enabled() ) {
            return $default_codes;
        } else {
            return [];
        }
    } // End get_bad_status_codes()


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
            'iframe' => 'src'
        ];
        $has_updated_settings = get_option( 'blnotifier_has_updated_settings' );
        $incl_images = get_option( 'blnotifier_include_images' );
        if ( ( $has_updated_settings && $incl_images ) || ( !$has_updated_settings ) ) {
            $el[ 'img' ] = 'src';
        }
        return filter_var_array( apply_filters( 'blnotifier_html_link_sources', $el ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    } // End get_html_link_sources()


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
            'meta_query' => [
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
                            <div class="star-rating"><span class="screen-reader-text"><?php echo abs( $rating ); ?> rating based on <?php echo absint( $returned_object->num_ratings ); ?> ratings</span>
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
        $parse = parse_url( $link );
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
            @$htmlDom->loadHTML( $content );

            // Look for each source
            foreach ( $html_link_sources as $tag => $html_link_source ) {
                $links = $htmlDom->getElementsByTagName( $tag );

                // Loop through the DOMNodeList.
                if ( !empty( $links ) ) {
                    foreach ( $links as $link ) {

                        // Get the link in the href attribute.
                        $linkHref = filter_var( $link->getAttribute( $html_link_source ), FILTER_SANITIZE_URL );

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
     * Check a URL to see if it Exists
     *
     * @param string $url
     * @param integer|null $timeout
     * @return array
     */
    public function check_url_status_code( $url, $timeout = null ) {
        // Get timeout
        if ( is_null( $timeout ) ) {
            $timeout = get_option( 'blnotifier_timeout', 5 );
        }

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
            'method'      => 'HEAD',
            'timeout'     => $timeout, // How long the connection should stay open in seconds. Default 5.
            'redirection' => 5,        // Number of allowed redirects. Not supported by all transports. Default 5.
            'httpversion' => '1.1',    // Version of the HTTP protocol to use. Accepts '1.0' and '1.1'. Default '1.0'.
            'sslverify'   => get_option( 'blnotifier_ssl_verify', true )
        ], $url );

        // Check the link
        $response = wp_safe_remote_get( $link, $http_request_args );
        if ( !is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );    
            $error = 'Unknown';
        } else {
            $code = 0;
            $error = $response->get_error_message();
        }

        // Let's make invalid URL 0 codes broken
        if ( $code === 0 && $error == 'A valid URL was not provided.' ) {
            $code = 666;
        }

        // Possible Codes
        $codes = [
            0 => $error,
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing', // WebDAV; RFC 2518
            103 => 'Early Hints', // RFC 8297
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information', // since HTTP/1.1
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content', // RFC 7233
            207 => 'Multi-Status', // WebDAV; RFC 4918
            208 => 'Already Reported', // WebDAV; RFC 5842
            226 => 'IM Used', // RFC 3229
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found', // Previously "Moved temporarily"
            303 => 'See Other', // since HTTP/1.1
            304 => 'Not Modified', // RFC 7232
            305 => 'Use Proxy', // since HTTP/1.1
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect', // since HTTP/1.1
            308 => 'Permanent Redirect', // RFC 7538
            400 => 'Bad Request',
            401 => 'Unauthorized', // RFC 7235
            402 => 'Payment Required',
            403 => 'Forbidden or Unsecure',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required', // RFC 7235
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed', // RFC 7232
            413 => 'Payload Too Large', // RFC 7231
            414 => 'URI Too Long', // RFC 7231
            415 => 'Unsupported Media Type', // RFC 7231
            416 => 'Range Not Satisfiable', // RFC 7233
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot', // RFC 2324, RFC 7168
            421 => 'Misdirected Request', // RFC 7540
            422 => 'Unprocessable Entity', // WebDAV; RFC 4918
            423 => 'Locked', // WebDAV; RFC 4918
            424 => 'Failed Dependency', // WebDAV; RFC 4918
            425 => 'Too Early', // RFC 8470
            426 => 'Upgrade Required',
            428 => 'Precondition Required', // RFC 6585
            429 => 'Too Many Requests', // RFC 6585
            431 => 'Request Header Fields Too Large', // RFC 6585
            451 => 'Unavailable For Legal Reasons', // RFC 7725
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates', // RFC 2295
            507 => 'Insufficient Storage', // WebDAV; RFC 4918
            508 => 'Loop Detected', // WebDAV; RFC 5842
            510 => 'Not Extended', // RFC 2774
            511 => 'Network Authentication Required', // RFC 6585
            
            // Unofficial codes
            103 => 'Checkpoint',
            218 => 'This is fine', // Apache Web Server
            419 => 'Page Expired', // Laravel Framework
            420 => 'Method Failure', // Spring Framework
            420 => 'Enhance Your Calm', // Twitter
            430 => 'Request Header Fields Too Large', // Shopify
            450 => 'Blocked by Windows Parental Controls', // Microsoft
            498 => 'Invalid Token', // Esri
            499 => 'Token Required', // Esri
            509 => 'Bandwidth Limit Exceeded', // Apache Web Server/cPanel
            526 => 'Invalid SSL Certificate', // Cloudflare and Cloud Foundry's gorouter
            529 => 'Site is overloaded', // Qualys in the SSLLabs
            530 => 'Site is frozen', // Pantheon web platform
            598 => 'Network read timeout error', // Informal convention
            440 => 'Login Time-out', // IIS
            449 => 'Retry With', // IIS
            451 => 'Redirect', // IIS
            444 => 'No Response', // nginx
            494 => 'Request header too large', // nginx
            495 => 'SSL Certificate Error', // nginx
            496 => 'SSL Certificate Required', // nginx
            497 => 'HTTP Request Sent to HTTPS Port', // nginx
            499 => 'Client Closed Request', // nginx
            520 => 'Web Server Returned an Unknown Error', // Cloudflare
            521 => 'Web Server Is Down', // Cloudflare
            522 => 'Connection Timed Out', // Cloudflare
            523 => 'Origin Is Unreachable', // Cloudflare
            524 => 'A Timeout Occurred', // Cloudflare
            525 => 'SSL Handshake Failed', // Cloudflare
            526 => 'Invalid SSL Certificate', // Cloudflare
            527 => 'Railgun Error', // Cloudflare
            666 => $error, // Our own error converted from 0
            999 => 'Scanning Not Permitted' // Non-standard code
        ];

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
            'text' => isset( $codes[ $code ] ) ? $codes[ $code ] : $error,
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
                'type' => 'broken',
                'code' => 0,
                'text' => 'Did not pass pre-check filter',
                'link' => $link
            ];

        // Handle the filtered link if in-proper array
        } elseif ( is_array( $link ) && ( !isset( $link[ 'type' ] ) || !isset( $link[ 'code' ] ) || !isset( $link[ 'text' ] ) ) ) {
            return [
                'type' => 'broken',
                'code' => 0,
                'text' => 'Did not pass pre-check filter',
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
        } elseif ( str_starts_with( $link, home_url() ) || str_starts_with( $link, '/' ) ) {
           
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