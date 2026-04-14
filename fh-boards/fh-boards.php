<?php
/**
 * Plugin Name: FH Boards
 * Description: A lightweight private beta tester forum for FisHotel.
 * Version:     1.7.0
 * Author:      FisHotel
 * Text Domain: fh-boards
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FHB_VERSION', '1.7.0' );
define( 'FHB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FHB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------
 * Includes
 * ----------------------------------------------------------------*/
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-constants.php';
require_once FHB_PLUGIN_DIR . 'includes/fhb-helpers.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-activator.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-post-types.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-shortcode.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-ajax.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-notifications.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-user-profile.php';
require_once FHB_PLUGIN_DIR . 'includes/class-fhb-updater.php';

if ( is_admin() ) {
    require_once FHB_PLUGIN_DIR . 'admin/class-fhb-admin.php';
}

/* ------------------------------------------------------------------
 * Activation / Deactivation
 * ----------------------------------------------------------------*/
register_activation_hook( __FILE__, array( 'FHB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FHB_Activator', 'deactivate' ) );

/* ------------------------------------------------------------------
 * Initialise components
 * ----------------------------------------------------------------*/
function fhb_init() {
    FHB_Post_Types::register();
    FHB_Shortcode::init();
    FHB_Ajax::init();
    FHB_Notifications::init();
    FHB_User_Profile::init();

    if ( is_admin() ) {
        FHB_Admin::init();
        FHB_Updater::init();
    }
}
add_action( 'init', 'fhb_init' );

/* ------------------------------------------------------------------
 * Enqueue public assets
 * ----------------------------------------------------------------*/
function fhb_enqueue_public_assets() {
    wp_enqueue_style(
        'fhb-public',
        FHB_PLUGIN_URL . 'public/css/fhb-public.css',
        array(),
        FHB_VERSION
    );
    wp_enqueue_script(
        'fhb-public',
        FHB_PLUGIN_URL . 'public/js/fhb-public.js',
        array( 'jquery' ),
        FHB_VERSION,
        true
    );
    wp_localize_script( 'fhb-public', 'fhb_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( FHB_Constants::NONCE_ACTION ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'fhb_enqueue_public_assets' );

/* ------------------------------------------------------------------
 * Plugin row meta – "Check for Updates" link on Plugins page.
 * Clears the GitHub version cache so the check is fresh.
 * ----------------------------------------------------------------*/
function fhb_plugin_row_meta( $links, $file ) {
    if ( plugin_basename( __FILE__ ) === $file ) {
        $url     = wp_nonce_url( self_admin_url( 'update-core.php?force-check=1&fhb_clear_cache=1' ), 'fhb_clear_cache' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for Updates', 'fh-boards' ) . '</a>';
    }
    return $links;
}
add_filter( 'plugin_row_meta', 'fhb_plugin_row_meta', 10, 2 );

/**
 * Clear the GitHub update cache when "Check for Updates" is clicked.
 */
function fhb_maybe_clear_update_cache() {
    if ( isset( $_GET['fhb_clear_cache'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'fhb_clear_cache' ) ) {
        FHB_Updater::clear_cache();
    }
}
add_action( 'admin_init', 'fhb_maybe_clear_update_cache' );

/* ------------------------------------------------------------------
 * Enqueue admin assets
 * ----------------------------------------------------------------*/
function fhb_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'fh-boards' ) === false ) {
        return;
    }
    wp_enqueue_style(
        'fhb-admin',
        FHB_PLUGIN_URL . 'admin/css/fhb-admin.css',
        array(),
        FHB_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'fhb_enqueue_admin_assets' );
