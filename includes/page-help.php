<?php
// Available hooks
$hooks = [
    [
        'hook'  => 'blnotifier_bad_status_codes',
        'args'  => '( Array $codes )',
        'label' => 'Which codes to signal as bad',
        'desc'  => '<li>Defaults: [ 666, 308, 404, 408 ]</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_warning_status_codes',
        'args'  => '( Array $codes )',
        'label' => 'Which codes to signal as warning only',
        'desc'  => '<li>Defaults: [ 0 ]</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_omitted_links',
        'args'  => '( Array $links )',
        'label' => 'Your omitted links',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_omitted_pages',
        'args'  => '( Array $pages )',
        'label' => 'Your omitted pages',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_omitted_pageload_post_types',
        'args'  => '( Array $post_types )',
        'label' => 'Post types that you do not want to scan on page load',
        'type'  => 'filter'
    ],
    [
        'hook'  => 'blnotifier_omitted_fullscan_post_types',
        'args'  => '( Array $post_types )',
        'label' => 'Post types that you do not want to allow for full scan option',
        'type'  => 'filter'
    ],
    [
        'hook'  => 'blnotifier_link_before_prechecks',
        'args'  => '( String|Array|False $link )',
        'label' => 'The link before checking anything',
        'desc'  => '<li>Return a modified link as a <code>String</code></li><li>Return <code>False</code> to set as broken</li><li>Return the status as an <code>Array ( String <em>type</em>, Int <em>code</em>, String <em>text</em>, String <em>link</em> )</code></li><li>Status <em>type</em> can be "broken," "warning," or "good"</li>',
        'type'  => 'filter'
    ],
    [
        'hook'  => 'blnotifier_status',
        'args'  => '( Array $status )',
        'label' => 'The status that is returned when checking a link for validity after all pre-checks are done',
        'desc'  => '<li>Return the status as an <code>Array ( String <em>type</em>, Int <em>code</em>, String <em>text</em>, String <em>link</em> )</code></li><li>Status <em>type</em> can be "broken," "warning," or "good"</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_http_request_args',
        'args'  => '( Array $args, String $link )',
        'label' => 'The http request args',
        'desc'  => '<li>See <a href="https://developer.wordpress.org/reference/classes/WP_Http/request/" target="_blank">WP_Http::request</a> for information on accepted arguments</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_remove_source_qs',
        'args'  => '( Array $query_strings )',
        'label' => 'Query strings to remove from source url on page load scans',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_url_schemes',
        'args'  => '( Array $schemes )',
        'label' => 'URL Schemes skipped during pre-checks',
        'desc'  => '<li>See <a href="https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml" target="_blank">IANA</a> for official code list</li><li>See <a href="https://en.wikipedia.org/wiki/List_of_URI_schemes" target="_blank">Wiki</a> for unofficial code list</li><li>Excludes <code>http</code> and <code>https</code></li><li>Lists last updated on 3/7/24</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_capability',
        'args'  => '( String $capability )',
        'label' => 'Change the user capability for viewing the plugin reports and settings on the back-end',
        'desc'  => '<li>Default: <code>manage_options</code></li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_suggested_offsite_checkers',
        'args'  => '( Array $checkers )',
        'label' => 'The list of suggested offsite checkers on Full Scan page',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_notify',
        'args'  => '( Array $flagged, Int $flagged_count, Array $all_links, String $source_url )',
        'label' => 'Fires when notifying you of new broken links and warning links that are found on page load',
        'desc'  => '<li>Useful for making your own custom notifications</li>',
        'type'  => 'action'
    ],
    [    
        'hook'  => 'blnotifier_email_emails',
        'args'  => '( String|Array $emails, Array $flagged, String $source_url )',
        'label' => 'Filter the emails that the email notifications are sent to',
        'desc'  => '<li>Default: emails listed in Settings</li><li>Useful for filtering emails based on links and source</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_email_subject',
        'args'  => '( String $subject, Array $flagged, String $source_url )',
        'label' => 'Filter the subject that the email notifications are sent with',
        'desc'  => '<li>Default: Broken Links Found</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_email_message',
        'args'  => '( String $message, Array $flagged, String $source_url )',
        'label' => 'Filter the message that the email notifications are sent with',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_email_headers',
        'args'  => '( Array $headers, Array $flagged, String $source_url )',
        'label' => 'Filter the headers used in the email notifications',
        'desc'  => '<li>Default:<br><code>Array (<br>&nbsp;&nbsp;&nbsp;&nbsp;"From: '.BLNOTIFIER_NAME.' &#60;'.get_bloginfo( 'admin_email' ).'&#62;",<br>&nbsp;&nbsp;&nbsp;&nbsp;"Content-Type: text/html; charset=UTF-8"<br>)</code></li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_discord_args',
        'args'  => '( Array $args, Array $flagged, String $source_url )',
        'label' => 'Filter the Discord webhook args',
        'desc'  => '<li>Return an <code>Array ( String <em>msg</em>, Bool <em>embed</em>, String <em>author_name</em>, String <em>author_url</em>, String <em>title</em>, String <em>title_url</em>, String <em>desc</em>, String <em>img_url</em>, String <em>thumbnail_url</em>, Bool <em>disable_footer</em>, String <em>bot_avatar_url</em>, String <em>bot_name</em>, Array <em>fields</em> )</code></li><li><em>fields</em> includes the broken links</li>',
        'type'  => 'filter'
    ],
    [    
        'hook'  => 'blnotifier_msteams_args',
        'args'  => '( Array $args, Array $flagged, String $source_url )',
        'label' => 'Filter the Microsoft Teams webhook args',
        'desc'  => '<li>Return an <code>Array ( String <em>site_name</em>, String <em>title</em>, String <em>msg</em>, String <em>img_url</em>, String <em>title</em>, String <em>title_url</em>, String <em>desc</em>, String <em>img_url</em>, String <em>source_url</em>, Array <em>facts</em> )</code></li><li><em>facts</em> includes the broken links</li>',
        'type'  => 'filter'
    ]
];

