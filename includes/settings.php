<?php
/**
 * Settings page class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Initiate the class
 */
add_action( 'init', function() {
    (new BLNOTIFIER_SETTINGS)->init();
} );


/**
 * Main plugin class.
 */
class BLNOTIFIER_SETTINGS {

    /**
     * The settings page slug (matches the option group used by register_setting)
     *
     * @var string
     */
    public $page_slug = 'blnotifier-settings';


    /**
     * Load on init
     */
    public function init() {

        // Settings page fields
        add_action( 'admin_init', [ $this, 'settings_fields' ] );

        // Subheader hooks
        add_action( 'blnotifier_subheader_left', [ $this, 'render_settings_subheader_left' ] );
        add_action( 'blnotifier_subheader_right', [ $this, 'render_settings_subheader_right' ] );
        add_action( 'blnotifier_subheader_right', [ $this, 'render_version' ], 5 );

        // Enqueue script
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // AJAX
        add_action( 'wp_ajax_blnotifier_clear_cache', [ $this, 'ajax_clear_cache' ] );
        add_action( 'wp_ajax_blnotifier_test_notification', [ $this, 'ajax_test_notification' ] );
        add_action( 'wp_ajax_blnotifier_save_settings', [ $this, 'ajax_save_settings' ] );

    } // End init()


    /**
     * Get settings box labels
     *
     * @return array
     */
    public function get_settings_box_labels() {
        return [
            'scanning'      => __( 'Scanning Behavior', 'broken-link-notifier' ),
            'advanced'      => __( 'Advanced', 'broken-link-notifier' ),
            'notifications' => __( 'Notification Methods', 'broken-link-notifier' ),
            'access'        => __( 'Access Control', 'broken-link-notifier' ),
            'status_codes'  => __( 'Status Codes', 'broken-link-notifier' ),
        ];
    } // End get_settings_box_labels()


    /**
     * Update user roles based on the currently saved blnotifier_editable_roles option
     *
     * @return void
     */
    public function update_roles() {
        $allowed_roles = get_option( 'blnotifier_editable_roles', [] );
        if ( is_array( $allowed_roles ) && !empty( $allowed_roles ) ) {
            $saniotized_allowed_roles = [];
            foreach ( $allowed_roles as $role_slug => $value ) {
                $saniotized_allowed_roles[] = sanitize_key( $role_slug );
            }
            $allowed_roles = $saniotized_allowed_roles;
        }

        $editable_roles = $this->get_editable_roles_choices();
        if ( !empty( $editable_roles ) && is_array( $editable_roles ) ) {

            foreach ( $editable_roles as $editable_role_slug => $label ) {
                $role = get_role( $editable_role_slug );
                if ( !$role ) {
                    continue;
                }

                if ( in_array( $editable_role_slug, $allowed_roles ) && ! $role->has_cap( 'manage_broken_links' ) ) {
                    $role->add_cap( 'manage_broken_links' );
                } elseif ( $role->has_cap( 'manage_broken_links' ) ) {
                    $role->remove_cap( 'manage_broken_links' );
                }
            }
        }
    } // End update_roles()


