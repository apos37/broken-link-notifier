<?php
/**
 * Plugin Name:         Broken Link Notifier
 * Plugin URI:          https://github.com/apos37/broken-link-notifier
 * Description:         Get notified when someone loads a page with a broken link
 * Version:             1.0.3.1
 * Requires at least:   5.9.0
 * Tested up to:        6.5
 * Requires PHP:        7.4
 * Author:              Apos37
 * Author URI:          https://apos37.com/
 * Text Domain:         broken-link-notifier
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Defines
 */

define( 'BLNOTIFIER_NAME', 'Broken Link Notifier' );
define( 'BLNOTIFIER_TEXTDOMAIN', 'broken-link-notifier' );
define( 'BLNOTIFIER_VERSION', '1.0.3.1' );
define( 'BLNOTIFIER_MIN_PHP_VERSION', '7.4' );
define( 'BLNOTIFIER_AUTHOR_NAME', 'Apos37' );
define( 'BLNOTIFIER_AUTHOR_URL', 'https://apos37.com/' );
define( 'BLNOTIFIER_DISCORD_SUPPORT_URL', 'https://discord.gg/3HnzNEJVnR' );
define( 'BLNOTIFIER_ADMIN_DIR', str_replace( site_url( '/' ), '', rtrim( admin_url(), '/' ) ) );                //: /wp-admin/
define( 'BLNOTIFIER_PLUGIN_DIR', plugins_url( '/'.BLNOTIFIER_TEXTDOMAIN.'/' ) );                                //: https://domain.com/wp-content/plugins/broken-link-notifier/
define( 'BLNOTIFIER_PLUGIN_INCLUDES_PATH', plugin_dir_path( __FILE__ ).'includes/' );                           //: /home/.../public_html/wp-content/plugins/broken-link-notifier/includes/
define( 'BLNOTIFIER_PLUGIN_JS_PATH', str_replace( site_url(), '', BLNOTIFIER_PLUGIN_DIR ).'includes/js/' );     //: /wp-content/plugins/broken-link-notifier/includes/js/
define( 'BLNOTIFIER_PLUGIN_CSS_PATH', str_replace( site_url(), '', BLNOTIFIER_PLUGIN_DIR ).'includes/css/' );   //: /wp-content/plugins/broken-link-notifier/includes/css/
define( 'BLNOTIFIER_PLUGIN_IMG_PATH', BLNOTIFIER_PLUGIN_DIR.'includes/img/' );                                  //: https://domain.com/wp-content/plugins/broken-link-notifier/includes/img/

/**
 * Prevent loading the plugin if PHP version is not minimum
 */
if ( version_compare( PHP_VERSION, BLNOTIFIER_MIN_PHP_VERSION, '<=' ) ) {
   add_action(
       'admin_init',
       static function() {
           deactivate_plugins( plugin_basename( __FILE__ ) );
       }
   );
   add_action(
       'admin_notices',
       static function() {
           echo wp_kses_post(
                sprintf(
                    '<div class="notice notice-error"><p>'.__( '"%s" requires PHP %s or newer.', 'broken-link-notifier' ).'</p></div>',
                    BLNOTIFIER_NAME,
                    BLNOTIFIER_MIN_PHP_VERSION
                )
           );
       }
   );
   return;
}


/**
 * Load the files
 */
require BLNOTIFIER_PLUGIN_INCLUDES_PATH . 'loader.php';