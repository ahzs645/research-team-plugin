<?php
/**
 * Admin functionality for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Admin {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'setup_cron_jobs'));
        add_filter('plugin_action_links_' . RTM_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    public function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'team_member' || strpos($hook, 'rtm-') !== false) {
            wp_enqueue_style(
                'rtm-admin-style',
                RTM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                RTM_VERSION
            );
            
            wp_enqueue_script(
                'rtm-admin-script',
                RTM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                RTM_VERSION,
                true
            );
            
            wp_localize_script('rtm-admin-script', 'rtm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rtm_ajax_nonce'),
            ));
        }
    }
    
    public function setup_cron_jobs() {
        if (get_option('rtm_enable_auto_sync', false)) {
            $frequency = get_option('rtm_sync_frequency', 'daily');
            
            if (!wp_next_scheduled('rtm_daily_sync')) {
                wp_schedule_event(time(), $frequency, 'rtm_daily_sync');
            }
        } else {
            wp_clear_scheduled_hook('rtm_daily_sync');
        }
    }
    
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=team_member&page=rtm-scholar-settings') . '">' . __('Settings', 'research-team-manager') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}