    /**
     * Register the settings fields for the plugin.
     */
    public function settings_fields() {
        // Add section
        add_settings_section( 
            'general',
            'Settings',
            '',
            $this->page_slug
        );

        // Has updated settings (internal flag, not user-facing)
        register_setting( $this->page_slug, 'blnotifier_has_updated_settings', [ $this, 'sanitize_boolean' ] );

        // The master field list, in display order. Reorder entries here to reorder the settings page.
        $fields = [

            // Scanning Behavior
            [
                'type'     => 'checkbox',
                'name'     => 'pause_frontend_scanning',
                'label'    => 'Pause Front-End Scanning',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'You can pause front-end scanning if you just want to scan manually; disabling this means you will NOT get notified when someone visits a page with broken links'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'remote_fetch_links',
                'label'    => 'Also Fetch Pages Remotely During Page Scan, Site Scan & Link Browser',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'Only applies to Page Scan, Site Scan, and Link Browser (not front-end scanning, which already reads the live page as visitors see it). In addition to reading a page\'s stored content, also fetches the live published URL and scans its HTML for links. This catches links rendered by shortcodes or templates that depend on live page context (common with listing/directory themes), at the cost of a slower scan since it makes a real web request per page. Only applies to published pages.'
            ],
            [
                'type'     => 'number',
                'name'     => 'max_links_per_page',
                'label'    => 'Max Links Per Page',
                'default'  => 200,
                'min'      => 0,
                'box'      => 'scanning',
                'comments' => 'Maximum number of links to check per page (0 for unlimited) - this is to prevent attacks and timeouts on pages with a large number of links'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_warnings',
                'label'    => 'Enable Warnings',
                'default'  => true,
                'box'      => 'scanning',
                'comments' => 'Includes warnings in all scans'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_good_links',
                'label'    => 'Show Good Links in Results',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'Includes good links on results page for verification purposes only (more performance heavy — click Verify Link Statuses to check and clear them)'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_delete_source',
                'label'    => 'Enable Delete Source Action Link',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'An action link will appear on the Results tab under the source where you can trash the page entirely'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'include_images',
                'label'    => 'Check for Broken Images',
                'default'  => true,
                'box'      => 'scanning',
                'comments' => 'Includes image src links in all scans'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'ssl_verify',
                'label'    => 'Warn if SSL is Not Verified',
                'default'  => true,
                'box'      => 'scanning',
                'comments' => 'If you are not concerned about insecure links, you can disable this'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'scan_header',
                'label'    => 'Scan <code>&#x3c;header&#x3e;</code> Elements',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'Only applies to page load scans - the header elements usually include the navigation menu(s) at the top of the page'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'scan_footer',
                'label'    => 'Scan <code>&#x3c;footer&#x3e;</code> Elements',
                'default'  => false,
                'box'      => 'scanning',
                'comments' => 'Only applies to page load scans - the footer elements include any links at the bottom of every page'
            ],
            [
                'type'     => 'checkboxes',
                'name'     => 'post_types',
                'label'    => 'Enable Scanning for These Post Types',
                'options'  => $this->get_post_type_choices(),
                'default'  => [ 'post', 'page' ],
                'box'      => 'scanning',
                'comments' => 'Controls which post types are included in Site Scan\'s link discovery, front-end page-load scanning, and (if enabled) legacy Multi-Scan.'
            ],

            // Advanced
            [
                'type'     => 'text',
                'name'     => 'user_agent',
                'label'    => 'User Agent',
                'default'  => 'WordPress/{blog_version}; {blog_url}',
                'box'      => 'advanced',
                'comments' => 'Only change this if you know what you are doing. Default is "WordPress/{blog_version}; {blog_url}" (WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . ')'
            ],
            [
                'type'     => 'number',
                'name'     => 'timeout',
                'label'    => 'Timeout (seconds)',
                'default'  => 5,
                'min'      => 5,
                'box'      => 'advanced',
                'comments' => 'How long to try to connect to a link\'s server before quitting'
            ],
            [
                'type'     => 'number',
                'name'     => 'max_redirects',
                'label'    => 'Max Redirects',
                'default'  => 5,
                'min'      => 0,
                'box'      => 'advanced',
                'comments' => 'Maximum number of redirects before giving up on a link (will only be used if you allow redirects below)'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'allow_redirects',
                'label'    => 'Allow Redirects',
                'default'  => true,
                'box'      => 'advanced',
                'comments' => 'Changes the method of checking for broken links from <code>HEAD</code> to <code>GET</code>. May cause issues linking to larger documents.'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'documents_use_head',
                'label'    => 'Force Documents to Use <code>HEAD</code> Requests',
                'default'  => false,
                'box'      => 'advanced',
                'comments' => 'If you have enabled allowing redirects (above), by default images, videos, and audio files force the use of <code>HEAD</code> requests rather than <code>GET</code>. Some servers automatically block <code>HEAD</code> requests for documents, so we don\'t force them by default. If you are having issues with large documents not completing a scan, then you can try enabling this option to see if it helps. If they are blocked, at least you will know why.'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'show_in_console',
                'label'    => 'Show Results in Dev Console',
                'default'  => false,
                'box'      => 'advanced',
                'comments' => 'Only applies to page load scans. Note that this is visible to anyone viewing your site\'s browser console, not just admins, so it\'s best left off unless you\'re actively troubleshooting or support has asked you to turn it on to help diagnose an issue.'
            ],
            [
                'type'     => 'number',
                'name'     => 'cache',
                'label'    => 'Length of Time to Cache Good Links (in Seconds)',
                'default'  => 0,
                'min'      => 0,
                'box'      => 'advanced',
                'comments' => 'Use 0 to disable caching. If you are experienced performance issues, you can set the value to 28800 (8 hours), 43200 (12 hours), 86400 (24 hours) or whatever you feel is best. Broken and warning links will never be cached. Deactivating or uninstalling the plugin will clear the cache completely.'
            ],
            [
                'type'  => 'clear_cache',
                'name'  => 'clear_cache_tools',
                'label' => 'Cache',
                'box'   => 'advanced',
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'uninstall_cleanup',
                'label'    => 'Remove Data on Uninstall',
                'default'  => false,
                'box'      => 'advanced',
                'comments' => 'Enable this option to automatically remove the database tables that the links are stored in and all options when the plugin is uninstalled.'
            ],
            [
                'type'  => 'html',
                'name'  => 'settings_backup_tools',
                'label' => 'Backup & Reset',
                'box'   => 'advanced',
            ],

            // Notification Methods
            [
                'type'     => 'checkbox',
                'name'     => 'enable_emailing',
                'label'    => 'Enable Emailing',
                'default'  => true,
                'box'      => 'notifications',
                'comments' => 'You can turn off email notifications and still get website notifications'
            ],
            [
                'type'     => 'emails_with_test',
                'name'     => 'emails',
                'label'    => 'Emails to Send Notifications',
                'default'  => get_bloginfo( 'admin_email' ),
                'box'      => 'notifications',
                'toggle'   => 'blnotifier_enable_emailing',
                'comments' => 'Separated by commas'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_discord',
                'label'    => 'Enable Discord Notifications',
                'default'  => false,
                'box'      => 'notifications',
                'comments' => 'You can also send notifications to a Discord channel'
            ],
            [
                'type'               => 'url_with_test',
                'name'               => 'discord',
                'label'              => 'Discord Webhook URL',
                'default'            => '',
                'box'                => 'notifications',
                'toggle'             => 'blnotifier_enable_discord',
                'test_type'          => 'discord',
                'comments'           => 'URL should look like this: https://discord.com/api/webhooks/xxx/xxx...',
                'accordion_title'    => 'How to Connect to Discord',
                'accordion_content'  => '<p>Using Discord to receive notifications is easy to set up, and often a more reliable method since emails can end up getting lost in cyberspace sometimes. The instructions below assume you already have a Discord account.</p>
        <p><strong>Set Up:</strong></p>
        <ol>
        <li><a href="https://support.discord.com/hc/en-us/articles/204849977-How-do-I-create-a-server" target="_blank">Create a server</a> if you don\'t already have one (it\'s free and easy)</li>
        <li>Go to Server Settings > Integrations</li>
        <li>Click on Webhooks</li>
        <li>Click on "New Webhook" (once)</li>
        <li>Scroll down and click on your new webhook (probably named "Captain Hook")</li>
        <li>Name your webhook (this will be used as the name that the messages are posted by)</li>
        <li>Choose the channel the messages should be posted in</li>
        <li>Click on "Copy Webhook URL"; it will save to your clipboard</li>
        <li>Add the webhook url above and enable Discord notifications</li>
        <li>Enable "Show Results in Dev Console" so you can verify scanning results are being picked up</li>
        <li>Visit a page that you know has new broken links (if the broken links are added to your <a href="'.esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ).'">Results</a> page, then you will need to delete them before testing again since it will only show the results once)</li>
        </ol>'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_slack',
                'label'    => 'Enable Slack Notifications',
                'default'  => false,
                'box'      => 'notifications',
                'comments' => 'You can also send notifications to a Slack channel'
            ],
            [
                'type'               => 'url_with_test',
                'name'               => 'slack',
                'label'              => 'Slack Webhook URL',
                'default'            => '',
                'box'                => 'notifications',
                'toggle'             => 'blnotifier_enable_slack',
                'test_type'          => 'slack',
                'comments'           => 'URL should look like this: https://hooks.slack.com/services/xxx/xxx/xxx',
                'accordion_title'    => 'How to Connect to Slack',
                'accordion_content'  => '<p>Using Slack to receive notifications is straightforward. The instructions below assume you already have a Slack account and workspace.</p>
        <p><strong>Set Up:</strong></p>
        <ol>
        <li>Go to <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a></li>
        <li>Click Create New App</li>
        <li>Choose From scratch</li>
        <li>Enter an app name (e.g. "Broken Link Notifier") and select your workspace</li>
        <li>Click Create App</li>
        <li>In the left sidebar click Incoming Webhooks</li>
        <li>Toggle Activate Incoming Webhooks to on</li>
        <li>Click Add New Webhook to Workspace at the bottom</li>
        <li>Select the channel you want notifications posted to</li>
        <li>Click Allow</li>
        <li>Copy the webhook URL</li>
        <li>Add the webhook url above and enable Slack notifications</li>
        <li>Enable "Show Results in Dev Console" so you can verify scanning results are being picked up</li>
        <li>Visit a page that you know has new broken links (if the broken links are added to your <a href="'.esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ).'">Results</a> page, then you will need to delete them before testing again since it will only show the results once)</li>
        </ol>'
            ],
            [
                'type'     => 'checkbox',
                'name'     => 'enable_msteams',
                'label'    => 'Enable Microsoft Teams Notifications',
                'default'  => false,
                'box'      => 'notifications',
                'comments' => 'You can also send notifications to a Microsoft Teams channel'
            ],
            [
                'type'               => 'url_with_test',
                'name'               => 'msteams',
                'label'              => 'Microsoft Teams Webhook URL',
                'default'            => '',
                'box'                => 'notifications',
                'toggle'             => 'blnotifier_enable_msteams',
                'test_type'          => 'msteams',
                'comments'           => 'URL should look like this: https://yourdomain.webhook.office.com/xxx/xxx...',
                'accordion_title'    => 'How to Connect to Microsoft Teams',
                'accordion_content'  => '<p>Using Microsoft Teams to receive notifications is easy to set up, too, and it\'s helpful for teams to work together on fixing the links. The instructions below assume you already have a Microsoft account and Teams installed.</p>
        <p><strong>Set Up:</strong></p>
        <ol>
        <li>Go to Apps</li>
        <li>Search for Incoming Webhook</li>
        <li>Click on the Incoming Webhook app</li>
        <li>Click on "Add to a team"</li>
        <li>Choose a channel to add the messages to</li>
        <li>Click on "Set up connector"</li>
        <li>Name your webhook (this will be used as the name that the messages are posted by)</li>
        <li>Upload a logo for your webhook</li>
        <li>Click on "Create"</li>
        <li>Copy the webhook URL and save it (you cannot retrieve it again)</li>
        <li>Click on "Done"</li>
        <li>Add the webhook url above and enable Microsoft Teams notifications</li>
        <li>Enable "Show Results in Dev Console" so you can verify scanning results are being picked up</li>
        <li>Visit a page that you know has new broken links (if the broken links are added to your <a href="'.esc_url( (new BLNOTIFIER_MENU)->get_plugin_page( 'results' ) ).'">Results</a> page, then you will need to delete them before testing again since it will only show the results once)</li>
        </ol>'
            ],

            // Access Control
            [
                'type'     => 'checkbox',
                'name'     => 'enable_rest_api',
                'label'    => 'Enable REST API',
                'default'  => false,
                'box'      => 'access',
                'comments' => 'Exposes a read/delete REST API endpoint for use with AI agents and external tools.'
            ],
            [
                'type' => 'api_key',
                'name' => 'api_key',
                'label' => 'API Key',
                'box'  => 'access',
            ],
            [
                'type'     => 'checkboxes',
                'name'     => 'editable_roles',
                'label'    => 'Allow These Additional Roles to Manage Broken Links',
                'options'  => $this->get_editable_roles_choices(),
                'box'      => 'access',
            ],

            // Status Codes
            [
                'type'    => 'status_codes',
                'name'    => 'status_codes',
                'label'   => 'Status Codes',
                'options' => (new BLNOTIFIER_HELPERS)->get_status_codes(),
                'box'     => 'status_codes',
            ],
        ];

