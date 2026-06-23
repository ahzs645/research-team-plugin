<?php
/**
 * Plugin Name: Research Team Manager Simple
 * Plugin URI: https://example.com/research-team-manager
 * Description: A comprehensive plugin to manage research team members and publications with Google Scholar integration. Supports single-team and multiple-teams (one page per lab) modes.
 * Version: 1.3.0
 * Author: Research Team Manager
 * License: GPL v2 or later
 * Text Domain: research-team-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('RTM_VERSION', '1.3.0');
define('RTM_PLUGIN_FILE', __FILE__);
define('RTM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RTM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RTM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/* -------------------------------------------------------------------------
 * Team mode helpers
 *
 * The plugin runs in one of two modes:
 *   - 'single'   : one implicit team (the original behaviour). The team UI is
 *                  hidden and publications/Scholar ID use the global option.
 *   - 'multiple' : many labs, each a `research_team` term with its own page,
 *                  settings and publications.
 * ---------------------------------------------------------------------- */

/**
 * Get the current team mode ('single' or 'multiple'). Defaults to 'multiple'.
 */
function rtm_get_team_mode() {
    $mode = get_option('rtm_team_mode', 'multiple');
    return in_array($mode, array('single', 'multiple'), true) ? $mode : 'multiple';
}

/**
 * Whether the plugin is running in multiple-teams mode.
 */
function rtm_is_multiple_teams() {
    return rtm_get_team_mode() === 'multiple';
}

/**
 * The active team term in single mode, or null for "all teams" / multiple mode.
 *
 * @return WP_Term|null
 */
function rtm_get_active_team() {
    if (rtm_is_multiple_teams()) {
        return null;
    }
    $term_id = (int) get_option('rtm_default_team', 0);
    if ($term_id) {
        $term = get_term($term_id, 'rtm_research_team');
        if ($term && !is_wp_error($term)) {
            return $term;
        }
    }
    return null;
}

/**
 * Resolve the Google Scholar user ID to use for a given team.
 *
 * Per-lab Scholar IDs are stored in the `rtm_team_scholar_id` term meta; when a
 * team has none (or in single mode) we fall back to the global option.
 *
 * @param int $team_id research_team term ID, or 0 for the global fallback.
 * @return string
 */
function rtm_get_scholar_id_for_team($team_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id) {
        $scholar_id = get_term_meta($team_id, 'rtm_team_scholar_id', true);
        if (!empty($scholar_id)) {
            return $scholar_id;
        }
    }
    return get_option('rtm_google_scholar_user_id', '');
}

/**
 * Resolve a team to a term ID for front-end display contexts.
 *
 * Order of precedence: an explicit shortcode value (slug or numeric ID) →
 * the current `research_team` archive → the single-mode active team.
 * Returns 0 when no specific team applies (i.e. show everything).
 *
 * @param string|int $team Slug, term ID, or '' to auto-detect.
 * @return int
 */
function rtm_resolve_team_id($team = '') {
    if (!empty($team)) {
        if (is_numeric($team)) {
            return (int) $team;
        }
        $term = get_term_by('slug', sanitize_title($team), 'rtm_research_team');
        return ($term && !is_wp_error($term)) ? (int) $term->term_id : 0;
    }

    if (is_tax('rtm_research_team')) {
        $obj = get_queried_object();
        if ($obj instanceof WP_Term) {
            return (int) $obj->term_id;
        }
    }

    $active = rtm_get_active_team();
    return $active ? (int) $active->term_id : 0;
}

/**
 * Discipline themes used to group labs. Keyed by slug (stored in the
 * `rtm_team_theme` term meta); the order here is the display order.
 *
 * @return array slug => label
 */
function rtm_team_themes() {
    return array(
        'physical-sciences-engineering'     => __('Physical Sciences, Engineering & Technology', 'research-team-manager'),
        'natural-resources-environment'     => __('Natural Resources, Geography & Environment', 'research-team-manager'),
        'health-human-development'          => __('Health & Human Development', 'research-team-manager'),
        'interdisciplinary-data-innovation' => __('Interdisciplinary Innovation & Data Resources', 'research-team-manager'),
    );
}

/**
 * Short labels for the theme filter chips. Falls back to the full label.
 *
 * @return array slug => short label
 */
function rtm_team_theme_short_labels() {
    return array(
        'physical-sciences-engineering'     => __('Physical Sciences', 'research-team-manager'),
        'natural-resources-environment'     => __('Natural Resources', 'research-team-manager'),
        'health-human-development'          => __('Health', 'research-team-manager'),
        'interdisciplinary-data-innovation' => __('Interdisciplinary', 'research-team-manager'),
    );
}

/**
 * The kind of research unit a team is (stored in `rtm_team_type` term meta).
 *
 * @return string[]
 */
function rtm_team_types() {
    return array('Lab', 'Research Group', 'Centre', 'Institute', 'Hub', 'Network', 'Working Group', 'Field Station', 'Facility', 'Program', 'Other');
}

/**
 * Label for a team's website link, based on its `rtm_team_link_type`
 * ('website' = a dedicated lab site, 'info' = a general info page).
 *
 * @param string $type  The stored link type.
 * @param bool   $short Use the compact label (for cards).
 * @return string
 */
function rtm_team_link_label($type, $short = false) {
    if ($type === 'info') {
        return $short ? __('More info', 'research-team-manager') : __('More information', 'research-team-manager');
    }
    return $short ? __('Official site', 'research-team-manager') : __('Official lab website', 'research-team-manager');
}

/**
 * The member post IDs designated as a team's lead(s).
 *
 * @return int[]
 */
function rtm_get_team_lead_ids($term_id) {
    $ids = get_term_meta($term_id, 'rtm_team_lead_ids', true);
    return is_array($ids) ? array_map('intval', $ids) : array();
}

/**
 * Plain-text lead label for a team — the linked members' names for a person
 * lead, the organization name for an org lead (with legacy text fallback).
 */
function rtm_team_lead_text($term_id) {
    if (get_term_meta($term_id, 'rtm_team_lead_type', true) === 'org') {
        return (string) get_term_meta($term_id, 'rtm_team_pi', true);
    }
    $names = array();
    foreach (rtm_get_team_lead_ids($term_id) as $id) {
        $name = get_the_title($id);
        if ($name) {
            $names[] = $name;
        }
    }
    if ($names) {
        return implode(', ', $names);
    }
    return (string) get_term_meta($term_id, 'rtm_team_pi', true); // legacy free-text fallback
}

// Plugin activation hook
register_activation_hook(__FILE__, 'rtm_activate_plugin');
function rtm_activate_plugin() {
    // Create database table
    rtm_create_publications_table();
    
    // Register post type
    rtm_register_team_member_post_type();
    
    // Create default member status terms
    rtm_create_default_member_status_terms();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'rtm_deactivate_plugin');
function rtm_deactivate_plugin() {
    flush_rewrite_rules();
}

// Create publications table
function rtm_create_publications_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rtm_publications';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Note: written for dbDelta — plain "CREATE TABLE", one field per line, two
    // spaces before the PRIMARY KEY column. On upgrade dbDelta adds the
    // `team_id` column / key to existing installs without data loss.
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        team_id bigint(20) DEFAULT 0 NOT NULL,
        title text NOT NULL,
        authors text NOT NULL,
        journal varchar(255) DEFAULT '' NOT NULL,
        year int(4) NOT NULL,
        citations int(11) DEFAULT 0 NOT NULL,
        url varchar(500) DEFAULT '' NOT NULL,
        google_scholar_id varchar(100) DEFAULT '' NOT NULL,
        abstract text NOT NULL,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY team_id (team_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Create default member status terms
function rtm_create_default_member_status_terms() {
    // Default member status terms to create (generic, lab-agnostic)
    $default_terms = array(
        array(
            'name' => 'Current Members',
            'slug' => 'current_member',
            'description' => 'Current active team members'
        ),
        array(
            'name' => 'Alumni',
            'slug' => 'alumni_member',
            'description' => 'Former team members who have graduated or moved on'
        ),
        array(
            'name' => 'Visiting / Honorary',
            'slug' => 'honorary_member',
            'description' => 'Visiting researchers and honorary members'
        )
    );
    
    // Create terms if they don't exist
    foreach ($default_terms as $term_data) {
        if (!term_exists($term_data['slug'], 'rtm_member_status')) {
            wp_insert_term(
                $term_data['name'],
                'rtm_member_status',
                array(
                    'slug' => $term_data['slug'],
                    'description' => $term_data['description']
                )
            );
        }
    }
}

// Register Team Member Post Type
add_action('init', 'rtm_register_team_member_post_type');
function rtm_register_team_member_post_type() {
    
    // In multiple-teams mode the section manages many labs, so present the
    // top-level admin menu as "Research Labs"; single mode stays "Team Members".
    $menu_name = rtm_is_multiple_teams() ? 'Research Labs' : 'Team Members';

    $labels = array(
        'name'                  => 'Team Members',
        'singular_name'         => 'Team Member',
        'menu_name'             => $menu_name,
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Team Member',
        'edit_item'             => 'Edit Team Member',
        'new_item'              => 'New Team Member',
        'view_item'             => 'View Team Member',
        'search_items'          => 'Search Team Members',
        'not_found'             => 'No team members found',
        'not_found_in_trash'    => 'No team members found in trash',
    );
    
    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'team-member'),
        'capability_type'       => 'post',
        'has_archive'           => true,
        'hierarchical'          => false,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-groups',
        'supports'              => array('title', 'thumbnail'),
        'show_in_rest'          => true,
    );
    
    register_post_type('rtm_team_member', $args);
    
    // Register Research Area Taxonomy
    register_taxonomy(
        'rtm_research_area',
        'rtm_team_member',
        array(
            'label'             => 'Research Areas',
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'research-area'),
        )
    );
    
    // Register Team Role Taxonomy
    register_taxonomy(
        'rtm_team_role',
        'rtm_team_member',
        array(
            'label'             => 'Team Roles',
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'team-role'),
        )
    );
    
    // Register Member Status Taxonomy
    register_taxonomy(
        'rtm_member_status',
        'rtm_team_member',
        array(
            'label'             => 'Member Status',
            'labels'            => array(
                'name'              => 'Member Status',
                'singular_name'     => 'Member Status',
                'menu_name'         => 'Member Status',
                'all_items'         => 'All Member Status',
                'edit_item'         => 'Edit Member Status',
                'view_item'         => 'View Member Status',
                'update_item'       => 'Update Member Status',
                'add_new_item'      => 'Add New Member Status',
                'new_item_name'     => 'New Member Status Name',
                'search_items'      => 'Search Member Status',
                'not_found'         => 'No member status found',
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'member-status'),
            'capabilities'      => array(
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ),
        )
    );

    // Register Research Team (Lab) Taxonomy
    rtm_register_research_team_taxonomy();
}

