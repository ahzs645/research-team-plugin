<?php
/**
 * REST API and Query Extensions for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_REST_API {
    
    public function __construct() {
        add_action('init', array($this, 'register_meta_fields'));
        add_action('rest_api_init', array($this, 'register_rest_fields'));
        add_filter('rest_team_member_query', array($this, 'modify_rest_query'), 10, 2);
        add_action('pre_get_posts', array($this, 'modify_query_orderby'));
        add_filter('query_loop_block_query_vars', array($this, 'modify_query_loop_vars'), 10, 3);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_modifications'));
    }
    
    /**
     * Register meta fields for REST API visibility
     */
    public function register_meta_fields() {
        $meta_fields = array(
            '_rtm_start_date' => array(
                'type' => 'string',
                'description' => 'Date the member joined the team',
                'single' => true,
                'show_in_rest' => true,
            ),
            '_rtm_end_date' => array(
                'type' => 'string', 
                'description' => 'Date the member left the team',
                'single' => true,
                'show_in_rest' => true,
            ),
            '_rtm_date_priority' => array(
                'type' => 'boolean',
                'description' => 'Use date-based ordering priority',
                'single' => true,
                'show_in_rest' => true,
            ),
            '_rtm_position' => array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ),
            '_rtm_department' => array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ),
            '_rtm_order' => array(
                'type' => 'integer',
                'single' => true,
                'show_in_rest' => true,
            ),
        );
        
        foreach ($meta_fields as $meta_key => $args) {
            register_post_meta('team_member', $meta_key, $args);
        }
    }
    
    /**
     * Register REST API fields
     */
    public function register_rest_fields() {
        // Add computed fields for easier access
        register_rest_field('team_member', 'joined_date', array(
            'get_callback' => function($post) {
                return get_post_meta($post['id'], '_rtm_start_date', true);
            },
            'schema' => array(
                'type' => 'string',
                'description' => 'Date joined the team',
            ),
        ));
        
        register_rest_field('team_member', 'left_date', array(
            'get_callback' => function($post) {
                return get_post_meta($post['id'], '_rtm_end_date', true);
            },
            'schema' => array(
                'type' => 'string',
                'description' => 'Date left the team',
            ),
        ));
        
        register_rest_field('team_member', 'use_date_priority', array(
            'get_callback' => function($post) {
                return (bool) get_post_meta($post['id'], '_rtm_date_priority', true);
            },
            'schema' => array(
                'type' => 'boolean',
                'description' => 'Use date-based ordering priority',
            ),
        ));
        
    }
    
    /**
     * Modify REST API queries
     */
    public function modify_rest_query($args, $request) {
        // Check if custom orderby is requested
        if (isset($request['orderby'])) {
            switch($request['orderby']) {
                case 'joined_date':
                case 'start_date':
                    $args['meta_key'] = '_rtm_start_date';
                    $args['orderby'] = 'meta_value';
                    $args['type'] = 'DATE';
                    break;
                    
                case 'left_date':
                case 'end_date':
                    $args['meta_key'] = '_rtm_end_date';
                    $args['orderby'] = 'meta_value';
                    $args['type'] = 'DATE';
                    break;
                    
                case 'date_priority':
                    // Sort by date priority first, then display order, then start date
                    $args['meta_query'] = array(
                        'relation' => 'AND',
                        'date_priority' => array(
                            'key' => '_rtm_date_priority',
                            'compare' => 'EXISTS',
                        ),
                        'display_order' => array(
                            'key' => '_rtm_order',
                            'compare' => 'EXISTS',
                        ),
                        'start_date' => array(
                            'key' => '_rtm_start_date',
                            'compare' => 'EXISTS',
                        ),
                    );
                    $args['orderby'] = array(
                        'date_priority' => 'DESC',
                        'display_order' => 'ASC',
                        'start_date' => 'DESC',
                    );
                    break;
            }
        }
        
        return $args;
    }
    
    /**
     * Modify front-end queries for team members
     */
    public function modify_query_orderby($query) {
        // Only modify team_member queries on the frontend
        if (!is_admin() && $query->is_main_query() && $query->get('post_type') === 'team_member') {
            return;
        }
        
        // Handle block editor queries
        if (!is_admin() && isset($query->query['post_type']) && $query->query['post_type'] === 'team_member') {
            $orderby = $query->get('orderby');
            
            switch($orderby) {
                case 'joined_date':
                case 'start_date':
                    // Don't use meta_key which filters - use custom SQL for sorting
                    global $wpdb;
                    
                    // Generate a unique identifier for this specific query
                    $query_hash = 'rtm_start_' . md5(serialize($query->query_vars));
                    $query->set('_rtm_query_hash', $query_hash);
                    
                    // Add a filter to modify the JOIN clause - only for THIS query
                    add_filter('posts_join', function($join, $wp_query) use ($wpdb, $query_hash) {
                        if ($wp_query->get('_rtm_query_hash') === $query_hash) {
                            if (strpos($join, 'rtm_start') === false) {
                                $join .= " LEFT JOIN {$wpdb->postmeta} AS rtm_start ON ({$wpdb->posts}.ID = rtm_start.post_id AND rtm_start.meta_key = '_rtm_start_date')";
                            }
                        }
                        return $join;
                    }, 10, 2);
                    
                    // Add a filter to modify the ORDER BY clause - only for THIS query
                    add_filter('posts_orderby', function($orderby, $wp_query) use ($query_hash) {
                        if ($wp_query->get('_rtm_query_hash') === $query_hash) {
                            $order = $wp_query->get('order') === 'ASC' ? 'ASC' : 'DESC';
                            // First sort by whether date exists, then by date value, then by post date
                            $orderby = "CASE WHEN rtm_start.meta_value IS NULL OR rtm_start.meta_value = '' THEN 1 ELSE 0 END, rtm_start.meta_value {$order}, post_date {$order}";
                        }
                        return $orderby;
                    }, 10, 2);
                    break;
                    
                case 'left_date':
                case 'end_date':
                    // Don't use meta_key which filters - use custom SQL for sorting
                    global $wpdb;
                    
                    // Generate a unique identifier for this specific query
                    $query_hash = 'rtm_end_' . md5(serialize($query->query_vars));
                    $query->set('_rtm_query_hash', $query_hash);
                    
                    // Add a filter to modify the JOIN clause - only for THIS query
                    add_filter('posts_join', function($join, $wp_query) use ($wpdb, $query_hash) {
                        if ($wp_query->get('_rtm_query_hash') === $query_hash) {
                            if (strpos($join, 'rtm_end') === false) {
                                $join .= " LEFT JOIN {$wpdb->postmeta} AS rtm_end ON ({$wpdb->posts}.ID = rtm_end.post_id AND rtm_end.meta_key = '_rtm_end_date')";
                            }
                        }
                        return $join;
                    }, 10, 2);
                    
                    // Add a filter to modify the ORDER BY clause - only for THIS query
                    add_filter('posts_orderby', function($orderby, $wp_query) use ($query_hash) {
                        if ($wp_query->get('_rtm_query_hash') === $query_hash) {
                            $order = $wp_query->get('order') === 'ASC' ? 'ASC' : 'DESC';
                            // First sort by whether date exists, then by date value, then by post date
                            $orderby = "CASE WHEN rtm_end.meta_value IS NULL OR rtm_end.meta_value = '' THEN 1 ELSE 0 END, rtm_end.meta_value {$order}, post_date {$order}";
                        }
                        return $orderby;
                    }, 10, 2);
                    break;
                    
                case 'date_priority':
                    // Complex sorting with priority - include members without dates
                    $meta_query = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_rtm_date_priority',
                            'compare' => 'NOT EXISTS',
                        ),
                        array(
                            'key' => '_rtm_date_priority',
                            'compare' => 'EXISTS',
                        ),
                    );
                    
                    $query->set('meta_query', $meta_query);
                    $query->set('orderby', array(
                        'meta_value' => 'DESC', // Priority first
                        'meta_value_num' => 'ASC', // Then order
                        'date' => 'DESC', // Then publish date as fallback
                    ));
                    break;
                    
                case 'custom_order':
                    $query->set('meta_key', '_rtm_order');
                    $query->set('orderby', 'meta_value_num');
                    break;
            }
        }
    }
    
    /**
     * Modify Query Loop block variables
     */
    public function modify_query_loop_vars($query, $block, $page) {
        // Only modify team_member queries
        if (!isset($query['post_type']) || $query['post_type'] !== 'team_member') {
            return $query;
        }
        
        // Check for our custom orderby values
        if (isset($query['orderby'])) {
            switch($query['orderby']) {
                case 'start_date':
                case 'joined_date':
                    // Don't add any meta_query or meta_key to avoid filtering
                    // The pre_get_posts filter will handle the sorting via SQL
                    break;
                    
                case 'left_date': 
                case 'end_date':
                    // Don't add any meta_query or meta_key to avoid filtering
                    // The pre_get_posts filter will handle the sorting via SQL
                    break;
                    
                case 'date_priority':
                    $query['meta_query'] = array(
                        'relation' => 'AND',
                        'date_priority' => array(
                            'key' => '_rtm_date_priority',
                            'compare' => 'EXISTS',
                        ),
                        'display_order' => array(
                            'key' => '_rtm_order',
                            'type' => 'NUMERIC',
                            'compare' => 'EXISTS',
                        ),
                        'start_date' => array(
                            'key' => '_rtm_start_date',
                            'type' => 'DATE',
                            'compare' => 'EXISTS',
                        ),
                    );
                    $query['orderby'] = array(
                        'date_priority' => 'DESC',
                        'display_order' => 'ASC',
                        'start_date' => 'DESC',
                    );
                    break;
                    
                case 'custom_order':
                    $query['meta_key'] = '_rtm_order';
                    $query['orderby'] = 'meta_value_num';
                    break;
            }
        }
        
        return $query;
    }
    
    /**
     * Enqueue script to add orderby options in editor
     */
    public function enqueue_editor_modifications() {
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            // Add custom orderby options to Query Loop blocks
            if (window.wp && window.wp.data) {
                const { subscribe, select } = wp.data;
                
                // Watch for block selection changes
                subscribe(() => {
                    const selectedBlock = select('core/block-editor').getSelectedBlock();
                    
                    if (selectedBlock && selectedBlock.name === 'core/query') {
                        const postType = selectedBlock.attributes.query?.postType;
                        
                        if (postType === 'team_member') {
                            // Add a note in console for developers
                            console.log('Team Member Query Block - Available orderBy options:', {
                                'date': 'Default (Publish Date)',
                                'title': 'Title',
                                'start_date': 'Date Joined',
                                'end_date': 'Date Left', 
                                'date_priority': 'Priority + Date Order',
                                'custom_order': 'Custom Display Order'
                            });
                        }
                    }
                });
            }
        });
        ";
        
        wp_add_inline_script('wp-blocks', $script);
    }
}

// Initialize the REST API class
new RTM_REST_API();