        // Map each field type to its sanitize callback and render callback
        $type_map = [
            'checkbox'          => [ 'sanitize' => [ $this, 'sanitize_checkbox' ],   'render' => [ $this, 'field_checkbox' ] ],
            'checkboxes'        => [ 'sanitize' => [ $this, 'sanitize_checkboxes' ], 'render' => [ $this, 'field_checkboxes' ] ],
            'number'            => [ 'sanitize' => 'absint',                         'render' => [ $this, 'field_number' ] ],
            'text'              => [ 'sanitize' => 'sanitize_text_field',            'render' => [ $this, 'field_text' ] ],
            'url_with_test'     => [ 'sanitize' => [ $this, 'sanitize_url' ],        'render' => [ $this, 'field_url_with_test' ] ],
            'emails_with_test'  => [ 'sanitize' => 'sanitize_text_field',            'render' => [ $this, 'field_emails_with_test' ] ],
            'status_codes'      => [ 'sanitize' => [],                               'render' => [ $this, 'field_status_codes' ] ],
            'api_key'           => [ 'sanitize' => 'sanitize_text_field',            'render' => [ $this, 'field_api_key' ] ],
            'html'              => [ 'sanitize' => null,                             'render' => [ $this, 'field_backup_tools' ] ],
            'clear_cache'       => [ 'sanitize' => null,                             'render' => [ $this, 'field_clear_cache' ] ],
        ];