/**
 * Register the Research Team ("lab") taxonomy.
 *
 * Each term is one lab/team and gets its own archive at /research-team/{slug}/.
 * The taxonomy stays public in both modes so archives and the REST API keep
 * working; only the admin UI is gated behind multiple-teams mode.
 */
function rtm_register_research_team_taxonomy() {
    $show_ui = rtm_is_multiple_teams();

    register_taxonomy(
        'rtm_research_team',
        'rtm_team_member',
        array(
            'labels' => array(
                'name'          => __('Teams', 'research-team-manager'),
                'singular_name' => __('Team', 'research-team-manager'),
                'menu_name'     => __('Teams', 'research-team-manager'),
                'all_items'     => __('All Teams', 'research-team-manager'),
                'edit_item'     => __('Edit Team', 'research-team-manager'),
                'view_item'     => __('View Team', 'research-team-manager'),
                'update_item'   => __('Update Team', 'research-team-manager'),
                'add_new_item'  => __('Add New Team', 'research-team-manager'),
                'new_item_name' => __('New Team Name', 'research-team-manager'),
                'search_items'  => __('Search Teams', 'research-team-manager'),
                'not_found'     => __('No teams found', 'research-team-manager'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => $show_ui,
            'show_in_menu'      => $show_ui,
            'show_in_nav_menus' => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'research-team'),
            'capabilities'      => array(
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ),
        )
    );
}

// Add admin menu items
add_action('admin_menu', 'rtm_add_admin_menus');
function rtm_add_admin_menus() {
    // Add Publications submenu
    add_submenu_page(
        'edit.php?post_type=rtm_team_member',
        'Publications',
        'Publications',
        'manage_options',
        'rtm-publications',
        'rtm_publications_page'
    );
    
    // Add Scholar Settings submenu
    add_submenu_page(
        'edit.php?post_type=rtm_team_member',
        'Scholar Settings',
        'Scholar Settings',
        'manage_options',
        'rtm-scholar-settings',
        'rtm_scholar_settings_page'
    );

    // Add Team Settings submenu (mode toggle: single vs multiple)
    add_submenu_page(
        'edit.php?post_type=rtm_team_member',
        'Team Settings',
        'Team Settings',
        'manage_options',
        'rtm-team-settings',
        'rtm_team_settings_page'
    );
}

// Team Settings page: a guarded pipeline for switching between single and
// multiple-teams mode (and choosing the active team / what happens to the rest).
function rtm_team_settings_page() {
    rtm_team_settings_handle_post();

    $mode         = rtm_get_team_mode();
    $default_team = (int) get_option('rtm_default_team', 0);
    $teams        = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'orderby' => 'name'));
    if (is_wp_error($teams)) {
        $teams = array();
    }
    $team_count   = count($teams);
    $member_count = array_sum((array) wp_count_posts('rtm_team_member'));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Team Settings', 'research-team-manager'); ?></h1>

        <p style="font-size:14px;">
            <?php
            printf(
                /* translators: 1: mode label */
                esc_html__('Current mode: %s', 'research-team-manager'),
                '<strong>' . ($mode === 'multiple'
                    ? esc_html__('Multiple teams', 'research-team-manager')
                    : esc_html__('Single team', 'research-team-manager')) . '</strong>'
            );
            if ($mode === 'multiple') {
                echo ' &middot; ' . esc_html(sprintf(_n('%d team', '%d teams', $team_count, 'research-team-manager'), $team_count));
            }
            ?>
        </p>

        <?php if ($mode === 'single'): ?>

            <?php // Active team within single mode ?>
            <div class="card" style="max-width:680px;">
                <h2><?php esc_html_e('Active team', 'research-team-manager'); ?></h2>
                <p class="description"><?php esc_html_e('In single-team mode, listings are scoped to this team. Choose “None” to show all members.', 'research-team-manager'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('rtm_update_active', 'rtm_update_active_nonce'); ?>
                    <input type="hidden" name="rtm_action" value="update_active" />
                    <select name="rtm_default_team">
                        <option value="0"><?php esc_html_e('— None (show all members) —', 'research-team-manager'); ?></option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo esc_attr($team->term_id); ?>" <?php selected($default_team, $team->term_id); ?>><?php echo esc_html($team->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button(__('Save active team', 'research-team-manager'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <?php // Switch to multiple ?>
            <div class="card" style="max-width:680px; margin-top:20px;">
                <h2><?php esc_html_e('Switch to multiple teams', 'research-team-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Turns on the Teams taxonomy, per-lab pages, settings and publications. Your existing members stay; optionally gather them into a starter team so nothing is left unassigned.', 'research-team-manager'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('rtm_to_multiple', 'rtm_to_multiple_nonce'); ?>
                    <input type="hidden" name="rtm_action" value="to_multiple" />
                    <p>
                        <label><input type="checkbox" name="rtm_create_starter" value="1" checked /> <?php esc_html_e('Create a starter team named:', 'research-team-manager'); ?></label>
                        <input type="text" name="rtm_starter_name" value="<?php echo esc_attr(get_bloginfo('name') . ' Lab'); ?>" class="regular-text" />
                    </p>
                    <p>
                        <label><input type="checkbox" name="rtm_move_members" value="1" checked /> <?php echo esc_html(sprintf(__('Move all %d existing member(s) into the starter team', 'research-team-manager'), $member_count)); ?></label>
                    </p>
                    <?php submit_button(__('Switch to multiple teams', 'research-team-manager'), 'primary', 'submit', false); ?>
                </form>
            </div>

        <?php else: // multiple ?>

            <div class="card" style="max-width:760px;">
                <h2><?php esc_html_e('Switch to single team', 'research-team-manager'); ?></h2>
                <p class="description"><?php esc_html_e('Single mode shows one team only and hides the Teams UI. Pick which team stays active, and decide what to do with the others.', 'research-team-manager'); ?></p>

                <?php if ($team_count === 0): ?>
                    <p><em><?php esc_html_e('There are no teams yet.', 'research-team-manager'); ?></em></p>
                    <form method="post" action="">
                        <?php wp_nonce_field('rtm_to_single', 'rtm_to_single_nonce'); ?>
                        <input type="hidden" name="rtm_action" value="to_single" />
                        <input type="hidden" name="rtm_active_team" value="0" />
                        <input type="hidden" name="rtm_other_teams" value="keep" />
                        <?php submit_button(__('Switch to single team', 'research-team-manager'), 'primary', 'submit', false); ?>
                    </form>
                <?php else: ?>
                    <form method="post" action="" onsubmit="return rtmConfirmToSingle(this);">
                        <?php wp_nonce_field('rtm_to_single', 'rtm_to_single_nonce'); ?>
                        <input type="hidden" name="rtm_action" value="to_single" />

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="rtm_active_team"><?php esc_html_e('Active team', 'research-team-manager'); ?></label></th>
                                <td>
                                    <select name="rtm_active_team" id="rtm_active_team">
                                        <option value="0"><?php esc_html_e('— None (keep all members, show all) —', 'research-team-manager'); ?></option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo esc_attr($team->term_id); ?>" <?php selected($default_team, $team->term_id); ?>><?php echo esc_html($team->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('The other teams', 'research-team-manager'); ?></th>
                                <td>
                                    <fieldset>
                                        <label style="display:block; margin-bottom:6px;"><input type="radio" name="rtm_other_teams" value="keep" checked /> <?php esc_html_e('Keep them (just hidden) — non-destructive, reversible', 'research-team-manager'); ?></label>
                                        <label style="display:block; margin-bottom:6px;"><input type="radio" name="rtm_other_teams" value="delete" /> <?php esc_html_e('Delete the other teams (members are kept, just unassigned)', 'research-team-manager'); ?></label>
                                        <label style="display:block;"><input type="radio" name="rtm_other_teams" value="delete_with_members" /> <?php esc_html_e('Delete the other teams AND their members (members not in the active team are permanently deleted)', 'research-team-manager'); ?></label>
                                    </fieldset>
                                    <p style="margin-top:10px;">
                                        <label><input type="checkbox" name="rtm_confirm_delete" value="1" /> <?php esc_html_e('I understand the delete options are permanent.', 'research-team-manager'); ?></label>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Switch to single team', 'research-team-manager'), 'primary', 'submit', false); ?>
                    </form>
                    <script>
                    function rtmConfirmToSingle(f){
                        var d = f.querySelector('input[name="rtm_other_teams"]:checked');
                        if (d && d.value !== 'keep') {
                            return confirm(<?php echo wp_json_encode(__('This will permanently delete the other teams (and possibly their members). Continue?', 'research-team-manager')); ?>);
                        }
                        return true;
                    }
                    </script>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
    <?php
}

// Process the Team Settings conversion forms.
function rtm_team_settings_handle_post() {
    if (!isset($_POST['rtm_action']) || !current_user_can('manage_options')) {
        return;
    }
    $action = sanitize_key($_POST['rtm_action']);

    // Change active team (already in single mode).
    if ($action === 'update_active' && check_admin_referer('rtm_update_active', 'rtm_update_active_nonce')) {
        update_option('rtm_default_team', isset($_POST['rtm_default_team']) ? (int) $_POST['rtm_default_team'] : 0);
        rtm_admin_notice(__('Active team updated.', 'research-team-manager'));
        return;
    }

    // Single -> Multiple.
    if ($action === 'to_multiple' && check_admin_referer('rtm_to_multiple', 'rtm_to_multiple_nonce')) {
        update_option('rtm_team_mode', 'multiple');
        update_option('rtm_default_team', 0);
        $extra = '';

        if (!empty($_POST['rtm_create_starter'])) {
            $name = sanitize_text_field(wp_unslash($_POST['rtm_starter_name'] ?? ''));
            if ($name === '') {
                $name = __('Main Lab', 'research-team-manager');
            }
            $existing = get_term_by('name', $name, 'rtm_research_team');
            if ($existing) {
                $team_id = (int) $existing->term_id;
            } else {
                $res = wp_insert_term($name, 'rtm_research_team');
                $team_id = is_wp_error($res) ? 0 : (int) $res['term_id'];
            }

            if ($team_id && !empty($_POST['rtm_move_members'])) {
                $ids = get_posts(array('post_type' => 'rtm_team_member', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids'));
                $moved = 0;
                foreach ($ids as $pid) {
                    $cur = wp_get_object_terms($pid, 'rtm_research_team', array('fields' => 'ids'));
                    if (empty($cur) || is_wp_error($cur)) {
                        wp_set_object_terms($pid, array($team_id), 'rtm_research_team');
                        $moved++;
                    }
                }
                $extra = ' ' . sprintf(__('Created “%1$s” and moved %2$d member(s) into it.', 'research-team-manager'), $name, $moved);
            } elseif ($team_id) {
                $extra = ' ' . sprintf(__('Created starter team “%s”.', 'research-team-manager'), $name);
            }
        }

        rtm_register_team_member_post_type();
        flush_rewrite_rules();
        rtm_admin_notice(__('Switched to multiple-teams mode.', 'research-team-manager') . $extra);
        return;
    }

    // Multiple -> Single.
    if ($action === 'to_single' && check_admin_referer('rtm_to_single', 'rtm_to_single_nonce')) {
        $active       = isset($_POST['rtm_active_team']) ? (int) $_POST['rtm_active_team'] : 0;
        $disposition  = sanitize_key($_POST['rtm_other_teams'] ?? 'keep');
        $destructive  = in_array($disposition, array('delete', 'delete_with_members'), true);

        if ($destructive && empty($_POST['rtm_confirm_delete'])) {
            rtm_admin_notice(__('Please tick the confirmation box to delete other teams. Nothing was changed.', 'research-team-manager'), 'error');
            return;
        }
        if ($destructive && !$active) {
            rtm_admin_notice(__('Choose an active team to keep before deleting the others. Nothing was changed.', 'research-team-manager'), 'error');
            return;
        }

        update_option('rtm_team_mode', 'single');
        update_option('rtm_default_team', $active);
        $extra = '';

        if ($destructive) {
            $ids = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'fields' => 'ids'));
            $deleted = 0;
            foreach ((array) $ids as $tid) {
                if ((int) $tid === $active) {
                    continue;
                }
                wp_delete_term((int) $tid, 'rtm_research_team');
                $deleted++;
            }
            $extra = ' ' . sprintf(__('Deleted %d other team(s).', 'research-team-manager'), $deleted);

            if ($disposition === 'delete_with_members') {
                // Members now with no team at all (those only in deleted teams).
                $orphans = get_posts(array(
                    'post_type'   => 'rtm_team_member',
                    'post_status' => 'any',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'tax_query'   => array(array('taxonomy' => 'rtm_research_team', 'operator' => 'NOT EXISTS')),
                ));
                $removed = 0;
                foreach ($orphans as $pid) {
                    wp_delete_post($pid, true);
                    $removed++;
                }
                $extra .= ' ' . sprintf(__('Removed %d member(s) with no remaining team.', 'research-team-manager'), $removed);
            }
        }

        rtm_register_team_member_post_type();
        flush_rewrite_rules();
        rtm_admin_notice(__('Switched to single-team mode.', 'research-team-manager') . $extra);
        return;
    }
}

// Print an admin notice inline (the settings handler runs during page render,
// after the admin_notices hook has already fired).
function rtm_admin_notice($message, $type = 'success') {
    printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
}

// Publications page
function rtm_publications_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    $multiple   = rtm_is_multiple_teams();

    // Which team are we scoping to? (0 = all / global)
    $selected_team = isset($_REQUEST['rtm_team']) ? (int) $_REQUEST['rtm_team'] : 0;
    if (!$multiple) {
        $active = rtm_get_active_team();
        $selected_team = $active ? (int) $active->term_id : 0;
    }

    // Handle sync request
    if (isset($_POST['sync_publications']) && wp_verify_nonce($_POST['rtm_sync_nonce'], 'rtm_sync_publications')) {
        rtm_sync_publications($selected_team);
    }

    // Handle import request
    if (isset($_POST['import_json']) && wp_verify_nonce($_POST['rtm_import_nonce'], 'rtm_import_json')) {
        rtm_import_json_publications($selected_team);
    }

    // Get publications (optionally scoped to a team)
    if ($selected_team) {
        $publications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE team_id = %d ORDER BY year DESC, citations DESC",
            $selected_team
        ), ARRAY_A);
    } else {
        $publications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY year DESC, citations DESC", ARRAY_A);
    }
    $total_publications = count($publications);
    $total_citations    = array_sum(array_column($publications, 'citations'));

    $teams = $multiple ? get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false)) : array();
    if (is_wp_error($teams)) {
        $teams = array();
    }
    $team_names    = $multiple ? wp_list_pluck($teams, 'name', 'term_id') : array();
    $scholar_in_use = rtm_get_scholar_id_for_team($selected_team);

    ?>
    <div class="wrap">
        <h1>Research Publications</h1>

        <?php if ($multiple): ?>
            <form method="get" style="margin: 15px 0;">
                <input type="hidden" name="post_type" value="rtm_team_member" />
                <input type="hidden" name="page" value="rtm-publications" />
                <label for="rtm_team"><strong>Team:</strong></label>
                <select name="rtm_team" id="rtm_team" onchange="this.form.submit()">
                    <option value="0" <?php selected($selected_team, 0); ?>>All teams</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo esc_attr($team->term_id); ?>" <?php selected($selected_team, $team->term_id); ?>>
                            <?php echo esc_html($team->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><input type="submit" class="button" value="Filter" /></noscript>
            </form>
        <?php endif; ?>

        <p class="description">
            <?php
            if ($selected_team) {
                printf('Scholar ID for this team: %s', $scholar_in_use ? '<code>' . esc_html($scholar_in_use) . '</code>' : '<em>not set</em>');
            } else {
                printf('Global Scholar ID: %s', $scholar_in_use ? '<code>' . esc_html($scholar_in_use) . '</code>' : '<em>not set</em>');
            }
            ?>
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0 0 10px 0;"><?php echo number_format($total_publications); ?></h3>
                <p style="margin: 0; color: #646970;">Total Publications<?php echo $selected_team ? ' (this team)' : ''; ?></p>
            </div>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0 0 10px 0;"><?php echo number_format($total_citations); ?></h3>
                <p style="margin: 0; color: #646970;">Total Citations</p>
            </div>
        </div>

        <div style="margin: 20px 0;">
            <?php if ($multiple && !$selected_team): ?>
                <p class="description"><?php esc_html_e('Select a team above to sync or import its publications. (Syncing pulls from that team’s own Google Scholar profile.)', 'research-team-manager'); ?></p>
            <?php else: ?>
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('rtm_sync_publications', 'rtm_sync_nonce'); ?>
                    <input type="hidden" name="rtm_team" value="<?php echo esc_attr($selected_team); ?>" />
                    <input type="submit" name="sync_publications" class="button button-primary" value="<?php echo esc_attr($selected_team ? sprintf(__('Sync “%s” from Google Scholar', 'research-team-manager'), $team_names[$selected_team] ?? __('team', 'research-team-manager')) : __('Sync Publications from Google Scholar', 'research-team-manager')); ?>" />
                </form>

                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('rtm_import_json', 'rtm_import_nonce'); ?>
                    <input type="hidden" name="rtm_team" value="<?php echo esc_attr($selected_team); ?>" />
                    <input type="submit" name="import_json" class="button button-secondary" value="<?php esc_attr_e('Import from JSON File', 'research-team-manager'); ?>" />
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($publications)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Authors</th>
                        <th>Journal</th>
                        <th>Year</th>
                        <th>Citations</th>
                        <?php if ($multiple): ?><th>Team</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publications as $pub): ?>
                        <tr>
                            <td>
                                <?php if ($pub['url']): ?>
                                    <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($pub['title']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($pub['authors']); ?></td>
                            <td><?php echo esc_html($pub['journal']); ?></td>
                            <td><?php echo esc_html($pub['year']); ?></td>
                            <td><?php echo esc_html($pub['citations']); ?></td>
                            <?php if ($multiple): ?>
                                <td><?php echo isset($team_names[$pub['team_id']]) ? esc_html($team_names[$pub['team_id']]) : '<em>—</em>'; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No publications found. Click "Sync Publications" to fetch from Google Scholar.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Scholar Settings page — per-team table in multiple mode, single global ID otherwise.
function rtm_scholar_settings_page() {
    if (rtm_is_multiple_teams()) {
        rtm_scholar_settings_page_multi();
        return;
    }

    // Save settings (single-team mode)
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['rtm_settings_nonce'], 'rtm_save_settings')) {
        update_option('rtm_google_scholar_user_id', sanitize_text_field(wp_unslash($_POST['rtm_google_scholar_user_id'])));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }

    $user_id = get_option('rtm_google_scholar_user_id', '');
    ?>
    <div class="wrap">
        <h1>Google Scholar Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('rtm_save_settings', 'rtm_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="rtm_google_scholar_user_id">Google Scholar User ID</label></th>
                    <td>
                        <input type="text" id="rtm_google_scholar_user_id" name="rtm_google_scholar_user_id" value="<?php echo esc_attr($user_id); ?>" class="regular-text" />
                        <p class="description">Enter the user ID from a Google Scholar profile URL (e.g., m0_aWlQAAAAJ)</p>
                        <?php if ($user_id): ?>
                            <p><a href="https://scholar.google.com/citations?user=<?php echo esc_attr($user_id); ?>" target="_blank" class="button button-secondary">View Profile</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Scholar Settings — manage every team's Scholar ID in one table (multiple mode).
function rtm_scholar_settings_page_multi() {
    global $wpdb;
    $table = $wpdb->prefix . 'rtm_publications';

    if (isset($_POST['submit']) && check_admin_referer('rtm_save_team_scholar', 'rtm_team_scholar_nonce')) {
        $ids = isset($_POST['rtm_team_scholar']) && is_array($_POST['rtm_team_scholar']) ? wp_unslash($_POST['rtm_team_scholar']) : array();
        foreach ($ids as $term_id => $value) {
            $term_id = (int) $term_id;
            $value   = sanitize_text_field($value);
            if ($value === '') {
                delete_term_meta($term_id, 'rtm_team_scholar_id');
            } else {
                update_term_meta($term_id, 'rtm_team_scholar_id', $value);
            }
        }
        update_option('rtm_google_scholar_user_id', sanitize_text_field(wp_unslash($_POST['rtm_google_scholar_user_id'] ?? '')));
        echo '<div class="notice notice-success"><p>' . esc_html__('Scholar IDs saved.', 'research-team-manager') . '</p></div>';
    }

    $teams    = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'orderby' => 'name'));
    if (is_wp_error($teams)) {
        $teams = array();
    }
    $fallback = get_option('rtm_google_scholar_user_id', '');
    $pubs_url = admin_url('edit.php?post_type=rtm_team_member&page=rtm-publications');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Google Scholar Settings', 'research-team-manager'); ?></h1>
        <p class="description"><?php esc_html_e('Each team has its own Google Scholar profile. Set every team’s Scholar ID here, then sync each team’s publications from the Publications screen.', 'research-team-manager'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('rtm_save_team_scholar', 'rtm_team_scholar_nonce'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Team', 'research-team-manager'); ?></th>
                        <th><?php esc_html_e('Google Scholar ID', 'research-team-manager'); ?></th>
                        <th style="width:90px;"><?php esc_html_e('Publications', 'research-team-manager'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Actions', 'research-team-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                        <tr><td colspan="4"><?php esc_html_e('No teams yet.', 'research-team-manager'); ?></td></tr>
                    <?php else: foreach ($teams as $team):
                        $sid   = get_term_meta($team->term_id, 'rtm_team_scholar_id', true);
                        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE team_id = %d", $team->term_id));
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($team->name); ?></strong></td>
                            <td><input type="text" class="regular-text" name="rtm_team_scholar[<?php echo (int) $team->term_id; ?>]" value="<?php echo esc_attr($sid); ?>" placeholder="<?php esc_attr_e('e.g. m0_aWlQAAAAJ', 'research-team-manager'); ?>" /></td>
                            <td><?php echo esc_html(number_format_i18n($count)); ?></td>
                            <td>
                                <?php if ($sid): ?>
                                    <a href="https://scholar.google.com/citations?user=<?php echo esc_attr($sid); ?>" target="_blank" rel="noopener">Profile</a> ·
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg('rtm_team', $team->term_id, $pubs_url)); ?>"><?php esc_html_e('Sync', 'research-team-manager'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top:2em;"><?php esc_html_e('Fallback Scholar ID', 'research-team-manager'); ?></h2>
            <p class="description"><?php esc_html_e('Used only for teams that have no Scholar ID of their own.', 'research-team-manager'); ?></p>
            <input type="text" class="regular-text" name="rtm_google_scholar_user_id" value="<?php echo esc_attr($fallback); ?>" placeholder="<?php esc_attr_e('(optional)', 'research-team-manager'); ?>" />

            <?php submit_button(__('Save Scholar IDs', 'research-team-manager')); ?>
        </form>
    </div>
    <?php
}

/**
 * Sync publications for a team from Google Scholar.
 *
 * There is no official Google Scholar API and scraping is unreliable/blocked, so
 * the plugin does not ship a built-in fetcher. Instead it exposes a filter —
 * `rtm_fetch_scholar_publications` — that an integration (e.g. a SerpAPI-backed
 * add-on) can implement to return real records; whatever it returns is stored
 * against the team. With no integration, we tell the user to use Import / manual
 * entry rather than inserting placeholder rows.
 */
function rtm_sync_publications($team_id = 0) {
    $team_id = (int) $team_id;
    $user_id = rtm_get_scholar_id_for_team($team_id);

    if (empty($user_id)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('No Google Scholar ID is set', 'research-team-manager')
            . esc_html($team_id ? __(' for this team — add one on Scholar Settings or the team’s edit screen.', 'research-team-manager') : __('. Set one under Scholar Settings.', 'research-team-manager'))
            . '</p></div>';
        return false;
    }

    /**
     * Return an array of publication rows to import for this Scholar profile.
     * Each row: title, authors, journal, year, citations, url, google_scholar_id, abstract.
     *
     * @param array  $publications Default empty.
     * @param string $user_id      The team's Google Scholar user ID.
     * @param int    $team_id      The team term ID (0 = global/single).
     */
    $publications = apply_filters('rtm_fetch_scholar_publications', array(), $user_id, $team_id);

    if (empty($publications) || !is_array($publications)) {
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Automated Google Scholar sync isn’t available (Scholar has no public API). Use “Import from JSON File” or add publications manually — they’ll be scoped to this team.', 'research-team-manager')
            . '</p></div>';
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    $allowed    = array('title', 'authors', 'journal', 'year', 'citations', 'url', 'google_scholar_id', 'abstract');
    $imported   = 0;

    foreach ($publications as $pub) {
        $row = array_intersect_key((array) $pub, array_flip($allowed));
        if (empty($row['title'])) {
            continue;
        }
        $row['team_id'] = $team_id;
        $gsid = isset($row['google_scholar_id']) ? $row['google_scholar_id'] : '';

        $existing = $gsid ? $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE google_scholar_id = %s AND team_id = %d",
            $gsid,
            $team_id
        )) : null;

        if ($existing) {
            $wpdb->update($table_name, $row, array('id' => $existing));
        } else {
            $wpdb->insert($table_name, $row);
        }
        $imported++;
    }

    echo '<div class="notice notice-success"><p>' . esc_html(sprintf(__('Synced %d publication(s).', 'research-team-manager'), $imported)) . '</p></div>';
}

