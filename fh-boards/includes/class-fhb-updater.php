<?php
/**
 * FHB_Updater – Check GitHub for plugin updates.
 *
 * Reads the Version header directly from fh-boards.php on the main
 * branch—no GitHub releases required. Push a version bump to main
 * and WordPress will see the update.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Updater {

    /** GitHub owner/repo. */
    private static $repo = 'Dierks27/fishotel-boards';

    /** Branch to check for updates. */
    private static $branch = 'main';

    /** Path to the main plugin file inside the repo. */
    private static $remote_plugin_file = 'fh-boards/fh-boards.php';

    /** Plugin basename (e.g. fh-boards/fh-boards.php). */
    private static $plugin_basename;

    /** Plugin slug (directory name). */
    private static $slug = 'fh-boards';

    /** Transient key for caching the remote version check. */
    private static $cache_key = 'fhb_github_update';

    /** Cache lifetime in seconds (6 hours). */
    private static $cache_ttl = 21600;

    public static function init() {
        self::$plugin_basename = plugin_basename( FHB_PLUGIN_DIR . 'fh-boards.php' );

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
    }

    /**
     * Fetch the remote version from the main branch. Result is cached.
     *
     * Returns an array with 'version' and 'download_url', or false on failure.
     */
    private static function fetch_remote_version() {
        $cached = get_transient( self::$cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Fetch the raw plugin header file from the main branch.
        $raw_url  = 'https://raw.githubusercontent.com/' . self::$repo . '/' . self::$branch . '/' . self::$remote_plugin_file;
        $response = wp_remote_get( $raw_url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );

        // Parse the Version header from the file contents.
        if ( ! preg_match( '/^\s*\*?\s*Version:\s*(.+)$/mi', $body, $matches ) ) {
            return false;
        }

        $remote_version = trim( $matches[1] );
        $download_url   = 'https://api.github.com/repos/' . self::$repo . '/zipball/' . self::$branch;

        $data = array(
            'version'      => $remote_version,
            'download_url' => $download_url,
        );

        set_transient( self::$cache_key, $data, self::$cache_ttl );

        return $data;
    }

    /**
     * Clear the cached remote version (called when the user clicks
     * "Check for Updates" on the plugins page).
     */
    public static function clear_cache() {
        delete_transient( self::$cache_key );
    }

    /**
     * Inject update information into the WordPress update transient.
     */
    public static function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = self::fetch_remote_version();
        if ( ! $remote ) {
            return $transient;
        }

        if ( version_compare( FHB_VERSION, $remote['version'], '<' ) ) {
            $transient->response[ self::$plugin_basename ] = (object) array(
                'slug'        => self::$slug,
                'plugin'      => self::$plugin_basename,
                'new_version' => $remote['version'],
                'package'     => $remote['download_url'],
                'url'         => 'https://github.com/' . self::$repo,
            );
        }

        return $transient;
    }

    /**
     * Provide plugin details for the "View Details" modal in the dashboard.
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || self::$slug !== ( $args->slug ?? '' ) ) {
            return $result;
        }

        $remote = self::fetch_remote_version();
        if ( ! $remote ) {
            return $result;
        }

        return (object) array(
            'name'          => 'FH Boards',
            'slug'          => self::$slug,
            'version'       => $remote['version'],
            'author'        => '<a href="https://github.com/' . self::$repo . '">FisHotel</a>',
            'homepage'      => 'https://github.com/' . self::$repo,
            'download_link' => $remote['download_url'],
            'sections'      => array(
                'description' => 'A lightweight private beta tester forum for FisHotel.',
                'changelog'   => 'See the <a href="https://github.com/' . self::$repo . '/commits/main">commit history</a> for changes.',
            ),
        );
    }

    /**
     * After extraction, rename the GitHub zip directory to match the
     * expected plugin slug (fh-boards/).
     *
     * GitHub source zips extract to a folder like:
     *   Dierks27-fishotel-boards-abc1234/fh-boards/
     *
     * WordPress expects:
     *   fh-boards/
     */
    public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( ! isset( $hook_extra['plugin'] ) || self::$plugin_basename !== $hook_extra['plugin'] ) {
            return $source;
        }

        global $wp_filesystem;

        // Look for the fh-boards/ directory inside the extracted source.
        $nested = trailingslashit( $source ) . self::$slug . '/';

        if ( $wp_filesystem->is_dir( $nested ) ) {
            $corrected = trailingslashit( $remote_source ) . self::$slug . '/';
            $wp_filesystem->move( $nested, $corrected );
            // Clean up the now-empty wrapper directory.
            $wp_filesystem->delete( $source, true );
            return $corrected;
        }

        return $source;
    }
}