// Initiate
$MENU = new BLNOTIFIER_MENU;
$HELPERS = new BLNOTIFIER_HELPERS;
?>

<style>
h2 {
    margin-top: 0 !important;
}
h2, section div {
    margin-bottom: 30px;
}
ul {
    padding: revert;
}
ol li {
    padding-inline-start: 1ch;
}
ol, ul  {
    list-style: revert;
}
ol li ol { list-style-type: lower-alpha !important; }
table.hooks th.type {
    width: 100px;
}
.example-code {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border-radius: 5px;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.example-code code {
    padding: 0 !important;
    margin: 0 !important;
    background: transparent;
}
</style>

<section>
    <h2><?php esc_html_e( 'Plugin Support', 'broken-link-notifier' ); ?></h2>

    <br><img class="discord-logo" src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH ); ?>discord.png" width="auto" height="100">
    <p>If you need assistance with this plugin or have suggestions for improving it, please join the Discord server below.</p>
    <a class="button button-primary" href="<?php echo esc_url( BLNOTIFIER_DISCORD_SUPPORT_URL ); ?>" target="_blank">Join Our Support Server »</a>
    <br><br>
    <p>Or if you would rather get support on WordPress.org, you can do so here:</p>
    <a class="button button-primary" href="https://wordpress.org/support/plugin/<?php echo esc_attr( BLNOTIFIER_TEXTDOMAIN ); ?>/" target="_blank">WordPress.org Plugin Support Page »</a>
</section>

<br><br><hr><br>