// Import JSON publications function
function rtm_import_json_publications($team_id = 0) {
    $team_id = (int) $team_id;
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    // Path to the JSON file
    $json_file = plugin_dir_path(__FILE__) . 'articles-import.json';
    
    if (!file_exists($json_file)) {
        echo '<div class="notice notice-error"><p>JSON import file not found: articles-import.json</p></div>';
        return false;
    }
    
    // Read and decode JSON file
    $json_content = file_get_contents($json_file);
    $publications = json_decode($json_content, true);
    
    if (!$publications) {
        echo '<div class="notice notice-error"><p>Invalid JSON file or empty data.</p></div>';
        return false;
    }
    
    $imported_count = 0;
    $updated_count = 0;

    foreach ($publications as $pub) {
        // Check if publication already exists by title and year (scoped to team)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE title = %s AND year = %d AND team_id = %d",
            $pub['title'],
            $pub['publication_year'],
            $team_id
        ));

        // Prepare data for database insertion
        $pub_data = array(
            'team_id' => $team_id,
            'title' => $pub['title'],
            'authors' => $pub['authors'],
            'journal' => $pub['journal'],
            'year' => $pub['publication_year'],
            'citations' => $pub['citation_count'],
            'url' => $pub['pub_url'],
            'google_scholar_id' => 'imported_' . $pub['extraction_order'],
            'abstract' => ''
        );

        if ($existing) {
            // Update existing publication
            $wpdb->update($table_name, $pub_data, array('id' => $existing));
            $updated_count++;
        } else {
            // Insert new publication
            $wpdb->insert($table_name, $pub_data);
            $imported_count++;
        }
    }
    
    echo '<div class="notice notice-success"><p>JSON import completed! ' . $imported_count . ' new publications imported, ' . $updated_count . ' publications updated.</p></div>';
}

