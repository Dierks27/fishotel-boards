<?php
/**
 * FH Boards – Clean uninstall.
 *
 * Only removes forum data if the admin has explicitly opted in via
 * Settings > FH Boards > "Delete all data on uninstall".
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-fhb-constants.php';

$delete_data = get_option( 'fhb_delete_data_on_uninstall', false );

if ( $delete_data ) {
    global $wpdb;

    // Drop custom tables.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . FHB_Constants::TABLE_THREAD_VISITS );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . FHB_Constants::TABLE_NOTIFICATIONS );

    // Delete all fhb_topic posts and their meta.
    $topics = get_posts( array(
        'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );
    foreach ( $topics as $topic_id ) {
        wp_delete_post( $topic_id, true );
    }

    // Delete all fhb_reply posts and their meta.
    $replies = get_posts( array(
        'post_type'      => FHB_Constants::POST_TYPE_REPLY,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );
    foreach ( $replies as $reply_id ) {
        wp_delete_post( $reply_id, true );
    }

    // Remove user meta for notification opt-in.
    delete_metadata( 'user', 0, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, '', true );

    // Remove plugin options.
    delete_option( 'fhb_db_version' );
    delete_option( 'fhb_delete_data_on_uninstall' );
}

// Always unschedule cron.
$timestamp = wp_next_scheduled( FHB_Constants::CRON_HOOK );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, FHB_Constants::CRON_HOOK );
}
