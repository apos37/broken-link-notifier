<?php
/**
 * Plugin Name:         Broken Link Notifier
 * Plugin URI:          https://github.com/apos37/broken-link-notifier
 * Description:         Get notified when someone loads a page with a broken link
 * Version:             1.2.5.1
 * Requires at least:   5.9
 * Tested up to:        6.8
 * Requires PHP:        7.4
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Support URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         broken-link-notifier
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Created on:          April 7, 2024
 */


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Defines
 */
$plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'version'      => 'Version',
    'requires_php' => 'Requires PHP',
    'textdomain'   => 'Text Domain',
    'author'       => 'Author',
    'author_uri'   => 'Author URI',
    'support_uri'  => 'Support URI',
] );

// Versions
define( 'BLNOTIFIER_VERSION', $plugin_data[ 'version' ] );
define( 'BLNOTIFIER_MIN_PHP_VERSION', $plugin_data[ 'requires_php' ] );

// Names
define( 'BLNOTIFIER_NAME', $plugin_data[ 'name' ] );
define( 'BLNOTIFIER_TEXTDOMAIN', $plugin_data[ 'textdomain' ] );
define( 'BLNOTIFIER_AUTHOR_NAME', $plugin_data[ 'author' ] );
define( 'BLNOTIFIER_AUTHOR_URL', $plugin_data[ 'author_uri' ] );
define( 'BLNOTIFIER_DISCORD_SUPPORT_URL', $plugin_data[ 'support_uri' ] );

// Prevent loading the plugin if PHP version is not minimum
if ( version_compare( PHP_VERSION, BLNOTIFIER_MIN_PHP_VERSION, '<=' ) ) {
    add_action( 'admin_init', static function() {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    } );
    add_action( 'admin_notices', static function() {
        /* translators: 1: Plugin name, 2: Minimum PHP version */
        $message = sprintf( __( '"%1$s" requires PHP %2$s or newer.', 'broken-link-notifier' ),
            BLNOTIFIER_NAME,
            BLNOTIFIER_MIN_PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>'.esc_html( $message ).'</p></div>';
    } );
    return;
}

// Paths
define( 'BLNOTIFIER_ADMIN_DIR', str_replace( site_url( '/' ), '', rtrim( admin_url(), '/' ) ) );                //: /wp-admin/
define( 'BLNOTIFIER_PLUGIN_DIR', plugins_url( '/'.BLNOTIFIER_TEXTDOMAIN.'/' ) );                                //: https://domain.com/wp-content/plugins/broken-link-notifier/
define( 'BLNOTIFIER_PLUGIN_INCLUDES_PATH', plugin_dir_path( __FILE__ ).'includes/' );                           //: /home/.../public_html/wp-content/plugins/broken-link-notifier/includes/
define( 'BLNOTIFIER_PLUGIN_JS_PATH', str_replace( site_url(), '', BLNOTIFIER_PLUGIN_DIR ).'includes/js/' );     //: /wp-content/plugins/broken-link-notifier/includes/js/
define( 'BLNOTIFIER_PLUGIN_CSS_PATH', str_replace( site_url(), '', BLNOTIFIER_PLUGIN_DIR ).'includes/css/' );   //: /wp-content/plugins/broken-link-notifier/includes/css/
define( 'BLNOTIFIER_PLUGIN_IMG_PATH', BLNOTIFIER_PLUGIN_DIR.'includes/img/' );                                  //: https://domain.com/wp-content/plugins/broken-link-notifier/includes/img/


/**
 * Load the files
 */
require BLNOTIFIER_PLUGIN_INCLUDES_PATH . 'loader.php';


/**
 * Delete the cache table on plugin deactivation or uninstall.
 */
register_deactivation_hook( __FILE__, 'blnotifier_cleanup_on_shutdown' );
register_uninstall_hook( __FILE__, 'blnotifier_cleanup_on_shutdown' );
function blnotifier_cleanup_on_shutdown() {
    if ( class_exists( 'BLNOTIFIER_CACHE' ) ) {
        $cache = new BLNOTIFIER_CACHE();
        $cache->delete_cache_table();
    }
} // End blnotifier_cleanup_on_shutdown()