// Add meta boxes for team members
add_action('add_meta_boxes', 'rtm_add_team_member_meta_boxes');
function rtm_add_team_member_meta_boxes() {
    add_meta_box(
        'rtm_member_details',
        'Team Member Details',
        'rtm_member_details_callback',
        'rtm_team_member',
        'normal',
        'high'
    );
}

// Meta box callback
function rtm_member_details_callback($post) {
    wp_nonce_field('rtm_save_member_meta', 'rtm_member_nonce');
    
    // Get existing values
    $short_description = get_post_meta($post->ID, '_rtm_short_description', true);
    $long_description = get_post_meta($post->ID, '_rtm_long_description', true);
    $linkedin_url = get_post_meta($post->ID, '_rtm_linkedin_url', true);
    $google_scholar_url = get_post_meta($post->ID, '_rtm_google_scholar_url', true);
    $researchgate_url = get_post_meta($post->ID, '_rtm_researchgate_url', true);
    $member_status = get_post_meta($post->ID, '_rtm_member_status', true);
    if (!$member_status) $member_status = 'current'; // Default to current member
    $email = get_post_meta($post->ID, '_rtm_email', true);
    $phonenumber = get_post_meta($post->ID, '_rtm_phonenumber', true);
    $website = get_post_meta($post->ID, '_rtm_website', true);
    $position = get_post_meta($post->ID, '_rtm_position', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="rtm_short_description">Short Description *</label></th>
            <td>
                <?php
                wp_editor($short_description, 'rtm_short_description', array(
                    'textarea_name' => 'rtm_short_description',
                    'media_buttons' => true,
                    'textarea_rows' => 5,
                    'teeny' => false,
                    'tinymce' => true,
                    'quicktags' => true,
                ));
                ?>
                <p class="description">Enter a brief description for the team listing page.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_sync_descriptions">Sync Descriptions</label></th>
            <td>
                <label>
                    <input type="checkbox" id="rtm_sync_descriptions" name="rtm_sync_descriptions" value="1" />
                    Use the same content for both short and long descriptions
                </label>
                <p class="description">When checked, the long description will automatically match the short description.</p>
            </td>
        </tr>
        <tr id="rtm_long_description_row">
            <th><label for="rtm_long_description">Long Description</label></th>
            <td>
                <?php
                wp_editor($long_description, 'rtm_long_description', array(
                    'textarea_name' => 'rtm_long_description',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny' => false,
                    'tinymce' => true,
                    'quicktags' => true,
                ));
                ?>
                <p class="description">Enter a detailed description for the individual profile page.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_linkedin_url">LinkedIn URL</label></th>
            <td>
                <input type="url" id="rtm_linkedin_url" name="rtm_linkedin_url" value="<?php echo esc_attr($linkedin_url); ?>" class="regular-text" />
                <p class="description">Enter the LinkedIn profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_google_scholar_url">Google Scholar URL</label></th>
            <td>
                <input type="url" id="rtm_google_scholar_url" name="rtm_google_scholar_url" value="<?php echo esc_attr($google_scholar_url); ?>" class="regular-text" />
                <p class="description">Enter the Google Scholar profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_researchgate_url">ResearchGate URL</label></th>
            <td>
                <input type="url" id="rtm_researchgate_url" name="rtm_researchgate_url" value="<?php echo esc_attr($researchgate_url); ?>" class="regular-text" />
                <p class="description">Enter the ResearchGate profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_email">Email</label></th>
            <td><input type="email" id="rtm_email" name="rtm_email" value="<?php echo esc_attr($email); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_phonenumber">Phone Number</label></th>
            <td><input type="text" id="rtm_phonenumber" name="rtm_phonenumber" value="<?php echo esc_attr($phonenumber); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_website">Website</label></th>
            <td><input type="url" id="rtm_website" name="rtm_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_position">Position</label></th>
            <td><input type="text" id="rtm_position" name="rtm_position" value="<?php echo esc_attr($position); ?>" class="regular-text" /></td>
        </tr>
    </table>
    
    <script>
    jQuery(document).ready(function($) {
        // Check if descriptions are currently the same
        function checkIfDescriptionsSynced() {
            var shortDesc = '';
            var longDesc = '';
            
            // Get short description content from TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_short_description')) {
                shortDesc = tinyMCE.get('rtm_short_description').getContent();
            } else {
                shortDesc = $('#rtm_short_description').val();
            }
            
            // Get long description content from TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_long_description')) {
                longDesc = tinyMCE.get('rtm_long_description').getContent();
            } else {
                longDesc = $('#rtm_long_description').val();
            }
            
            // If they're the same, check the box
            if (shortDesc === longDesc && shortDesc !== '') {
                $('#rtm_sync_descriptions').prop('checked', true);
                $('#rtm_long_description_row').hide();
            }
        }
        
        // Check on page load
        setTimeout(checkIfDescriptionsSynced, 1000);
        
        // Handle checkbox change
        $('#rtm_sync_descriptions').change(function() {
            if ($(this).is(':checked')) {
                // Hide long description field
                $('#rtm_long_description_row').hide();
                
                // Copy short description to long description
                var shortContent = '';
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_short_description')) {
                    shortContent = tinyMCE.get('rtm_short_description').getContent();
                    if (tinyMCE.get('rtm_long_description')) {
                        tinyMCE.get('rtm_long_description').setContent(shortContent);
                    }
                } else {
                    shortContent = $('#rtm_short_description').val();
                }
                $('#rtm_long_description').val(shortContent);
            } else {
                // Show long description field
                $('#rtm_long_description_row').show();
            }
        });
        
        // Sync content in real-time when checkbox is checked
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.on('AddEditor', function(e) {
                if (e.editor.id === 'rtm_short_description') {
                    e.editor.on('change keyup', function() {
                        if ($('#rtm_sync_descriptions').is(':checked')) {
                            var content = this.getContent();
                            if (tinyMCE.get('rtm_long_description')) {
                                tinyMCE.get('rtm_long_description').setContent(content);
                            }
                            $('#rtm_long_description').val(content);
                        }
                    });
                }
            });
        }
        
        // Fallback for text mode
        $('#rtm_short_description').on('input', function() {
            if ($('#rtm_sync_descriptions').is(':checked')) {
                $('#rtm_long_description').val($(this).val());
            }
        });
    });
    </script>
    <?php
}

