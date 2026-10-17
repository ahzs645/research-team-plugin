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
    'rtm_network_enabled',
    'rtm_team_menus',
    'rtm_team_menu_label',
    'rtm_team_menu_teams',
    'rtm_team_access',
);
foreach ($rtm_options as $rtm_option) {
    delete_option($rtm_option);
}

// Remove the team manager (PI) role and member capabilities.
remove_role('rtm_team_manager');
foreach (wp_roles()->role_objects as $rtm_role) {
    foreach (array('edit_rtm_members', 'edit_others_rtm_members', 'edit_private_rtm_members', 'edit_published_rtm_members', 'publish_rtm_members', 'read_private_rtm_members', 'delete_rtm_members', 'delete_private_rtm_members', 'delete_published_rtm_members', 'delete_others_rtm_members', 'rtm_manage_all_teams', 'rtm_manage_publications') as $rtm_cap) {
        $rtm_role->remove_cap($rtm_cap);
    }
}
delete_option('rtm_caps_version');
delete_metadata('user', 0, 'rtm_managed_teams', '', true);

// Drop the custom publications table.
$rtm_table = $wpdb->prefix . 'rtm_publications';
$wpdb->query("DROP TABLE IF EXISTS {$rtm_table}");