<section>
    <h2>Scanning Options</h2>

    <div class="scanning-option">
        <strong>Page Load</strong> <em>- Automatic</em>
        <p>As people visit your webpages, the plugin will scan the links using jQuery and AJAX in the background after the page loads. This does not affect load time or performance. It only takes a few seconds depending on how many links you have. Results will be sent to the <a href="<?php echo esc_url( $MENU->get_plugin_page( 'results' ) ); ?>">Results</a> page, and notifications of broken links will be sent via email, Discord and/or Microsoft Teams, depending on what you have enabled and set up in <a href="<?php echo esc_url( $MENU->get_plugin_page( 'settings' ) ); ?>">Settings</a>.</p>
        <p>If you select to show results in the dev console from Settings, you can visit a page that is not omitted and see the scan results as they happen. The dev(eloper) console is a tool that logs information about the backend operations of the sites you visit and applications you run. The information logged in the console can help developers to solve any issue that they may experience. This will also confirm which pages are being scanned. Of course, anybody can see them if they open the dev console, so it's up to you if you want them to see it.</p>
        <img src="<?php echo esc_url( BLNOTIFIER_PLUGIN_IMG_PATH.'devconsole.gif' ); ?>">
    </div>

    <div class="scanning-option">
        <strong>Full Scan</strong> <em>- <a href="<?php echo esc_url( $MENU->get_plugin_page( 'scan-full' ) ); ?>">Check it out!</a></em>
        <p>Our "Full Scan" is different than most plugins. It doesn't just scan the whole website and give you results like you would expect. The reason we don't do that is because most hosts do not allow for such a big load all at once. It causes a slew of issues and often times out. Generally speaking, doing a full scan like that is better to be done off-site. The way we do it is by loading your WP List Tables for individual post types, checking one set of pages at a time. We also ignore the header and footer during the process since it's unlikely to be an issue.</p>
    </div>

    <div class="scanning-option">
        <strong>Page Scan</strong> <em>- <a href="<?php echo esc_url( $MENU->get_plugin_page( 'scan-single' ) ); ?>">Check it out!</a></em>
        <p>This page was designed to take a deeper dive into an individual pages so you can see the results of all the links on the page rather than just the broken ones. A link to this scan is also added to the post type action links so you can quickly scan them after creating or editing them.</p>
    </div>
</section>

<br><hr><br>

<section>
    <h2>Frequently Asked Questions</h2>

    <div class="faq">
        <strong>Why do some links show as broken when they are not?</strong>
        <p>If the link works fine and it's still being flagged as broken, then it is either redirecting to another page or there is an issue with the page's response headers and there's nothing we can do about it. If it is a redirect on your own site due to permalink modification, then it's better to fix the link instead of unnecessarily redirecting. You may use the Omit option to omit false positives from future scans as well. If you are seeing a pattern with a multiple links from the same domain, you can go to <a href="<?php echo esc_url( $MENU->get_plugin_page( 'omit-links' ) ); ?>">Omitted Links</a> to add a domain with a wildcard (*), which will omit all links starting with that domain.</p>
    </div>

    <div class="faq">
        <strong>What causes a link to give a warning?</strong>
        <p>Warnings mean the link was found, but they may be unsecure or slow to respond. If you are getting too many warnings due to timeouts, try increasing your timeout in Settings. This will just result in longer wait times, but with more accuracy. You can also disable warnings if you have too many of them.</p>
    </div>

    <div class="faq">
        <strong>What is status code <code>666</code>?</strong>
        <p>A status code of <code>666</code> is a code we use to force invalid URL <code>code 0</code> to be a broken link in case warnings are disabled. It is not an official status code.</p>
    </div>

    <div class="faq">
        <strong>Can I omit links and pages from scans?</strong>
        <p>Yes, you can omit links from being checked for validity by using the "Omit" link in the scan results, or by entering them in manually under <a href="<?php echo esc_url( $MENU->get_plugin_page( 'omit-links' ) ); ?>">Omitted Links</a>. Likewise, you can omit pages from being scanned from the results page or <a href="<?php echo esc_url( $MENU->get_plugin_page( 'omit-links' ) ); ?>">Omitted Pages</a>. Wildcards (*) are accepted.</p>
    </div>

    <div class="faq">
        <strong>When I click on "Find On Page," I cannot find the link. Where is it?</strong>
        <p>Sometimes links are hidden with CSS or inside modals/popups. To find hidden links, go to the page and either open your developer console or view the page source and search for the link. This will show you where it is and which element to look in. Then you can edit the page accordingly. This is more advanced and may require some assistance, so feel free to reach out to me for help.</p>
    </div>

    <div class="faq">
        <strong>Why does the dev console show more links that what is scanned on the full scan?</strong>
        <p>The full scan link count does not include links that are filtered out from the pre-check.</p>
    </div>

    <div class="faq">
        <strong>What pre-checks are used to filter out broken links?</strong>
        <p>We skip links that start with <code>#</code> (anchor tags and JavaScript) or <code>?</code> (query strings), non-http url schemes (such as <code>mailto:</code>, <code>tel:</code>, <code>data:</code>, etc. ), and any links you have omitted.</p>
    </div>

    <div class="faq">
        <strong>What can I do if I have the same broken link on a lot of pages?</strong>
        <p>There are plugins such as <a href="https://wordpress.org/plugins/better-search-replace/" target="_blank">Better Search Replace <em>by WP Engine</em></a> that will replace URLs on your entire site at once.</p>
    </div>