// Save meta box data
add_action('save_post', 'rtm_save_team_member_meta');
function rtm_save_team_member_meta($post_id) {
    // Security checks
    if (!isset($_POST['rtm_member_nonce']) || !wp_verify_nonce($_POST['rtm_member_nonce'], 'rtm_save_member_meta')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Check if sync checkbox is checked
    if (isset($_POST['rtm_sync_descriptions']) && $_POST['rtm_sync_descriptions'] == '1') {
        // If syncing, copy short description to long description
        if (isset($_POST['rtm_short_description'])) {
            $short_desc = wp_kses_post(wp_unslash($_POST['rtm_short_description']));
            update_post_meta($post_id, '_rtm_short_description', $short_desc);
            update_post_meta($post_id, '_rtm_long_description', $short_desc);
        }
    } else {
        // Save short and long descriptions separately
        if (isset($_POST['rtm_short_description'])) {
            update_post_meta($post_id, '_rtm_short_description', wp_kses_post(wp_unslash($_POST['rtm_short_description'])));
        }
        if (isset($_POST['rtm_long_description'])) {
            update_post_meta($post_id, '_rtm_long_description', wp_kses_post(wp_unslash($_POST['rtm_long_description'])));
        }
    }
    
    // Save other fields
    $fields = array(
        'rtm_linkedin_url' => 'esc_url_raw',
        'rtm_google_scholar_url' => 'esc_url_raw',
        'rtm_researchgate_url' => 'esc_url_raw',
        'rtm_email' => 'sanitize_email',
        'rtm_phonenumber' => 'sanitize_text_field',
        'rtm_website' => 'esc_url_raw',
        'rtm_position' => 'sanitize_text_field',
    );
    
    foreach ($fields as $field => $sanitize) {
        if (isset($_POST[$field])) {
            $value = call_user_func($sanitize, wp_unslash($_POST[$field]));
            update_post_meta($post_id, '_' . $field, $value);
        }
    }
}

/* -------------------------------------------------------------------------
 * Multi-team admin: make managing members team-centric.
 * All of this is gated behind multiple-teams mode (the team UI is hidden in
 * single mode), so single-team installs are unaffected.
 * ---------------------------------------------------------------------- */

// Filter the Team Members list by team.
add_action('restrict_manage_posts', 'rtm_member_team_filter');
function rtm_member_team_filter($post_type) {
    if ($post_type !== 'rtm_team_member' || !rtm_is_multiple_teams()) {
        return;
    }
    $tax = 'rtm_research_team';
    wp_dropdown_categories(array(
        'show_option_all' => __('All teams', 'research-team-manager'),
        'taxonomy'        => $tax,
        'name'            => $tax,
        'value_field'     => 'slug',
        'selected'        => isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '',
        'hierarchical'    => true,
        'hide_empty'      => false,
        'show_count'      => true,
        'orderby'         => 'name',
    ));
}

// Replace the default "Teams" checklist with a prominent, high-priority
// "Research Team" box, and let new members be pre-assigned via ?rtm_team=ID.
add_action('add_meta_boxes_rtm_team_member', 'rtm_member_team_metabox');
function rtm_member_team_metabox() {
    if (!rtm_is_multiple_teams()) {
        return;
    }
    remove_meta_box('rtm_research_teamdiv', 'rtm_team_member', 'side');
    add_meta_box('rtm_member_team', __('Research Team', 'research-team-manager'), 'rtm_member_team_metabox_cb', 'rtm_team_member', 'side', 'high');
}
function rtm_member_team_metabox_cb($post) {
    $terms = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'orderby' => 'name'));
    if (empty($terms) || is_wp_error($terms)) {
        printf(
            '<p>%s</p>',
            sprintf(
                /* translators: %s: add-team URL */
                wp_kses_post(__('No teams yet. <a href="%s">Add a team</a> first.', 'research-team-manager')),
                esc_url(admin_url('edit-tags.php?taxonomy=rtm_research_team&post_type=rtm_team_member'))
            )
        );
        return;
    }

    $assigned = wp_get_object_terms($post->ID, 'rtm_research_team', array('fields' => 'ids'));
    if (is_wp_error($assigned)) {
        $assigned = array();
    }
    // Pre-select when adding from a team ("Add member" action).
    $preselect = isset($_GET['rtm_team']) ? (int) $_GET['rtm_team'] : 0;
    if ($preselect && !in_array($preselect, $assigned, true)) {
        $assigned[] = $preselect;
    }

    echo '<p class="description">' . esc_html__('Which research team(s) does this member belong to?', 'research-team-manager') . '</p>';
    // Hidden 0 lets WordPress clear all teams when none are ticked.
    echo '<input type="hidden" name="tax_input[rtm_research_team][]" value="0" />';
    echo '<div style="max-height:260px; overflow:auto; border:1px solid #dcdcde; border-radius:4px; padding:8px 10px;">';
    foreach ($terms as $t) {
        printf(
            '<label style="display:block; margin:4px 0;"><input type="checkbox" name="tax_input[rtm_research_team][]" value="%d" %s /> %s</label>',
            (int) $t->term_id,
            checked(in_array((int) $t->term_id, array_map('intval', $assigned), true), true, false),
            esc_html($t->name)
        );
    }
    echo '</div>';
}

// "Add member" row action on each team in the Teams list.
add_filter('rtm_research_team_row_actions', 'rtm_team_add_member_action', 10, 2);
function rtm_team_add_member_action($actions, $term) {
    if (rtm_is_multiple_teams()) {
        $url = admin_url('post-new.php?post_type=rtm_team_member&rtm_team=' . (int) $term->term_id);
        $actions['rtm_add_member'] = '<a href="' . esc_url($url) . '">' . esc_html__('Add member', 'research-team-manager') . '</a>';
    }
    return $actions;
}

// Register shortcodes
add_shortcode('rtm_team_members', 'rtm_team_members_shortcode');
function rtm_team_members_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10,
        'role' => '',
        'research_area' => '',
        'team' => '',
    ), $atts);

    $args = array(
        'post_type' => 'rtm_team_member',
        'posts_per_page' => intval($atts['limit']),
        'post_status' => 'publish',
    );

    // Scope to a team (explicit slug/ID, current team archive, or single-mode default).
    $tax_query = array();
    $team_id = rtm_resolve_team_id($atts['team']);
    if ($team_id) {
        $tax_query[] = array(
            'taxonomy' => 'rtm_research_team',
            'field' => 'term_id',
            'terms' => $team_id,
        );
    }
    if (!empty($atts['role'])) {
        $tax_query[] = array(
            'taxonomy' => 'rtm_team_role',
            'field' => 'slug',
            'terms' => explode(',', $atts['role']),
        );
    }
    if (!empty($atts['research_area'])) {
        $tax_query[] = array(
            'taxonomy' => 'rtm_research_area',
            'field' => 'slug',
            'terms' => explode(',', $atts['research_area']),
        );
    }
    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    
    ob_start();
    
    if ($query->have_posts()) {
        echo '<div class="rtm-team-members" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';
        
        while ($query->have_posts()) {
            $query->the_post();
            $position = get_post_meta(get_the_ID(), '_rtm_position', true);
            $email = get_post_meta(get_the_ID(), '_rtm_email', true);
            $short_description = get_post_meta(get_the_ID(), '_rtm_short_description', true);
            
            // Get member status from taxonomy
            $member_status_terms = get_the_terms(get_the_ID(), 'rtm_member_status');
            $member_status_display = '';
            if ($member_status_terms && !is_wp_error($member_status_terms)) {
                $member_status_names = wp_list_pluck($member_status_terms, 'name');
                $member_status_display = implode(', ', $member_status_names);
            }
            
            // Use post title as the display name
            $display_name = get_the_title();
            
            ?>
            <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
                <?php if (has_post_thumbnail()): ?>
                    <div style="margin-bottom: 15px;">
                        <?php the_post_thumbnail('medium', array('style' => 'width: 100%; height: auto;')); ?>
                    </div>
                <?php endif; ?>
                
                <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($display_name); ?></h3>
                
                <?php if ($position): ?>
                    <p style="color: #666; margin: 5px 0;"><strong><?php echo esc_html($position); ?></strong></p>
                <?php endif; ?>
                
                <?php if ($member_status_display): ?>
                    <p style="margin: 5px 0; font-size: 0.9em;">
                        <span style="background: #035642; color: white; padding: 2px 8px; border-radius: 12px;">
                            <?php echo esc_html($member_status_display); ?>
                        </span>
                    </p>
                <?php endif; ?>
                
                <?php if ($email): ?>
                    <p style="margin: 5px 0;"><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <?php if ($short_description): ?>
                        <?php echo wp_kses_post($short_description); ?>
                    <?php else: ?>
                        <?php the_excerpt(); ?>
                    <?php endif; ?>
                </div>
                
                <a href="<?php the_permalink(); ?>" style="color: #0073aa;">View Profile →</a>
            </div>
            <?php
        }
        
        echo '</div>';
    } else {
        echo '<p>No team members found.</p>';
    }
    
    wp_reset_postdata();
    
    return ob_get_clean();
}

