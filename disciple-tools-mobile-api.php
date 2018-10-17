<?php
/**
 * Plugin Name: Disciple Tools - Mobile API
 * Plugin URI: https://github.com/cairocoder01/dt-mobile-api
 * Description: Disciple Tools - Mobile API is intended to help developers and integrator jumpstart their extension
 * of the Disciple Tools system.
 * Version:  0.1.0
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-mobile-api
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 4.9
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

/*******************************************************************
 * Using the Mobile API
 * The Disciple Tools mobile API is intended to accelerate integrations and extensions to the Disciple Tools system.
 * This basic plugin starter has some of the basic elements to quickly launch and extension project in the pattern of
 * the Disciple Tools system.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Gets the instance of the `DT_Mobile_API` class.
 *
 * @since  0.1
 * @access public
 * @return object
 */
function dt_mobile_api() {
    $current_theme = wp_get_theme()->get( "Name" );

    if ( 'Disciple Tools' == $current_theme || dt_is_child_theme_of_disciple_tools() ) {
        return DT_Mobile_API::get_instance();
    }
    else {
        add_action( 'admin_notices', 'dt_mobile_api_hook_admin_notice' );
        add_action( 'wp_ajax_dismissed_notice_handler', 'dt_mobile_api_ajax_notice_handler' );
        return new WP_Error( 'current_theme_not_dt', 'Disciple Tools Theme not active.' );
    }

}
add_action( 'plugins_loaded', 'dt_mobile_api' );

/**
 * Singleton class for setting up the plugin.
 *
 * @since  0.1
 * @access public
 */
class DT_Mobile_API {

    /**
     * Declares public variables
     *
     * @since  0.1
     * @access public
     * @return object
     */
    public $token;
    public $version;
    public $dir_path = '';
    public $dir_uri = '';
    public $img_uri = '';
    public $includes_path;

    /**
     * Returns the instance.
     *
     * @since  0.1
     * @access public
     * @return object
     */
    public static function get_instance() {

        static $instance = null;

        if ( is_null( $instance ) ) {
            $instance = new dt_mobile_api();
            $instance->setup();
            $instance->includes();
            $instance->setup_actions();
        }
        return $instance;
    }

    /**
     * Constructor method.
     *
     * @since  0.1
     * @access private
     * @return void
     */
    private function __construct() {
    }

    /**
     * Loads files needed by the plugin.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    private function includes() {
//        require_once( 'includes/admin/admin-menu-and-tabs.php' );
        require_once('includes/disciple-tools-mobile-api-endpoints.php');
    }

    /**
     * Sets up globals.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    private function setup() {

        // Main plugin directory path and URI.
        $this->dir_path     = trailingslashit( plugin_dir_path( __FILE__ ) );
        $this->dir_uri      = trailingslashit( plugin_dir_url( __FILE__ ) );

        // Plugin directory paths.
        $this->includes_path      = trailingslashit( $this->dir_path . 'includes' );

        // Plugin directory URIs.
        $this->img_uri      = trailingslashit( $this->dir_uri . 'img' );

        // Admin and settings variables
        $this->token             = 'dt_mobile_api';
        $this->version             = '0.1';
    }

    /**
     * Sets up main plugin actions and filters.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    private function setup_actions() {

        // Check for plugin updates
        /*if ( ! class_exists( 'Puc_v4_Factory' ) ) {
            require( $this->includes_path . 'admin/libraries/plugin-update-checker/plugin-update-checker.php' );
        }*/
        /**
         * Below is the publicly hosted .json file that carries the version information. This file can be hosted
         * anywhere as long as it is publicly accessible. You can download the version file listed below and use it as
         * a template.
         * Also, see the instructions for version updating to understand the steps involved.
         * @see https://github.com/DiscipleTools/disciple-tools-version-control/wiki/How-to-Update-the-Starter-Plugin
         */
        /*Puc_v4_Factory::buildUpdateChecker(
            'https://raw.githubusercontent.com/DiscipleTools/disciple-tools-version-control/master/disciple-tools-mobile-api-version-control.json',
            __FILE__,
            'disciple-tools-mobile-api'
        );*/