</section>

<br><hr><br>

<section>
    <h2>How to Connect to Discord</h2>

    <p>Using Discord to receive notifications is easy to set up, and often a more reliable method since emails can end up getting lost in cyberspace sometimes. The instructions below assume you already have a Discord account.</p>

    <strong>Set Up:</strong>
    <ol>
        <li><a href="https://support.discord.com/hc/en-us/articles/204849977-How-do-I-create-a-server" target="_blank">Create a server</a> if you don't already have one (it's free and easy)</li>
        <li>Go to Server Settings > Integrations</li>
        <li>Click on Webhooks</li>
        <li>Click on "New Webhook" (once)</li>
        <li>Scroll down and click on your new webhook (probably named "Captain Hook")</li>
        <li>Name your webhook (this will be used as the name that the messages are posted by)</li>
        <li>Choose the channel the messages should be posted in</li>
        <li>Click on "Copy Webhook URL"; it will save to your clipboard</li>
        <li>Add the webhook url to <a href="<?php echo esc_url( $MENU->get_plugin_page( 'settings' ) ); ?>">Settings</a> and enable Discord notifications</li>
        <li>Enable "show results in the dev console" so you can verify scanning results are being picked up</li>
        <li>Visit a page that you know has new broken links (if the broken links are added to your <a href="<?php echo esc_url( $MENU->get_plugin_page( 'results' ) ); ?>">Results</a> page, then you will need to delete them before testing again since it will only show the results once)</li>
    </ol>
</section>

<br><hr><br>

<section>
    <h2>How to Connect to Microsoft Teams</h2>

    <p>Using Microsoft Teams to receive notifications is easy to set up, too, and it's helpful for teams to work together on fixing the links. The instructions below assume you already have a Microsoft account and Teams installed.</p>

    <strong>Set Up:</strong>
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
        <li>Add the webhook url to <a href="<?php echo esc_url( $MENU->get_plugin_page( 'settings' ) ); ?>">Settings</a> and enable Microsoft Teams notifications</li>
        <li>Enable "show results in the dev console" so you can verify scanning results are being picked up</li>
        <li>Visit a page that you know has new broken links (if the broken links are added to your <a href="<?php echo esc_url( $MENU->get_plugin_page( 'results' ) ); ?>">Results</a> page, then you will need to delete them before testing again since it will only show the results once)</li>
    </ol>
</section>

<br><hr><br>

<section>
    <h2>Developer Hooks</h2>

    <p>Developers can use the following hooks to extend the capability of the plugin.</p>

    <table class="hooks wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th class="label">Description</th>
                <th class="hook">Hook</th>
                <th class="type">Type</th>
                <th class="args">Args</th>
            </tr>
        </thead>
        <?php
        
        foreach ( $hooks as $hook ) {
            ?>
            <tr class="link-row pending" data-link="<?php echo esc_html( $check_link ); ?>">
                <td class="label"><?php echo esc_html( $hook[ 'label' ] ); ?><?php echo wp_kses_post( isset( $hook[ 'desc' ] ) ? '<ul>'.$hook[ 'desc' ].'</ul>' : '' ); ?></td>
                <td class="hook"><code><?php echo esc_html( $hook[ 'hook' ] ); ?></code></td>
                <td class="type"><?php echo esc_html( $hook[ 'type' ] ); ?></td>
                <td class="args"><?php echo wp_kses_post( preg_replace( '/\$([\S]+)/', '<strong>$0</strong>', $hook[ 'args' ] ) ); ?></td>
            </tr>
            <?php
        }    
        ?>
        <tfoot>
            <tr>
                <th>Description</th>
                <th>Hook</th>
                <th>Type</th>
                <th>Args</th>
            </tr>
        </tfoot>
    </table>

    <br><br>

    <?php
    $filepath = BLNOTIFIER_PLUGIN_INCLUDES_PATH.'example-hook.php';
    if ( is_readable( $filepath ) ) {
        ?>
        <strong>Example Code:</strong>
        <div class="example-code">
            <?php echo wp_kses_post( highlight_file( $filepath, true ) ); ?>
        </div>
        <?php
    }
    ?>
</section>