// Register publications shortcode
add_shortcode('rtm_publications', 'rtm_publications_shortcode');
function rtm_publications_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    $atts = shortcode_atts(array(
        'limit' => 10,
        'team' => '',
    ), $atts);

    $team_id = rtm_resolve_team_id($atts['team']);

    if ($team_id) {
        $publications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE team_id = %d ORDER BY year DESC, citations DESC LIMIT %d",
            $team_id,
            intval($atts['limit'])
        ), ARRAY_A);
    } else {
        $publications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY year DESC, citations DESC LIMIT %d",
            intval($atts['limit'])
        ), ARRAY_A);
    }

    ob_start();

    if (!empty($publications)) {
        echo '<div class="rtm-publications">';
        
        foreach ($publications as $pub) {
            ?>
            <div style="margin-bottom: 20px; padding: 15px; border-left: 3px solid #0073aa;">
                <h4 style="margin: 0 0 5px 0;">
                    <?php if ($pub['url']): ?>
                        <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($pub['title']); ?>
                    <?php endif; ?>
                </h4>
                <p style="margin: 5px 0; color: #666;"><?php echo esc_html($pub['authors']); ?></p>
                <p style="margin: 5px 0; font-style: italic;"><?php echo esc_html($pub['journal']); ?> (<?php echo esc_html($pub['year']); ?>)</p>
                <?php if ($pub['citations'] > 0): ?>
                    <p style="margin: 5px 0; color: #0073aa;">Cited by <?php echo esc_html($pub['citations']); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }
        
        echo '</div>';
    } else {
        echo '<p>No publications available.</p>';
    }
    
    return ob_get_clean();
}

/**
 * Build the lab/team header markup (logo, name, PI, intro, contact) from term meta.
 *
 * @param int $team_id research_team term ID.
 * @return string
 */
function rtm_get_team_header_html($team_id) {
    $team_id = (int) $team_id;
    if (!$team_id) {
        return '';
    }
    $term = get_term($team_id, 'rtm_research_team');
    if (!$term || is_wp_error($term)) {
        return '';
    }

    $pi        = get_term_meta($team_id, 'rtm_team_pi', true);
    $lead_type = get_term_meta($team_id, 'rtm_team_lead_type', true);
    $type     = get_term_meta($team_id, 'rtm_team_type', true);
    $theme    = get_term_meta($team_id, 'rtm_team_theme', true);
    $intro    = get_term_meta($team_id, 'rtm_team_intro', true);
    $email    = get_term_meta($team_id, 'rtm_team_contact_email', true);
    $website  = get_term_meta($team_id, 'rtm_team_website', true);
    $link_type = get_term_meta($team_id, 'rtm_team_link_type', true);
    $location = get_term_meta($team_id, 'rtm_team_location', true);
    $logo_id  = (int) get_term_meta($team_id, 'rtm_team_logo', true);

    $themes      = function_exists('rtm_team_themes') ? rtm_team_themes() : array();
    $theme_label = ($theme && isset($themes[$theme])) ? $themes[$theme] : '';

    ob_start();
    ?>
    <header class="rtm-team-header">
        <?php if ($logo_id): ?>
            <div class="rtm-team-logo"><?php echo wp_get_attachment_image($logo_id, 'medium'); ?></div>
        <?php endif; ?>
        <div class="rtm-team-header-body">
            <?php if ($type || $theme_label): ?>
                <p class="rtm-team-eyebrow">
                    <?php if ($type): ?><span class="rtm-team-type"><?php echo esc_html($type); ?></span><?php endif; ?>
                    <?php if ($theme_label): ?><span class="rtm-team-theme"><?php echo esc_html($theme_label); ?></span><?php endif; ?>
                </p>
            <?php endif; ?>
            <h1 class="rtm-team-title"><?php echo esc_html($term->name); ?></h1>
            <?php
            $lead_ids = rtm_get_team_lead_ids($team_id);
            if ($lead_type !== 'org' && $lead_ids):
                $links = array();
                foreach ($lead_ids as $lid) {
                    $name = get_the_title($lid);
                    if ($name) {
                        $links[] = '<a href="' . esc_url(get_permalink($lid)) . '">' . esc_html($name) . '</a>';
                    }
                }
                if ($links):
                    $label = _n('Principal Investigator:', 'Principal Investigators:', count($links), 'research-team-manager');
                    ?>
                    <p class="rtm-team-pi"><strong><?php echo esc_html($label); ?></strong> <?php echo implode(', ', $links); ?></p>
                    <?php
                endif;
            elseif ($pi): ?>
                <p class="rtm-team-pi"><strong><?php echo esc_html($lead_type === 'org' ? __('Led by:', 'research-team-manager') : __('Principal Investigator:', 'research-team-manager')); ?></strong> <?php echo esc_html($pi); ?></p>
            <?php endif; ?>
            <?php if ($location): ?>
                <p class="rtm-team-location"><?php echo esc_html($location); ?></p>
            <?php endif; ?>
            <?php if ($intro): ?>
                <div class="rtm-team-intro"><?php echo wp_kses_post(wpautop($intro)); ?></div>
            <?php elseif (!empty($term->description)): ?>
                <div class="rtm-team-intro"><?php echo wp_kses_post(wpautop($term->description)); ?></div>
            <?php endif; ?>
            <?php if ($email || $website): ?>
                <p class="rtm-team-contact">
                    <?php if ($email): ?>
                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                    <?php endif; ?>
                    <?php if ($website): ?>
                        <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(rtm_team_link_label($link_type)); ?> <?php echo rtm_ui_icon('external'); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </header>
    <?php
    return ob_get_clean();
}

// Team header shortcode — drop on a page/template to show a lab's logo, PI and
// intro. Works in any theme (auto-detects the current team archive if no team set).
add_shortcode('rtm_team_header', 'rtm_team_header_shortcode');
function rtm_team_header_shortcode($atts) {
    $atts = shortcode_atts(array('team' => ''), $atts);
    return rtm_get_team_header_html(rtm_resolve_team_id($atts['team']));
}

/**
 * [rtm_team_roster] — the team's members for the current (or given) team.
 * Renders a heading + member grid only when the team has members; outputs
 * nothing at all when empty, so the section disappears.
 */
