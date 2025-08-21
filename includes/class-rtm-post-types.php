<?php
/**
 * Custom Post Types for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_filter('manage_team_member_posts_columns', array($this, 'team_member_columns'));
        add_action('manage_team_member_posts_custom_column', array($this, 'team_member_column_content'), 10, 2);
    }
    
    public function register_post_types() {
        $this->register_team_member_post_type();
    }
    
    public function register_taxonomies() {
        $this->register_research_area_taxonomy();
        $this->register_team_role_taxonomy();
    }
    
    private function register_team_member_post_type() {
        $labels = array(
            'name'                  => _x('Team Members', 'Post Type General Name', 'research-team-manager'),
            'singular_name'         => _x('Team Member', 'Post Type Singular Name', 'research-team-manager'),
            'menu_name'             => __('Team Members', 'research-team-manager'),
            'name_admin_bar'        => __('Team Member', 'research-team-manager'),
            'archives'              => __('Team Member Archives', 'research-team-manager'),
            'attributes'            => __('Team Member Attributes', 'research-team-manager'),
            'parent_item_colon'     => __('Parent Team Member:', 'research-team-manager'),
            'all_items'             => __('All Team Members', 'research-team-manager'),
            'add_new_item'          => __('Add New Team Member', 'research-team-manager'),
            'add_new'               => __('Add New', 'research-team-manager'),
            'new_item'              => __('New Team Member', 'research-team-manager'),
            'edit_item'             => __('Edit Team Member', 'research-team-manager'),
            'update_item'           => __('Update Team Member', 'research-team-manager'),
            'view_item'             => __('View Team Member', 'research-team-manager'),
            'view_items'            => __('View Team Members', 'research-team-manager'),
            'search_items'          => __('Search Team Member', 'research-team-manager'),
            'not_found'             => __('Not found', 'research-team-manager'),
            'not_found_in_trash'    => __('Not found in Trash', 'research-team-manager'),
            'featured_image'        => __('Profile Picture', 'research-team-manager'),
            'set_featured_image'    => __('Set profile picture', 'research-team-manager'),
            'remove_featured_image' => __('Remove profile picture', 'research-team-manager'),
            'use_featured_image'    => __('Use as profile picture', 'research-team-manager'),
            'insert_into_item'      => __('Insert into team member', 'research-team-manager'),
            'uploaded_to_this_item' => __('Uploaded to this team member', 'research-team-manager'),
            'items_list'            => __('Team members list', 'research-team-manager'),
            'items_list_navigation' => __('Team members list navigation', 'research-team-manager'),
            'filter_items_list'     => __('Filter team members list', 'research-team-manager'),
        );
        
        $args = array(
            'label'                 => __('Team Member', 'research-team-manager'),
            'description'           => __('Research team members', 'research-team-manager'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt'),
            'taxonomies'            => array('research_area', 'team_role'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-groups',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        );
        
        register_post_type('team_member', $args);
    }
    
    private function register_research_area_taxonomy() {
        $labels = array(
            'name'                       => _x('Research Areas', 'Taxonomy General Name', 'research-team-manager'),
            'singular_name'              => _x('Research Area', 'Taxonomy Singular Name', 'research-team-manager'),
            'menu_name'                  => __('Research Areas', 'research-team-manager'),
            'all_items'                  => __('All Research Areas', 'research-team-manager'),
            'parent_item'                => __('Parent Research Area', 'research-team-manager'),
            'parent_item_colon'          => __('Parent Research Area:', 'research-team-manager'),
            'new_item_name'              => __('New Research Area Name', 'research-team-manager'),
            'add_new_item'               => __('Add New Research Area', 'research-team-manager'),
            'edit_item'                  => __('Edit Research Area', 'research-team-manager'),
            'update_item'                => __('Update Research Area', 'research-team-manager'),
            'view_item'                  => __('View Research Area', 'research-team-manager'),
            'separate_items_with_commas' => __('Separate research areas with commas', 'research-team-manager'),
            'add_or_remove_items'        => __('Add or remove research areas', 'research-team-manager'),
            'choose_from_most_used'      => __('Choose from the most used', 'research-team-manager'),
            'popular_items'              => __('Popular Research Areas', 'research-team-manager'),
            'search_items'               => __('Search Research Areas', 'research-team-manager'),
            'not_found'                  => __('Not Found', 'research-team-manager'),
            'no_terms'                   => __('No research areas', 'research-team-manager'),
            'items_list'                 => __('Research areas list', 'research-team-manager'),
            'items_list_navigation'      => __('Research areas list navigation', 'research-team-manager'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
        );
        
        register_taxonomy('research_area', array('team_member'), $args);
    }
    
    private function register_team_role_taxonomy() {
        $labels = array(
            'name'                       => _x('Team Roles', 'Taxonomy General Name', 'research-team-manager'),
            'singular_name'              => _x('Team Role', 'Taxonomy Singular Name', 'research-team-manager'),
            'menu_name'                  => __('Team Roles', 'research-team-manager'),
            'all_items'                  => __('All Team Roles', 'research-team-manager'),
            'parent_item'                => __('Parent Team Role', 'research-team-manager'),
            'parent_item_colon'          => __('Parent Team Role:', 'research-team-manager'),
            'new_item_name'              => __('New Team Role Name', 'research-team-manager'),
            'add_new_item'               => __('Add New Team Role', 'research-team-manager'),
            'edit_item'                  => __('Edit Team Role', 'research-team-manager'),
            'update_item'                => __('Update Team Role', 'research-team-manager'),
            'view_item'                  => __('View Team Role', 'research-team-manager'),
            'separate_items_with_commas' => __('Separate team roles with commas', 'research-team-manager'),
            'add_or_remove_items'        => __('Add or remove team roles', 'research-team-manager'),
            'choose_from_most_used'      => __('Choose from the most used', 'research-team-manager'),
            'popular_items'              => __('Popular Team Roles', 'research-team-manager'),
            'search_items'               => __('Search Team Roles', 'research-team-manager'),
            'not_found'                  => __('Not Found', 'research-team-manager'),
            'no_terms'                   => __('No team roles', 'research-team-manager'),
            'items_list'                 => __('Team roles list', 'research-team-manager'),
            'items_list_navigation'      => __('Team roles list navigation', 'research-team-manager'),
        );
        
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => true,
            'public'                     => true,
            'show_ui'                    => true,
            'show_admin_column'          => true,
            'show_in_nav_menus'          => true,
            'show_tagcloud'              => true,
            'show_in_rest'               => true,
        );
        
        register_taxonomy('team_role', array('team_member'), $args);
    }
    
    public function team_member_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['profile_picture'] = __('Profile Picture', 'research-team-manager');
        $new_columns['position'] = __('Position', 'research-team-manager');
        $new_columns['email'] = __('Email', 'research-team-manager');
        $new_columns['research_area'] = $columns['taxonomy-research_area'];
        $new_columns['team_role'] = $columns['taxonomy-team_role'];
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    public function team_member_column_content($column, $post_id) {
        switch ($column) {
            case 'profile_picture':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(50, 50));
                } else {
                    echo '<div class="rtm-no-image">No Image</div>';
                }
                break;
                
            case 'position':
                $position = get_post_meta($post_id, '_rtm_position', true);
                echo esc_html($position);
                break;
                
            case 'email':
                $email = get_post_meta($post_id, '_rtm_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                break;
        }
    }
}