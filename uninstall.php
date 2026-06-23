<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Removes the plugin's own options and custom table. Team member posts and
 * their meta/terms are intentionally left intact — they are user content and
 * deleting them on uninstall would be surprising and unrecoverable.
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

// Guard against direct access — WordPress defines this only during uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options.
$rtm_options = array(
    'rtm_team_mode',
    'rtm_default_team',
    'rtm_google_scholar_user_id',
);
foreach ($rtm_options as $rtm_option) {
    delete_option($rtm_option);
}

// Drop the custom publications table.
$rtm_table = $wpdb->prefix . 'rtm_publications';
$wpdb->query("DROP TABLE IF EXISTS {$rtm_table}");