add_shortcode('rtm_team_roster', 'rtm_team_roster_shortcode');
function rtm_team_roster_shortcode($atts) {
    $atts = shortcode_atts(array('team' => '', 'heading' => 'Lab Team', 'group_by' => ''), $atts);
    $team_id = rtm_resolve_team_id($atts['team']);
    if (!$team_id) {
        return '';
    }

    $members = get_posts(array(
        'post_type'      => 'rtm_team_member',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'tax_query'      => array(array(
            'taxonomy' => 'rtm_research_team',
            'field'    => 'term_id',
            'terms'    => $team_id,
        )),
    ));
    if (empty($members)) {
        return ''; // no members — hide the whole section
    }

    // Resolve grouping: explicit attr → the team's setting → default (by status).
    $group_by = $atts['group_by'];
    if ($group_by === '') {
        $group_by = get_term_meta($team_id, 'rtm_team_roster_group', true);
        if ($group_by === '') {
            $group_by = 'rtm_member_status';
        }
    }
    $group_tax = in_array($group_by, array('rtm_member_status', 'rtm_team_role'), true) ? $group_by : '';

    ob_start();
    if (!empty($atts['heading'])) {
        echo '<h2 class="rtm-section-title">' . esc_html($atts['heading']) . '</h2>';
    }

    if (!$group_tax) {
        echo rtm_render_member_grid($members);
    } else {
        // Split into the chosen taxonomy's terms. Members with no term render
        // first (unlabelled); each term with members gets its own subheading.
        $ungrouped = array();
        $groups = array(); // term_id => ['name'=>, 'members'=>[]]
        foreach ($members as $m) {
            $terms = get_the_terms($m->ID, $group_tax);
            if (!$terms || is_wp_error($terms)) {
                $ungrouped[] = $m;
                continue;
            }
            foreach ($terms as $t) {
                if (!isset($groups[$t->term_id])) {
                    $groups[$t->term_id] = array('name' => $t->name, 'members' => array());
                }
                $groups[$t->term_id]['members'][] = $m;
            }
        }
        // Order groups by name, but push "alumni/former" sections to the end.
        uasort($groups, function ($a, $b) {
            $aa = (stripos($a['name'], 'alumni') !== false || stripos($a['name'], 'former') !== false);
            $bb = (stripos($b['name'], 'alumni') !== false || stripos($b['name'], 'former') !== false);
            if ($aa !== $bb) {
                return $aa ? 1 : -1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        echo rtm_render_member_grid($ungrouped);
        foreach ($groups as $g) {
            echo '<h3 class="rtm-roster-group">' . esc_html($g['name']) . '</h3>';
            echo rtm_render_member_grid($g['members']);
        }
    }

    return ob_get_clean();
}

/**
 * Render a grid of member cards (leads first, then alphabetical).
 *
 * @param WP_Post[] $members
 * @return string
 */
function rtm_render_member_grid($members) {
    if (empty($members)) {
        return '';
    }
    // Leads first, then by title.
    usort($members, function ($a, $b) {
        $al = get_post_meta($a->ID, '_rtm_is_lead', true) ? 1 : 0;
        $bl = get_post_meta($b->ID, '_rtm_is_lead', true) ? 1 : 0;
        if ($al !== $bl) {
            return $bl - $al;
        }
        return strcasecmp(get_the_title($a->ID), get_the_title($b->ID));
    });

    ob_start();
    echo '<div class="rtm-team-members rtm-team-grid">';
    foreach ($members as $m) {
        $pid      = $m->ID;
        $url      = get_permalink($pid);
        $position = get_post_meta($pid, '_rtm_position', true);
        $is_lead  = (bool) get_post_meta($pid, '_rtm_is_lead', true);
        ?>
        <div class="rtm-team-member">
            <?php if (has_post_thumbnail($pid)): ?>
                <div class="rtm-member-photo"><a href="<?php echo esc_url($url); ?>"><?php echo get_the_post_thumbnail($pid, 'medium', array('class' => 'rtm-member-image')); ?></a></div>
            <?php endif; ?>
            <div class="rtm-member-content">
                <h3 class="rtm-member-name"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html(get_the_title($pid)); ?></a></h3>
                <?php if ($is_lead): ?>
                    <span class="rtm-member-lead"><?php esc_html_e('Principal Investigator', 'research-team-manager'); ?></span>
                <?php elseif ($position): ?>
                    <div class="rtm-member-position"><?php echo esc_html($position); ?></div>
                <?php endif; ?>
                <a class="rtm-member-link" href="<?php echo esc_url($url); ?>"><?php esc_html_e('View profile', 'research-team-manager'); ?></a>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * [rtm_team_publications] — the team's publications with a heading, shown only
 * when there are publications; outputs nothing when empty.
 */
add_shortcode('rtm_team_publications', 'rtm_team_publications_shortcode');
function rtm_team_publications_shortcode($atts) {
    $atts = shortcode_atts(array('team' => '', 'heading' => 'Publications'), $atts);
    $inner = rtm_sorted_publications_shortcode(array('team' => $atts['team']));
    if (trim($inner) === '') {
        return '';
    }
    $out = '';
    if (!empty($atts['heading'])) {
        $out .= '<h2 class="rtm-section-title">' . esc_html($atts['heading']) . '</h2>';
    }
    return $out . $inner;
}

/**
 * [rtm_member_profile] — a full profile for a team member (photo, name, role,
 * team(s), bio, contact and academic/social links). Empty parts are omitted.
 * Defaults to the current post in the loop. Used by the single-member template.
 */
add_shortcode('rtm_member_profile', 'rtm_member_profile_shortcode');
function rtm_member_profile_shortcode($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts);
    $pid = $atts['id'] ? (int) $atts['id'] : get_the_ID();
    if (!$pid || get_post_type($pid) !== 'rtm_team_member') {
        return '';
    }

    $position = get_post_meta($pid, '_rtm_position', true);
    $is_lead  = (bool) get_post_meta($pid, '_rtm_is_lead', true);
    $bio      = get_post_meta($pid, '_rtm_long_description', true);
    if (!$bio) {
        $bio = get_post_meta($pid, '_rtm_short_description', true);
    }
    $email    = get_post_meta($pid, '_rtm_email', true);
    $phone    = get_post_meta($pid, '_rtm_phonenumber', true);
    $website  = get_post_meta($pid, '_rtm_website', true);

    // Academic / social links (label => url), in display order.
    $links = array(
        'Google Scholar' => get_post_meta($pid, '_rtm_google_scholar_url', true),
        'ResearchGate'   => get_post_meta($pid, '_rtm_researchgate_url', true),
        'LinkedIn'       => get_post_meta($pid, '_rtm_linkedin_url', true),
        'ORCID'          => get_post_meta($pid, '_rtm_orcid', true),
        'GitHub'         => get_post_meta($pid, '_rtm_github', true),
        'Website'        => $website,
    );
    $links = array_filter($links);

    $teams = get_the_terms($pid, 'rtm_research_team');
    $teams = ($teams && !is_wp_error($teams)) ? $teams : array();

    ob_start();
    ?>
    <article class="rtm-profile">
        <header class="rtm-profile-head">
            <?php if (has_post_thumbnail($pid)): ?>
                <div class="rtm-profile-photo"><?php echo get_the_post_thumbnail($pid, 'large', array('class' => 'rtm-profile-img')); ?></div>
            <?php endif; ?>
            <div class="rtm-profile-id">
                <h1 class="rtm-profile-name"><?php echo esc_html(get_the_title($pid)); ?></h1>
                <?php if ($is_lead): ?>
                    <p class="rtm-profile-role"><span class="rtm-member-lead"><?php esc_html_e('Principal Investigator', 'research-team-manager'); ?></span></p>
                <?php elseif ($position): ?>
                    <p class="rtm-profile-role"><?php echo esc_html($position); ?></p>
                <?php endif; ?>
                <?php if ($teams): ?>
                    <p class="rtm-profile-teams">
                        <?php foreach ($teams as $t):
                            $link = get_term_link($t); if (is_wp_error($link)) { continue; } ?>
                            <a class="rtm-profile-team" href="<?php echo esc_url($link); ?>"><?php echo esc_html($t->name); ?></a>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($bio): ?>
            <div class="rtm-profile-bio"><?php echo wp_kses_post(wpautop($bio)); ?></div>
        <?php endif; ?>

        <?php if ($email || $phone || $links): ?>
            <div class="rtm-profile-contact">
                <?php if ($email || $phone): ?>
                    <ul class="rtm-profile-contact-list">
                        <?php if ($email): ?><li><strong><?php esc_html_e('Email', 'research-team-manager'); ?>:</strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li><?php endif; ?>
                        <?php if ($phone): ?><li><strong><?php esc_html_e('Phone', 'research-team-manager'); ?>:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></li><?php endif; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($links): ?>
                    <p class="rtm-profile-links">
                        <?php foreach ($links as $label => $url): ?>
                            <a class="rtm-profile-link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        $archive = get_post_type_archive_link('rtm_team_member');
        if ($teams):
            $first = get_term_link($teams[0]);
            if (!is_wp_error($first)): ?>
                <p class="rtm-profile-back"><a href="<?php echo esc_url($first); ?>">&larr; <?php echo esc_html(sprintf(__('Back to %s', 'research-team-manager'), $teams[0]->name)); ?></a></p>
            <?php endif;
        endif; ?>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Inline SVG icon for a theme (keyed by the theme term slug). Uses currentColor
 * so it adapts to light/dark. Falls back to a generic "atom" mark.
 */
function rtm_get_team_icon_svg($key) {
    $icons = array(
        'physical-sciences-engineering'     => '<path d="M9 3h6"/><path d="M10 3v6.5L4.6 17.4A2 2 0 0 0 6.3 20.5h11.4a2 2 0 0 0 1.7-3.1L14 9.5V3"/><path d="M7.5 15h9"/>',
        'natural-resources-environment'     => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.5 10-10 10Z"/><path d="M2 21c0-3 1.9-5.4 5.1-6"/>',
        'health-human-development'          => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/><path d="M3.5 12h4l1.5-3 3 6 2-4 1 1h4"/>',
        'interdisciplinary-data-innovation' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1.3.5 2.6 1.5 3.5.8.8 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
    );
    $default = '<circle cx="12" cy="12" r="1.4"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(45 12 12)"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(-45 12 12)"/>';
    $inner = isset($icons[$key]) ? $icons[$key] : $default;
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * Small inline UI icon (chevron, search, sun, moon).
 */
function rtm_ui_icon($name) {
    $icons = array(
        'chevron'  => '<polyline points="9 6 15 12 9 18"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'    => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
    );
    $inner = isset($icons[$name]) ? $icons[$name] : '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * [rtm_teams] — a filterable, themeable directory of all teams/labs.
 *
 * Top-level terms become section headings (themes); their children render as
 * lab cards (icon · title · PI · focus). Includes a theme filter, a search box
 * and a light/dark toggle. Attributes: show_pi (true/false).
 */
add_shortcode('rtm_teams', 'rtm_teams_shortcode');
function rtm_teams_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_pi' => 'true',
    ), $atts);

    $all = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'orderby' => 'name'));
    if (is_wp_error($all) || empty($all)) {
        return '<p>' . esc_html__('No teams found.', 'research-team-manager') . '</p>';
    }

    // Group labs by their Research Theme field (rtm_team_theme), in the order
    // defined by rtm_team_themes(); anything unthemed falls into "Other".
    $themes = rtm_team_themes();
    $short  = rtm_team_theme_short_labels();
    $by_theme = array();
    foreach ($all as $t) {
        $theme = get_term_meta($t->term_id, 'rtm_team_theme', true);
        if (!$theme || !isset($themes[$theme])) {
            $theme = 'other';
        }
        $by_theme[$theme][] = $t;
    }

    $groups = array();
    foreach ($themes as $slug => $label) {
        if (empty($by_theme[$slug])) {
            continue;
        }
        $groups[] = array(
            'slug'  => $slug,
            'name'  => $label,
            'short' => isset($short[$slug]) ? $short[$slug] : $label,
            'desc'  => '',
            'terms' => $by_theme[$slug],
        );
    }
    if (!empty($by_theme['other'])) {
        $groups[] = array('slug' => 'other', 'name' => __('Other', 'research-team-manager'), 'short' => __('Other', 'research-team-manager'), 'desc' => '', 'terms' => $by_theme['other']);
    }

    $total = 0;
    foreach ($groups as $g) {
        $total += count($g['terms']);
    }

    ob_start();
    echo '<div class="rtm-labs" data-rtm-labs>';

    // Toolbar: theme filter chips + search + light/dark toggle.
    echo '<div class="rtm-labs__toolbar">';
    echo '<div class="rtm-labs__filters" role="group" aria-label="' . esc_attr__('Filter labs by theme', 'research-team-manager') . '">';
    echo '<button type="button" class="rtm-chip is-active" data-filter="all" aria-pressed="true">' . esc_html__('All', 'research-team-manager') . ' <span class="rtm-chip__count">' . (int) $total . '</span></button>';
    foreach ($groups as $g) {
        echo '<button type="button" class="rtm-chip" data-filter="' . esc_attr($g['slug']) . '" aria-pressed="false">' . esc_html($g['short']) . ' <span class="rtm-chip__count">' . count($g['terms']) . '</span></button>';
    }
    echo '</div>';
    echo '<div class="rtm-labs__tools">';
    echo '<label class="rtm-labs__search"><span class="rtm-labs__search-ico">' . rtm_ui_icon('search') . '</span>';
    echo '<input type="search" placeholder="' . esc_attr__('Search labs or PIs…', 'research-team-manager') . '" aria-label="' . esc_attr__('Search labs', 'research-team-manager') . '"></label>';
    echo '</div></div>';

    // Theme groups.
    foreach ($groups as $g) {
        echo '<section class="rtm-labs__group" data-theme="' . esc_attr($g['slug']) . '">';
        echo '<h2 class="rtm-labs__group-title">' . esc_html($g['name']) . '</h2>';
        if (!empty($g['desc'])) {
            echo '<p class="rtm-labs__group-desc">' . esc_html(wp_strip_all_tags($g['desc'])) . '</p>';
        }
        echo rtm_render_team_cards($g['terms'], $atts);
        echo '</section>';
    }

    echo '<p class="rtm-labs__empty" hidden>' . esc_html__('No labs match your search.', 'research-team-manager') . '</p>';
    echo '</div>';

    rtm_print_labs_script();

    return ob_get_clean();
}

/**
 * Render a responsive grid of lab cards (icon · title · PI · focus · link).
 *
 * @param WP_Term[] $terms
 * @param array     $atts      Shortcode attributes (show_pi).
 * @param string    $icon_svg  Inline SVG shown on each card in this group.
 * @return string
 */
function rtm_render_team_cards($terms, $atts) {
    if (empty($terms)) {
        return '';
    }
    $show_pi = ($atts['show_pi'] !== 'false');

    ob_start();
    echo '<div class="rtm-labs-grid">';
    foreach ($terms as $t) {
        $link = get_term_link($t);
        if (is_wp_error($link)) {
            $link = '#';
        }
        $pi      = rtm_team_lead_text($t->term_id);
        $type    = get_term_meta($t->term_id, 'rtm_team_type', true);
        $intro   = get_term_meta($t->term_id, 'rtm_team_intro', true);
        $logo_id = (int) get_term_meta($t->term_id, 'rtm_team_logo', true);
        $website = get_term_meta($t->term_id, 'rtm_team_website', true);
        $link_type = get_term_meta($t->term_id, 'rtm_team_link_type', true);
        $source  = $intro ? $intro : $t->description;
        $excerpt = $source ? wp_trim_words(wp_strip_all_tags($source), 24) : '';
        $search  = strtolower(wp_strip_all_tags($t->name . ' ' . $pi . ' ' . $type));
        ?>
        <article class="rtm-lab-card" data-search="<?php echo esc_attr($search); ?>">
            <?php if ($logo_id): ?>
                <span class="rtm-lab-card__logo-wrap"><?php echo wp_get_attachment_image($logo_id, 'thumbnail', false, array('class' => 'rtm-lab-card__logo')); ?></span>
            <?php endif; ?>
            <?php if ($type): ?>
                <span class="rtm-lab-card__type"><?php echo esc_html($type); ?></span>
            <?php endif; ?>
            <h3 class="rtm-lab-card__title"><?php echo esc_html($t->name); ?></h3>
            <?php if ($show_pi && $pi): ?>
                <p class="rtm-lab-card__meta"><?php echo esc_html($pi); ?></p>
            <?php endif; ?>
            <?php if ($excerpt): ?>
                <p class="rtm-lab-card__desc"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <span class="rtm-lab-card__actions">
                <span class="rtm-lab-card__cta"><?php esc_html_e('View team', 'research-team-manager'); ?> <?php echo rtm_ui_icon('chevron'); ?></span>
                <?php if ($website): ?>
                    <a class="rtm-lab-card__site" href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(rtm_team_link_label($link_type, true)); ?> <?php echo rtm_ui_icon('external'); ?></a>
                <?php endif; ?>
            </span>
            <a class="rtm-lab-card__link" href="<?php echo esc_url($link); ?>" aria-label="<?php echo esc_attr(sprintf(__('View %s', 'research-team-manager'), $t->name)); ?>"></a>
        </article>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * Print the directory's filter / search / theme-toggle script once per request.
 */
function rtm_print_labs_script() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <script>
    (function () {
        document.querySelectorAll('[data-rtm-labs]').forEach(function (root) {
            if (root.dataset.rtmInit) { return; }
            root.dataset.rtmInit = '1';

            // Theme filter + text search.
            var chips = root.querySelectorAll('.rtm-chip');
            var search = root.querySelector('.rtm-labs__search input');
            var empty = root.querySelector('.rtm-labs__empty');
            var active = 'all';
            function apply() {
                var q = (search && search.value || '').trim().toLowerCase();
                var anyGroup = false;
                root.querySelectorAll('.rtm-labs__group').forEach(function (group) {
                    var visible = (active === 'all' || active === group.getAttribute('data-theme'));
                    var anyCard = false;
                    group.querySelectorAll('.rtm-lab-card').forEach(function (card) {
                        var match = visible && (!q || card.getAttribute('data-search').indexOf(q) > -1);
                        card.style.display = match ? '' : 'none';
                        if (match) { anyCard = true; }
                    });
                    group.style.display = (visible && anyCard) ? '' : 'none';
                    if (visible && anyCard) { anyGroup = true; }
                });
                if (empty) { empty.hidden = anyGroup; }
            }
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('is-active'); c.setAttribute('aria-pressed', 'false'); });
                    chip.classList.add('is-active'); chip.setAttribute('aria-pressed', 'true');
                    active = chip.getAttribute('data-filter'); apply();
                });
            });
            if (search) { search.addEventListener('input', apply); }
        });
    })();
    </script>
    <?php
}