        // Internationalize the text strings used.
        add_action( 'plugins_loaded', array( $this, 'i18n' ), 2 );
    }

    /**
     * Method that runs only when the plugin is activated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function activation() {

        // Confirm 'Administrator' has 'manage_dt' privilege. This is key in 'remote' configuration when
        // Disciple Tools theme is not installed, otherwise this will already have been installed by the Disciple Tools Theme
        $role = get_role( 'administrator' );
        if ( !empty( $role ) ) {
            $role->add_cap( 'manage_dt' ); // gives access to dt plugin options
        }

    }

    /**
     * Method that runs only when the plugin is deactivated.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public static function deactivation() {
        delete_option( 'dismissed-dt-starter' );
    }

    /**
     * Loads the translation files.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function i18n() {
        load_plugin_textdomain( 'dt_mobile_api', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ). 'languages' );
    }

    /**
     * Magic method to output a string if trying to use the object as a string.
     *
     * @since  0.1
     * @access public
     * @return string
     */
    public function __toString() {
        return 'dt_mobile_api';
    }

    /**
     * Magic method to keep the object from being cloned.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Whoah, partner!', 'dt_mobile_api' ), '0.1' );
    }

    /**
     * Magic method to keep the object from being unserialized.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Whoah, partner!', 'dt_mobile_api' ), '0.1' );
    }

    /**
     * Magic method to prevent a fatal error when calling a method that doesn't exist.
     *
     * @since  0.1
     * @access public
     * @return null
     */
    public function __call( $method = '', $args = array() ) {
        // @codingStandardsIgnoreLine
        _doing_it_wrong( "dt_mobile_api::{$method}", esc_html__( 'Method does not exist.', 'dt_mobile_api' ), '0.1' );
        unset( $method, $args );
        return null;
    }
}
// end main plugin class

// Register activation hook.
register_activation_hook( __FILE__, [ 'DT_Mobile_API', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'DT_Mobile_API', 'deactivation' ] );

/**
 * Admin alert for when Disciple Tools Theme is not available
 */
function dt_mobile_api_no_disciple_tools_theme_found()
{
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( "'Disciple Tools - Mobile API' requires 'Disciple Tools' theme to work. Please activate 'Disciple Tools' theme or deactivate 'Disciple Tools - Mobile API' plugin.", "dt_mobile_api" ); ?></p>
    </div>
    <?php
}

/**
 * A simple function to assist with development and non-disruptive debugging.
 * -----------
 * -----------
 * REQUIREMENT:
 * WP Debug logging must be set to true in the wp-config.php file.
 * Add these definitions above the "That's all, stop editing! Happy blogging." line in wp-config.php
 * -----------
 * define( 'WP_DEBUG', true ); // Enable WP_DEBUG mode
 * define( 'WP_DEBUG_LOG', true ); // Enable Debug logging to the /wp-content/debug.log file
 * define( 'WP_DEBUG_DISPLAY', false ); // Disable display of errors and warnings
 * @ini_set( 'display_errors', 0 );
 * -----------
 * -----------
 * EXAMPLE USAGE:
 * (string)
 * write_log('THIS IS THE START OF MY CUSTOM DEBUG');
 * -----------
 * (array)
 * $an_array_of_things = ['an', 'array', 'of', 'things'];
 * write_log($an_array_of_things);
 * -----------
 * (object)
 * $an_object = new An_Object
 * write_log($an_object);
 */
if ( !function_exists( 'dt_write_log' ) ) {
    /**
     * A function to assist development only.
     * This function allows you to post a string, array, or object to the WP_DEBUG log.
     *
     * @param $log
     */
    function dt_write_log( $log )
    {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}

if ( ! function_exists( 'dt_is_child_theme_of_disciple_tools' ) ) {
    /**
     * Returns true if this is a child theme of Disciple Tools, and false if it is not.
     *
     * @return bool
     */
    function dt_is_child_theme_of_disciple_tools() : bool {
        if ( get_template_directory() !== get_stylesheet_directory() ) {
            $current_theme = wp_get_theme();
            if ( 'disciple-tools-theme' == $current_theme->get( 'Template' ) ) {
                return true;
            }
        }
        return false;
    }
}

function dt_mobile_api_hook_admin_notice() {
    // Check if it's been dismissed...
    if ( ! get_option( 'dismissed-dt-mobile-api', false ) ) {
        // multiple dismissible notice states ?>
        <div class="notice notice-error notice-dt-mobile-api is-dismissible" data-notice="dt-demo">
            <p><?php esc_html_e( "'Disciple Tools - Mobile API' requires 'Disciple Tools' theme to work. Please activate 'Disciple Tools' theme or deactivate 'Disciple Tools - Mobile API'." ); ?></p>
        </div>
        <script>
            jQuery(function($) {
                $( document ).on( 'click', '.notice-dt-starter .notice-dismiss', function () {
                    let type = $( this ).closest( '.notice-dt-starter' ).data( 'notice' );
                    $.ajax( ajaxurl,
                        {
                            type: 'POST',
                            data: {
                                action: 'dismissed_notice_handler',
                                type: type,
                            }
                        } );
                } );
            });
        </script>

    <?php }
}

/**
 * AJAX handler to store the state of dismissible notices.
 */
function dt_mobile_api_ajax_notice_handler() {
    $type = 'dt-mobile-api';
    update_option( 'dismissed-' . $type, true );
}
