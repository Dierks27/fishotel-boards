<?php
/**
 * FHB_Updater – Check GitHub releases for plugin updates.
 *
 * Hooks into the WordPress update system so the plugin can be updated
 * directly from GitHub releases, no wordpress.org listing required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Updater {

    /** GitHub owner/repo. */
    private static $repo = 'Dierks27/fishotel-boards';

    /** Plugin basename (e.g. fh-boards/fh-boards.php). */
    private static $plugin_basename;

    /** Plugin slug (directory name). */
    private static $slug = 'fh-boards';

    /** Transient key for caching the GitHub response. */
    private static $cache_key = 'fhb_github_update';

    /** Cache lifetime in seconds (12 hours). */
    private static $cache_ttl = 43200;

    public static function init() {
        self::$plugin_basename = plugin_basename( FHB_PLUGIN_DIR . 'fh-boards.php' );

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
    }

    /**
     * Query the GitHub API for the latest release. Result is cached.
     */
    private static function fetch_release() {
        $cached = get_transient( self::$cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $url      = 'https://api.github.com/repos/' . self::$repo . '/releases/latest';
        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $release['tag_name'] ) ) {
            return false;
        }

        set_transient( self::$cache_key, $release, self::$cache_ttl );

        return $release;
    }

    /**
     * Extract a clean version string from the release tag (strip leading "v").
     */
    private static function tag_to_version( $tag ) {
        return ltrim( $tag, 'vV' );
    }

    /**
     * Get the best download URL for a release.
     *
     * Prefers a pre-built zip asset named fh-boards*.zip over the
     * GitHub source zipball (which requires directory renaming).
     */
    private static function get_download_url( $release ) {
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( preg_match( '/^fh-boards.*\.zip$/i', $asset['name'] ) ) {
                    return $asset['browser_download_url'];
                }
            }
        }
        return $release['zipball_url'];
    }

    /**
     * Inject update information into the WordPress update transient.
     */
    public static function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = self::fetch_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = self::tag_to_version( $release['tag_name'] );

        if ( version_compare( FHB_VERSION, $remote_version, '<' ) ) {
            $transient->response[ self::$plugin_basename ] = (object) array(
                'slug'        => self::$slug,
                'plugin'      => self::$plugin_basename,
                'new_version' => $remote_version,
                'package'     => self::get_download_url( $release ),
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

        $release = self::fetch_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = self::tag_to_version( $release['tag_name'] );

        return (object) array(
            'name'          => 'FH Boards',
            'slug'          => self::$slug,
            'version'       => $remote_version,
            'author'        => '<a href="https://github.com/' . self::$repo . '">FisHotel</a>',
            'homepage'      => 'https://github.com/' . self::$repo,
            'download_link' => self::get_download_url( $release ),
            'sections'      => array(
                'description' => 'A lightweight private beta tester forum for FisHotel.',
                'changelog'   => nl2br( esc_html( $release['body'] ?? '' ) ),
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