// Enqueue front-end styles for the .rtm-* shortcodes and bundled templates.
add_action('wp_enqueue_scripts', 'rtm_enqueue_public_assets');
function rtm_enqueue_public_assets() {
    $css = RTM_PLUGIN_PATH . 'assets/css/rtm-public.css';
    $ver = file_exists($css) ? filemtime($css) : RTM_VERSION;
    wp_enqueue_style(
        'rtm-public',
        RTM_PLUGIN_URL . 'assets/css/rtm-public.css',
        array(),
        $ver
    );
}

// Tag any singular view that embeds the teams directory so its page title /
// intro can be made dark-mode aware via CSS (the theme hardcodes heading colour).
add_filter('body_class', 'rtm_body_class');
function rtm_body_class($classes) {
    if (is_singular()) {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'rtm_teams')) {
            $classes[] = 'rtm-labs-page';
        }
    }
    return $classes;
}

/**
 * Does the active (block) theme — or a user customization — provide a block
 * template for this slug? If so, we let it win (and stay editable in the Site
 * Editor); otherwise the plugin renders the page itself.
 */
function rtm_theme_has_block_template($slug) {
    if (!function_exists('get_block_template')) {
        return false;
    }
    $tpl = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template');
    return $tpl instanceof WP_Block_Template;
}

/**
 * Render lab pages and member profiles. The plugin is self-contained:
 *  - Block themes: if the theme/Site Editor provides a matching block template
 *    it wins; otherwise we render our own canvas (theme header/footer parts +
 *    our shortcodes) so the page works without any theme files.
 *  - Classic themes: a theme file override wins, else the bundled PHP template.
 */
add_filter('template_include', 'rtm_template_include');
function rtm_template_include($template) {
    $is_block = function_exists('wp_is_block_theme') && wp_is_block_theme();

    if (is_tax('rtm_research_team')) {
        $slug    = 'taxonomy-rtm_research_team';
        $classic = 'templates/taxonomy-rtm_research_team.php';
    } elseif (is_singular('rtm_team_member')) {
        $slug    = 'single-rtm_team_member';
        $classic = 'templates/single-rtm_team_member.php';
    } elseif (is_post_type_archive('rtm_team_member')) {
        if ($is_block) {
            return $template; // leave the member archive to the block theme
        }
        $slug    = 'archive-rtm_team_member';
        $classic = 'templates/archive-rtm_team_member.php';
    } else {
        return $template;
    }

    if ($is_block) {
        if (rtm_theme_has_block_template($slug)) {
            return $template; // theme / Site Editor template wins
        }
        $canvas = plugin_dir_path(__FILE__) . 'templates/block-canvas.php';
        return file_exists($canvas) ? $canvas : $template;
    }

    if (locate_template(array($slug . '.php'))) {
        return $template; // classic theme override wins
    }
    $plugin_template = plugin_dir_path(__FILE__) . $classic;
    return file_exists($plugin_template) ? $plugin_template : $template;
}

// Allow block editor for team members (comment out to disable)
// add_filter('use_block_editor_for_post_type', 'rtm_disable_gutenberg', 10, 2);
// function rtm_disable_gutenberg($current_status, $post_type) {
//     if ($post_type === 'rtm_team_member') return false;
//     return $current_status;
// }

// Initialize on plugins_loaded to ensure everything is ready
add_action('plugins_loaded', 'rtm_init_plugin');
function rtm_init_plugin() {
    // Ensure tables exist
    rtm_create_publications_table();
}

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-blocks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rtm-custom-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rtm-rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rtm-teams.php';

// Per-lab team settings. Instantiated at load time (not on `init`) so its own
// `init` hook for register_term_meta() is in place before `init` fires.
new RTM_Teams();

// Initialize blocks and custom fields
add_action('init', 'rtm_init_blocks');
function rtm_init_blocks() {
    new RTM_Blocks();
    new RTM_Custom_Fields();
}

// CSS and JS assets are now handled by the child theme
// add_action('wp_enqueue_scripts', 'rtm_enqueue_team_cards_assets');
// function rtm_enqueue_team_cards_assets() {
//     // Enqueue CSS
//     wp_enqueue_style(
//         'rtm-team-member-cards',
//         plugin_dir_url(__FILE__) . 'assets/team-member-cards.css',
//         array(),
//         '1.0.0'
//     );
//     
//     // Enqueue publications CSS
//     wp_enqueue_style(
//         'rtm-publications',
//         plugin_dir_url(__FILE__) . 'assets/publications.css',
//         array(),
//         '1.0.0'
//     );
//     
//     // Enqueue JS for button functionality
//     wp_enqueue_script(
//         'rtm-team-member-cards-js',
//         plugin_dir_url(__FILE__) . 'assets/team-member-cards.js',
//         array('jquery'),
//         '1.0.0',
//         true
//     );
// }

// Sorted publications shortcode using database
function rtm_sorted_publications_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    $atts = shortcode_atts(array(
        'limit' => -1,
        'team' => '',
    ), $atts);

    $team_id = rtm_resolve_team_id($atts['team']);

    // Get publications from database, ordered by year desc (optionally per team)
    if ($team_id) {
        $publications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE team_id = %d ORDER BY year DESC, citations DESC",
            $team_id
        ), ARRAY_A);
    } else {
        $publications = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY year DESC, citations DESC",
            ARRAY_A
        );
    }

    if (empty($publications)) {
        return ''; // nothing to show — let the section hide itself
    }

    // Group publications by year
    $publications_by_year = array();
    foreach ($publications as $pub) {
        $year = $pub['year'];
        if (!isset($publications_by_year[$year])) {
            $publications_by_year[$year] = array();
        }
        $publications_by_year[$year][] = $pub;
    }
    
    ob_start();
    ?>
    <div class="rtm-publications-list">
        <div class="rtm-publications-content">
            <?php foreach ($publications_by_year as $year => $pubs): ?>
                <div class="rtm-publication-year" id="year-<?php echo esc_attr($year); ?>">
                    <h2 class="rtm-year-heading"><?php echo esc_html($year); ?></h2>
                    <?php foreach ($pubs as $pub): ?>
                        <div class="rtm-publication-item">
                            <div class="rtm-publication-title"><?php echo esc_html($pub['title']); ?></div>
                            <div class="rtm-publication-authors"><?php echo esc_html($pub['authors']); ?></div>
                            
                            <?php if (!empty($pub['journal'])): ?>
                                <div class="rtm-publication-journal"><em><?php echo esc_html($pub['journal']); ?></em></div>
                            <?php endif; ?>
                            
                            <div class="rtm-publication-links">
                                <?php if (!empty($pub['url'])): ?>
                                    <a href="<?php echo esc_url($pub['url']); ?>" target="_blank" class="rtm-url-link">View Publication</a>
                                <?php endif; ?>
                                
                                <?php if ($pub['citations'] > 0): ?>
                                    <span class="rtm-citation-count">Cited by <?php echo esc_html($pub['citations']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Year navigation -->
        <div class="rtm-year-navigation">
            <h3>Jump to Year:</h3>
            <ul>
                <?php foreach (array_keys($publications_by_year) as $year): ?>
                    <li><a href="#year-<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

// Register sorted_publications shortcode
add_shortcode('sorted_publications', 'rtm_sorted_publications_shortcode');