        // Register and add each field in order
        foreach ( $fields as $field ) {
            $option_name = 'blnotifier_' . $field[ 'name' ];
            $type_info = $type_map[ $field[ 'type' ] ];

            if ( $type_info[ 'sanitize' ] !== null ) {
                register_setting( $this->page_slug, $option_name, $type_info[ 'sanitize' ] );
            }

            $args = $field;
            $args[ 'class' ] = $option_name;
            $args[ 'name' ]  = $option_name;
            unset( $args[ 'type' ], $args[ 'label' ], $args[ 'box' ] );
            $args[ 'box' ] = $field[ 'box' ];

            add_settings_field(
                $option_name,
                $field[ 'label' ],
                $type_info[ 'render' ],
                $this->page_slug,
                'general',
                $args
            );
        }
    } // End settings_fields()


    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function field_text( $args ) {
        printf(
            '<input type="text" id="%s" name="%s" value="%s"/>',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) )
        );
    } // End field_text()


    /**
     * Custom callback function to print url field
     *
     * @param array $args
     * @return void
     */
    public function field_url( $args ) {
        printf(
            '<input type="url" id="%s" name="%s" value="%s"/>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_url( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
        );
    } // End field_url()


    /**
     * Render a URL field with an optional test notification button
     *
     * @param array $args
     * @return void
     */
    public function field_url_with_test( $args ) {
        $value = esc_url( get_option( $args[ 'name' ], '' ) );
        $has_value = !empty( $value );
        $toggle = isset( $args[ 'toggle' ] ) ? ' data-toggle="'.esc_attr( $args[ 'toggle' ] ).'"' : '';
        ?>
        <div class="blnotifier-notification-field"<?php echo $toggle; ?>>
            <input type="url" id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
            <button type="button" class="blnotifier-button blnotifier-test-btn" data-type="<?php echo esc_attr( $args[ 'test_type' ] ); ?>" data-field="<?php echo esc_attr( $args[ 'name' ] ); ?>" <?php echo !$has_value ? 'disabled' : ''; ?>>
                <?php esc_html_e( 'Send Test', 'broken-link-notifier' ); ?>
            </button>
        </div>
        <?php if ( !empty( $args[ 'accordion_title' ] ) && !empty( $args[ 'accordion_content' ] ) ) : ?>
            <div class="blnotifier-accordion">
                <button type="button" class="blnotifier-accordion-toggle"><?php echo esc_html( $args[ 'accordion_title' ] ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="blnotifier-accordion-panel"><?php echo wp_kses_post( $args[ 'accordion_content' ] ); ?></div>
            </div>
        <?php endif;
    } // End field_url_with_test()


    /**
     * Render the emails field with a test notification button
     *
     * @param array $args
     * @return void
     */
    public function field_emails_with_test( $args ) {
        $value = esc_attr( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) );
        $has_value = !empty( $value );
        $toggle = isset( $args[ 'toggle' ] ) ? ' data-toggle="'.esc_attr( $args[ 'toggle' ] ).'"' : '';
        ?>
        <div class="blnotifier-notification-field"<?php echo $toggle; ?>>
            <input type="text" id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>" value="<?php echo esc_attr( $value ); ?>" pattern="([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+)(\s*,\s*([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+))*"/>
            <button type="button" class="blnotifier-button blnotifier-test-btn" data-type="email" data-field="<?php echo esc_attr( $args[ 'name' ] ); ?>" <?php echo !$has_value ? 'disabled' : ''; ?>>
                <?php esc_html_e( 'Send Test', 'broken-link-notifier' ); ?>
            </button>
        </div>
        <?php
    } // End field_emails_with_test()


    /**
     * Sanitize url
     *
     * @param string $value
     * @return string
     */
    public function sanitize_url( $value ) {
        return filter_var( $value, FILTER_SANITIZE_URL );
    } // End sanitize_url()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function field_checkbox( $args ) {
        $option_value = get_option( $args[ 'name' ], null );
        if ( is_null( $option_value ) ) {
            $value = isset( $args[ 'default' ] ) ? $args[ 'default' ] : false;
        } else {
            $value = $this->sanitize_checkbox( $option_value );
        }
        printf(
            '<label class="blnotifier-checkbox-label" for="%s"><input type="checkbox" id="%s" name="%s" value="yes" %s/><span>%s</span>%s</label>',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( checked( 1, $value, false ) ),
            wp_kses_post( isset( $args[ 'label' ] ) ? $args[ 'label' ] : '' ),
            $this->render_tooltip( isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '' )
        );        
    } // End field_checkbox()


    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return boolean
     */
    public function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


    /**
     * Custom callback function to print checkboxes field
     *
     * @param array $args
     * @return void
     */
    public function field_checkboxes( $args ) {
        $value = get_option( $args[ 'name' ] );

        if ( get_option( 'blnotifier_has_updated_settings' ) ) {
            $value = ! empty( $value ) && is_array( $value ) ? array_keys( $value ) : [];
        } else {
            $value = isset( $args[ 'default' ] ) && is_array( $args[ 'default' ] ) ? $args[ 'default' ] : [];
        }

        if ( isset( $args[ 'options' ] ) && is_array( $args[ 'options' ] ) ) {
            echo '<div class="blnotifier-checkboxes">';
            foreach ( $args[ 'options' ] as $key => $label ) {
                $checked = in_array( $key, $value ) ? 'checked' : '';
                printf(
                    '<label><input type="checkbox" id="%s" name="%s[%s]" value="1" %s/> %s</label>',
                    esc_attr( $args[ 'name' ] . '_' . $key ),
                    esc_attr( $args[ 'name' ] ),
                    esc_attr( $key ),
                    esc_html( $checked ),
                    esc_html( $label )
                );
            }
            echo '</div>';
        }
    } // field_checkboxes()

    /**
     * Custom callback function to print status codes field
     *
     * @param array $args
     * @return void
     */
    public function field_status_codes( $args ) {
        $value = filter_var_array( get_option( $args[ 'name' ], [] ), FILTER_SANITIZE_SPECIAL_CHARS );

        $HELPERS = new BLNOTIFIER_HELPERS;
        $broken = $HELPERS->get_bad_status_codes();
        $warning = $HELPERS->get_warning_status_codes( true );

        if ( empty( $value ) ) {
            $mark_zero_as_broken = filter_var( get_option( 'blnotifier_mark_code_zero_broken' ), FILTER_VALIDATE_BOOLEAN );
            if ( $mark_zero_as_broken ) {
                $value[ 0 ] = 'broken'; 
            } else {
                $value[ 0 ] = 'warning'; 
            }
            
            foreach ( $broken as $code ) {
                $value[ $code ] = 'broken';
            }
            
            foreach ( $warning as $code ) {
                if ( !in_array( $code, array_keys( $value ) ) ) {
                    $value[ $code ] = 'warning';
                }
            }
        }
    
        if ( isset( $args[ 'options' ] ) ) {
            ?>
            <div class="blnotifier-status-summary">
                <div class="blnotifier-status-summary-item broken">
                    <span class="label">Broken Status Codes</span>
                    <span class="codes" id="bln-broken-codes-summary"><?php echo esc_html( !empty( $broken ) ? implode( ', ', $broken ) : 'None' ); ?></span>
                </div>
                <div class="blnotifier-status-summary-item warning">
                    <span class="label">Warning Status Codes</span>
                    <span class="codes" id="bln-warning-codes-summary"><?php echo esc_html( !empty( $warning ) ? implode( ', ', $warning ) : 'None' ); ?></span>
                </div>
            </div>

            <div class="blnotifier-accordion">
                <button type="button" class="blnotifier-accordion-toggle">View/Change Status Types <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="blnotifier-accordion-panel">
            <?php

            foreach ( $args[ 'options' ] as $code => $c ) {
                $type = isset( $value[ $code ] ) ? $value[ $code ] : 'good';
                $checked_good = $type === 'good' ? 'checked' : '';
                $checked_warning = $type === 'warning' ? 'checked' : '';
                $checked_broken = $type === 'broken' ? 'checked' : '';
                $display_code = isset( $c[ 'official' ] ) && !$c[ 'official' ] ? $code : '<a href="https://http.dev/' . $code . '" target="_blank">' . $code . '</a>';
    
                printf(
                    '<div class="status-row ' . esc_attr( $type ) . '">
                        <div class="info-input">
                            <div class="code-msg">
                                <span class="code">%s</span> <span class="message">(%s)</span>
                            </div>
                            <div class="description">%s</div>
                            <div class="selections">
                                <input type="radio" id="%s_good" name="%s[%s]" value="good" %s/> 
                                <label for="%s_good">Good</label>
                                <input type="radio" id="%s_warning" name="%s[%s]" value="warning" %s/> 
                                <label for="%s_warning">Warning</label>
                                <input type="radio" id="%s_broken" name="%s[%s]" value="broken" %s/> 
                                <label for="%s_broken">Broken</label>
                            </div>
                        </div>
                        <div class="indicator">
                            <div class="type">%s</div>
                        </div>
                    </div><br><br>',
                    wp_kses( $display_code, [ 'a' => [ 'href' => [], 'target' => [] ] ] ),
                    esc_html( $c[ 'msg' ] ),
                    wp_kses( $c[ 'desc' ], [ 'code' ] ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_good ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_warning ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_html( $args[ 'name' ].'_'.$code ),
                    esc_html( $args[ 'name' ] ),
                    esc_attr( $code ),
                    esc_html( $checked_broken ),
                    esc_html( $args[ 'name' ].'_'.$code ),

                    esc_attr( strtoupper( $type ) )
                );
            }

            echo '</div></div>';
        }
    } // End field_status_codes()


    /**
     * Sanitize checkboxes
     *
     * @param array $value
     * @return boolean
     */
    public function sanitize_checkboxes( $value ) {
        if ( !is_null( $value ) ) {
            return filter_var_array( $value, FILTER_VALIDATE_BOOLEAN );
        } else {
            return [];
        }
    } // End sanitize_checkboxes()


    /**
     * Sanitize boolean
     *
     * @param mixed $input
     * @return boolean
     */
    public function sanitize_boolean( $input ) {
        return (bool) $input;
    } // End sanitize_boolean()


    /**
     * Get post type choices
     *
     * @return array
     */
    public function get_post_type_choices() {
        $HELPERS = new BLNOTIFIER_HELPERS;
        $results = [];

        $post_types = $HELPERS->get_post_types();

        if ( ! is_array( $post_types ) || empty( $post_types ) ) {
            return $results; // return empty array if no post types found
        }

        foreach ( $post_types as $post_type ) {
            $post_type_name = $HELPERS->get_post_type_name( $post_type );
            if ( $post_type_name === null ) {
                $post_type_name = $post_type; // fallback to post type slug
            }
            $results[ $post_type ] = $post_type_name;
        }

        return $results;
    } // End get_post_type_choices()


    /**
     * Get editable roles choices
     *
     * @return array
     */
    public function get_editable_roles_choices() {
        global $wp_roles;
        $results = [];
        $roles = $wp_roles->get_names();
        foreach ( $roles as $role_slug => $role_name ) {
            if ( $role_slug == 'administrator' ) {
                continue;
            }
            $results[ $role_slug ] = $role_name;
        }
        return $results;
    } // End get_editable_roles_choices()


    /**
     * Custom callback function to print multiple emails field
     *
     * @param array $args
     * @return void
     */
    public function field_emails( $args ) {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" pattern="%s"/>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            '([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+)(\s*,\s*([a-zA-Z0-9+_.\-]+@[a-zA-Z0-9.\-]+.[a-zA-Z0-9]+))*',
        );
    } // field_emails()


    /**
     * Custom callback function to print number field
     *
     * @param array $args
     * @return void
     */
    public function field_number( $args ) {
        printf(
            '<input type="number" id="%s" name="%s" value="%d" min="%d" required/>',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_attr( get_option( $args[ 'name' ], isset( $args[ 'default' ] ) ? $args[ 'default' ] : '' ) ),
            esc_attr( $args[ 'min' ] )
        );
    } // End field_number()


    /**
     * Custom callback function to print textarea field
     * 
     * @param array $args
     * @return void
     */
    public function field_textarea( $args ) {
        printf(
            '<textarea class="textarea" id="%s" name="%s"/>%s</textarea>',
            esc_html( $args[ 'name' ] ),
            esc_html( $args[ 'name' ] ),
            esc_html( get_option( $args[ 'name' ], '' ) ),
        );
    } // field_textarea()


    /**
     * API key field
     *
     * @param array $args
     * @return void
     */
    public function field_api_key( $args ) {
        $key = get_option( $args[ 'name' ], '' );
        ?>
        <div class="blnotifier-api-key-wrapper">
            <div id="blnotifier-api-key-display" class="blnotifier-api-key-box <?php echo $key ? 'has-key' : 'no-key'; ?>">
                <?php echo $key ? esc_html( $key ) : '<em>No API Key Generated</em>'; ?>
            </div>

            <input type="hidden" id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>" value="<?php echo esc_attr( $key ); ?>">

            <div class="blnotifier-api-key-actions">
                <button type="button" id="blnotifier-generate-key" class="blnotifier-button">Generate New API Key</button>
                <button type="button" id="blnotifier-copy-key" class="blnotifier-button" <?php echo !$key ? 'disabled' : ''; ?>>Copy</button>
                <button type="button" id="blnotifier-clear-key" class="blnotifier-button" <?php echo !$key ? 'disabled' : ''; ?>>Clear</button>
            </div>
        </div>
        <?php
    } // End field_api_key()


    /**
     * Render settings fields grouped into boxes
     *
     * @return void
     */
    public function render_settings_boxes() {
        global $wp_settings_fields;

        if ( empty( $wp_settings_fields[ $this->page_slug ][ 'general' ] ) ) {
            return;
        }

        $boxes = [];
        foreach ( $wp_settings_fields[ $this->page_slug ][ 'general' ] as $field ) {
            $box = isset( $field[ 'args' ][ 'box' ] ) ? $field[ 'args' ][ 'box' ] : 'scanning';
            $boxes[ $box ][] = $field;
        }

        $box_order = array_keys( $this->get_settings_box_labels() );
        $ordered_boxes = [];
        foreach ( $box_order as $box_key ) {
            if ( isset( $boxes[ $box_key ] ) ) {
                $ordered_boxes[ $box_key ] = $boxes[ $box_key ];
                unset( $boxes[ $box_key ] );
            }
        }
        $ordered_boxes = array_merge( $ordered_boxes, $boxes );

        $box_labels = $this->get_settings_box_labels();
        ?>
        <div class="blnotifier-settings-grid">
            <?php foreach ( $ordered_boxes as $box_key => $fields ) : ?>
                <div class="blnotifier-box" id="blnotifier-box-<?php echo esc_attr( str_replace( '_', '-', $box_key ) ); ?>">
                    <div class="blnotifier-box-header">
                        <h2><?php echo esc_html( $box_labels[ $box_key ] ?? ucfirst( str_replace( '_', ' ', $box_key ) ) ); ?></h2>
                    </div>
                    <div class="blnotifier-box-body">
                        <?php foreach ( $fields as $field ) : $this->render_settings_field( $field ); endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    } // End render_settings_boxes()


    /**
     * Render a single settings field wrapper. Checkboxes get their label to the
     * right of the input; every other field type gets its label above the input.
     *
     * @param array $field
     * @return void
     */
    public function render_settings_field( $field ) {
        $callback_name = is_array( $field[ 'callback' ] ) ? $field[ 'callback' ][ 1 ] : $field[ 'callback' ];
        $args = $field[ 'args' ];
        $args[ 'label' ] = $field[ 'title' ];
        $name = isset( $args[ 'name' ] ) ? $args[ 'name' ] : '';
        $comments = isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '';
        $toggle_attr = isset( $args[ 'toggle' ] ) ? ' data-toggle="'.esc_attr( $args[ 'toggle' ] ).'"' : '';

        if ( $callback_name === 'field_checkbox' ) {
            echo '<div class="blnotifier-field blnotifier-field-checkbox">';
            call_user_func( $field[ 'callback' ], $args );
            echo '</div>';
        } else {
            echo '<div class="blnotifier-field">';
            echo '<label for="'.esc_attr( $name ).'"'.$toggle_attr.'>'.wp_kses_post( $field[ 'title' ] ).$this->render_tooltip( $comments ).'</label>';
            call_user_func( $field[ 'callback' ], $args );
            echo '</div>';
        }
    } // End render_settings_field()


    /**
     * Render a tooltip icon with hover text, matching AHD's pattern
     *
     * @param string $comments
     * @return string
     */
    public function render_tooltip( $comments ) {
        if ( empty( $comments ) ) {
            return '';
        }

        return ' <span class="blnotifier-tooltip"><span class="dashicons dashicons-editor-help"></span><span class="blnotifier-tooltip-text">'.wp_kses( $comments, [ 'code' => [], 'a' => [ 'href' => [], 'target' => [] ] ] ).'</span></span>';
    } // End render_tooltip()


    /**
     * Render the Save button and save reminder in the left subheader on the Settings tab
     *
     * @param string $active_tab
     * @return void
     */
    public function render_settings_subheader_left( $active_tab ) {
        if ( $active_tab !== 'settings' ) {
            return;
        }
        ?>
        <button type="button" id="blnotifier-save-settings" class="blnotifier-button">Save</button>
        <span id="blnotifier-save-reminder">Remember to click "Save" after making changes to your settings.</span>
        <?php
    } // End render_settings_subheader_left()


    /**
     * Render the doc/support buttons in the right subheader on the Settings tab
     *
     * @param string $active_tab
     * @return void
     */
    public function render_settings_subheader_right( $active_tab ) {
        if ( $active_tab !== 'settings' ) {
            return;
        }
        ?>
        <a href="<?php echo esc_url( BLNOTIFIER_GUIDE_URL ); ?>" target="_blank" class="blnotifier-button bln-external-link">How-To Guide <span class="dashicons dashicons-external"></span></a>
        <a href="<?php echo esc_url( BLNOTIFIER_DOCS_URL ); ?>" target="_blank" class="blnotifier-button bln-external-link">Developer Docs <span class="dashicons dashicons-external"></span></a>
        <a href="<?php echo esc_url( BLNOTIFIER_SUPPORT_URL ); ?>" target="_blank" class="blnotifier-button bln-external-link">Support Forum <span class="dashicons dashicons-external"></span></a>
        <?php
    } // End render_settings_subheader_right()


    /**
     * Render the plugin version in the subheader
     *
     * @param string $active_tab
     * @return void
     */
    public function render_version( $active_tab ) {
        if ( $active_tab !== 'settings' ) {
            return;
        }
        ?>
        <span id="blnotifier-version"><?php esc_html_e( 'Version', 'broken-link-notifier' ); ?> <?php echo esc_html( BLNOTIFIER_VERSION ); ?></span>
        <?php
    } // End render_version()


    /**
     * Render the download/upload/reset buttons
     *
     * @param array $args
     * @return void
     */
    public function field_backup_tools( $args ) {
        ?>
        <div class="blnotifier-backup-tools">
            <button type="button" id="blnotifier-download-settings-btn" class="blnotifier-button">Download Settings</button>

            <div id="blnotifier-upload-settings-button">
                <label for="blnotifier-upload-settings"><span class="blnotifier-button">Upload Settings</span></label>
                <input type="file" id="blnotifier-upload-settings" accept=".json">
            </div>
            <div id="blnotifier-upload-settings-filename"></div>

            <button type="button" id="blnotifier-reset-settings" class="blnotifier-button">Reset All Settings</button>
        </div>
        <?php
    } // End field_backup_tools()


    /**
     * Render the Clear Cache button
     *
     * @param array $args
     * @return void
     */
    public function field_clear_cache( $args ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_cache';
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ); // phpcs:ignore
        ?>
        <div class="blnotifier-backup-tools">
            <button type="button" id="blnotifier-clear-cache" class="blnotifier-button">Clear Cache</button>
            <span id="blnotifier-cache-count">Currently caching <?php echo absint( $count ); ?> link<?php echo $count == 1 ? '' : 's'; ?>.</span>
        </div>
        <?php
    } // End field_clear_cache()


    /**
     * Get a flat list of field metadata for JS-side download/upload/reset
     *
     * @return array
     */
    public function get_field_definitions_for_js() {
        global $wp_settings_fields;

        $fields = [];
        if ( empty( $wp_settings_fields[ $this->page_slug ][ 'general' ] ) ) {
            return $fields;
        }

        foreach ( $wp_settings_fields[ $this->page_slug ][ 'general' ] as $field ) {
            $name = isset( $field[ 'args' ][ 'name' ] ) ? $field[ 'args' ][ 'name' ] : null;
            if ( !$name || $name === 'blnotifier_settings_backup_tools' ) {
                continue;
            }

            $callback_name = is_array( $field[ 'callback' ] ) ? $field[ 'callback' ][ 1 ] : $field[ 'callback' ];

            $type = 'text';
            if ( $callback_name === 'field_checkbox' ) {
                $type = 'checkbox';
            } elseif ( $callback_name === 'field_checkboxes' ) {
                $type = 'checkboxes';
            } elseif ( $callback_name === 'field_number' ) {
                $type = 'number';
            } elseif ( $callback_name === 'field_status_codes' ) {
                $type = 'status_codes';
                $defaults = (new BLNOTIFIER_HELPERS)->get_default_status_codes();
                $fields[] = [
                    'name'            => $name,
                    'type'            => $type,
                    'default_broken'  => $defaults[ 'broken' ],
                    'default_warning' => $defaults[ 'warning' ],
                ];
                continue;
            } elseif ( $callback_name === 'field_api_key' ) {
                // API key is never included in download/upload/reset
                continue;
            }

            $fields[] = [
                'name'    => $name,
                'type'    => $type,
                'default' => isset( $field[ 'args' ][ 'default' ] ) ? $field[ 'args' ][ 'default' ] : null,
            ];
        }

        return $fields;
    } // End get_field_definitions_for_js()


    /**
     * Enqueue script
     *
     * @param string $screen
     * @return void
     */
    public function enqueue_scripts( $screen ) {
        $options_page = 'toplevel_page_'.BLNOTIFIER_TEXTDOMAIN;
        $tab = (new BLNOTIFIER_HELPERS)->get_tab();
        if ( ( $screen == $options_page && $tab == 'settings' ) ) {
            wp_enqueue_style( 'blnotifier-settings', BLNOTIFIER_PLUGIN_CSS_PATH.'settings.css', [ 'blnotifier-theme' ], BLNOTIFIER_SCRIPT_VERSION );

            $handle = 'blnotifier_settings_script';
            wp_register_script( $handle, BLNOTIFIER_PLUGIN_JS_PATH.'settings.js', [ 'jquery' ], BLNOTIFIER_SCRIPT_VERSION, true );
            wp_localize_script( $handle, 'blnotifier_settings', [
                'api_key'           => get_option( 'blnotifier_api_key', '' ),
                'nonce'             => wp_create_nonce( 'blnotifier_test_notification' ),
                'clear_cache_nonce' => wp_create_nonce( 'blnotifier_clear_cache' ),
                'save_nonce'        => wp_create_nonce( 'blnotifier_save_settings' ),
                'fields'            => $this->get_field_definitions_for_js(),
                'ajaxurl'           => admin_url( 'admin-ajax.php' ),
            ] );
            wp_enqueue_script( $handle );
            wp_enqueue_script( 'jquery' );
        }
    } // End enqueue_scripts()


    /**
     * AJAX handler for clearing the cache
     *
     * @return void
     */
    public function ajax_clear_cache() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), 'blnotifier_clear_cache' ) ) {
            wp_send_json_error( [ 'msg' => 'Invalid nonce.' ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => 'Unauthorized.' ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'blnotifier_cache';
        $wpdb->query( "TRUNCATE TABLE $table_name" ); // phpcs:ignore

        wp_send_json_success();
    } // End ajax_clear_cache()


    /**
     * AJAX handler for test notifications
     *
     * @return void
     */
    public function ajax_test_notification() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), 'blnotifier_test_notification' ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        $type  = isset( $_REQUEST[ 'type' ] ) ? sanitize_key( $_REQUEST[ 'type' ] ) : '';
        $value = isset( $_REQUEST[ 'value' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'value' ] ) ) : '';

        if ( !$type || !$value ) {
            wp_send_json_error( [ 'msg' => __( 'Missing data.', 'broken-link-notifier' ) ] );
        }

        $fake_link    = 'https://example.com/broken-link';
        $fake_code    = 404;
        $fake_text    = 'Not Found';
        $fake_source  = home_url( '/sample-page/' );

        if ( $type === 'email' ) {
            $emails  = array_map( 'trim', explode( ',', $value ) );
            $headers = [
                'From: ' . BLNOTIFIER_NAME . ' <' . get_bloginfo( 'admin_email' ) . '>',
                'Content-Type: text/html; charset=UTF-8',
            ];
            $subject = 'Test: Broken Links Found';
            $message = 'This is a test notification from ' . BLNOTIFIER_NAME . '.<br><br>';
            $message .= 'CONTENT<br><br>';
            $message .= 'URL: ' . $fake_link . '<br>Status Code: ' . $fake_code . ' - ' . $fake_text;
            $message .= '<br><br><hr><br>' . get_bloginfo( 'name' ) . '<br><em>' . BLNOTIFIER_NAME . ' Plugin</em>';

            if ( wp_mail( $emails, $subject, $message, $headers ) ) {
                wp_send_json_success();
            } else {
                wp_send_json_error( [ 'msg' => __( 'Email could not be sent.', 'broken-link-notifier' ) ] );
            }

        } elseif ( $type === 'discord' ) {
            $DISCORD = new BLNOTIFIER_DISCORD;
            $args = [
                'msg'            => '',
                'embed'          => true,
                'author_name'    => 'Source: ' . $fake_source,
                'author_url'     => $fake_source,
                'title'          => get_bloginfo( 'name' ),
                'title_url'      => home_url(),
                'desc'           => '-------------------',
                'img_url'        => '',
                'thumbnail_url'  => '',
                'disable_footer' => false,
                'bot_avatar_url' => BLNOTIFIER_PLUGIN_IMG_PATH . 'logo-teal.png',
                'bot_name'       => BLNOTIFIER_NAME,
                'fields'         => [
                    [
                        'name'   => 'Broken Link:',
                        'value'  => $fake_link . "\nStatus Code: " . $fake_code . ' - ' . $fake_text,
                        'inline' => false,
                    ],
                ],
            ];
            $result = $DISCORD->send( $value, $args );
            $result ? wp_send_json_success() : wp_send_json_error( [ 'msg' => __( 'Could not send to Discord.', 'broken-link-notifier' ) ] );

        } elseif ( $type === 'msteams' ) {
            $MSTEAMS = new BLNOTIFIER_MSTEAMS;
            $args = [
                'site_name'  => get_bloginfo( 'name' ),
                'title'      => 'Broken Links Found',
                'msg'        => 'The following broken links were found:',
                'img_url'    => '',
                'source_url' => $fake_source,
                'facts'      => [
                    [
                        'name'  => 'Broken Link:',
                        'value' => '[' . $fake_link . '](' . $fake_link . ') _Status Code: **' . $fake_code . '** - ' . $fake_text . '_',
                    ],
                ],
            ];
            $result = $MSTEAMS->send( $value, $args );
            $result ? wp_send_json_success() : wp_send_json_error( [ 'msg' => __( 'Could not send to Microsoft Teams.', 'broken-link-notifier' ) ] );

        } elseif ( $type === 'slack' ) {
            $SLACK = new BLNOTIFIER_SLACK;
            $args = [
                'title'  => 'Broken Links Found',
                'source' => $fake_source,
                'fields' => [
                    [
                        'link' => $fake_link,
                        'code' => $fake_code,
                        'text' => $fake_text,
                    ],
                ],
            ];
            $result = $SLACK->send( $value, $args );
            $result ? wp_send_json_success() : wp_send_json_error( [ 'msg' => __( 'Could not send to Slack.', 'broken-link-notifier' ) ] );

        } else {
            wp_send_json_error( [ 'msg' => __( 'Unknown notification type.', 'broken-link-notifier' ) ] );
        }
    } // End ajax_test_notification()


    /**
     * AJAX handler to save all settings fields
     *
     * @return void
     */
    public function ajax_save_settings() {
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), 'blnotifier_save_settings' ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'broken-link-notifier' ) ] );
        }

        if ( !(new BLNOTIFIER_HELPERS)->user_can_manage_broken_links() ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'broken-link-notifier' ) ] );
        }

        global $wp_settings_fields;
        $fields = isset( $wp_settings_fields[ $this->page_slug ][ 'general' ] ) ? $wp_settings_fields[ $this->page_slug ][ 'general' ] : [];

        foreach ( $fields as $field ) {
            $name = isset( $field[ 'args' ][ 'name' ] ) ? $field[ 'args' ][ 'name' ] : null;
            if ( !$name ) {
                continue;
            }

            $callback_name = is_array( $field[ 'callback' ] ) ? $field[ 'callback' ][ 1 ] : $field[ 'callback' ];

            if ( $callback_name === 'field_checkbox' ) {
                $value = isset( $_POST[ $name ] ) ? 'yes' : '';
                update_option( $name, $value );

            } elseif ( $callback_name === 'field_checkboxes' || $callback_name === 'field_status_codes' ) {
                $value = isset( $_POST[ $name ] ) && is_array( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : []; // phpcs:ignore
                update_option( $name, $value );

            } else {
                $value = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore
                update_option( $name, $value );
            }
        }

        // Mark settings as having been saved at least once
        update_option( 'blnotifier_has_updated_settings', true );

        // Sync role capabilities against the freshly saved editable_roles option
        $this->update_roles();

        wp_send_json_success( [ 'msg' => __( 'Settings saved successfully.', 'broken-link-notifier' ) ] );
    } // End ajax_save_settings()

}