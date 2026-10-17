<?php
/**
 * Team workspaces: a sidebar menu per team (PI) and per-team permissions.
 *
 * Multiple-teams mode only. Two access models, chosen in Team Settings:
 *   - open:       anyone who can manage team members manages every team.
 *   - restricted: administrators and editors manage every team; everyone else
 *                 (typically the "Research Team Manager (PI)" role) only sees
 *                 and edits the teams assigned to them on their user profile.
 *
 * Team members use their own capabilities (edit_rtm_members, …) instead of
 * the post capabilities, so a PI can manage members without being able to
 * write blog posts. Existing roles receive the equivalent capabilities of
 * the post capabilities they already hold.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Team_Access {

    const TAXONOMY      = 'rtm_research_team';
    const ROLE          = 'rtm_team_manager';
    const USER_META     = 'rtm_managed_teams';
    const CAPS_VERSION  = '1';
    const MENU_PREFIX   = 'rtm-team-';

    /** Member capability => the post capability it mirrors for existing roles. */
    const MEMBER_CAPS = array(
        'edit_rtm_members'             => 'edit_posts',
        'edit_others_rtm_members'      => 'edit_others_posts',
        'edit_private_rtm_members'     => 'edit_private_posts',
        'edit_published_rtm_members'   => 'edit_published_posts',
        'publish_rtm_members'          => 'publish_posts',
        'read_private_rtm_members'     => 'read_private_posts',
        'delete_rtm_members'           => 'delete_posts',
        'delete_private_rtm_members'   => 'delete_private_posts',
        'delete_published_rtm_members' => 'delete_published_posts',
        'delete_others_rtm_members'    => 'delete_others_posts',
    );

    /** Re-entrancy guard for the team assignment correction. */
    private static $correcting = false;

    public static function boot() {
        add_action('init', array(__CLASS__, 'maybe_install_caps'), 1);
        add_filter('register_taxonomy_args', array(__CLASS__, 'taxonomy_caps'), 20, 3);
        add_filter('map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4);

        add_action('admin_menu', array(__CLASS__, 'register_menus'), 20);
        add_filter('parent_file', array(__CLASS__, 'parent_file'));
        add_filter('submenu_file', array(__CLASS__, 'submenu_file'));
        add_action('pre_get_posts', array(__CLASS__, 'scope_member_list'));
        add_filter('views_edit-rtm_team_member', array(__CLASS__, 'scope_list_views'));
        add_filter('admin_url', array(__CLASS__, 'add_new_in_team'), 10, 2);
        add_action('set_object_terms', array(__CLASS__, 'keep_team_assignments'), 10, 6);
        add_action('save_post_rtm_team_member', array(__CLASS__, 'assign_default_team'), 99, 3);

        add_action('show_user_profile', array(__CLASS__, 'user_teams_field'));
        add_action('edit_user_profile', array(__CLASS__, 'user_teams_field'));
        add_action('user_new_form', array(__CLASS__, 'user_teams_field'));
        add_action('personal_options_update', array(__CLASS__, 'save_user_teams'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_user_teams'));
        add_action('user_register', array(__CLASS__, 'save_user_teams'));
        add_filter('manage_users_columns', array(__CLASS__, 'users_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'users_column_value'), 10, 3);

        add_action(self::TAXONOMY . '_edit_form_fields', array(__CLASS__, 'team_managers_field'), 20);
        add_action('edited_' . self::TAXONOMY, array(__CLASS__, 'save_team_managers'));
    }

    /* ------------------------------------------------------------------
     * Settings
     * ---------------------------------------------------------------- */

    /** 'open' or 'restricted'. */
    public static function access_mode() {
        return get_option('rtm_team_access', 'open') === 'restricted' ? 'restricted' : 'open';
    }

    public static function menus_enabled() {
        return get_option('rtm_team_menus', '0') === '1';
    }

    /** 'pi' (lead names) or 'team' (team name). */
    public static function menu_label_mode() {
        return get_option('rtm_team_menu_label', 'pi') === 'team' ? 'team' : 'pi';
    }

    /** Team IDs that get a sidebar menu; empty means every team. */
    public static function menu_team_ids() {
        return array_values(array_filter(array_map('intval', (array) get_option('rtm_team_menu_teams', array()))));
    }

    /* ------------------------------------------------------------------
     * Roles and capabilities
     * ---------------------------------------------------------------- */

    public static function maybe_install_caps() {
        if (get_option('rtm_caps_version') !== self::CAPS_VERSION) {
            self::install_caps();
        }
    }

    /**
     * Give every role the member capabilities matching the post capabilities
     * it already has, so nobody loses access when members stop using post
     * capabilities. Also creates the team manager (PI) role.
     */
    public static function install_caps() {
        foreach (wp_roles()->role_objects as $role) {
            foreach (self::MEMBER_CAPS as $cap => $post_cap) {
                if ($role->has_cap($post_cap)) {
                    $role->add_cap($cap);
                }
            }
            if ($role->has_cap('manage_options') || $role->has_cap('edit_others_posts')) {
                $role->add_cap('rtm_manage_all_teams');
            }
            if ($role->has_cap('manage_options')) {
                $role->add_cap('rtm_manage_publications');
            }
        }

        $caps = array('read' => true, 'upload_files' => true, 'rtm_manage_publications' => true);
        foreach (array_keys(self::MEMBER_CAPS) as $cap) {
            $caps[$cap] = true;
        }
        remove_role(self::ROLE);
        add_role(self::ROLE, __('Research Team Manager (PI)', 'research-team-manager'), $caps);

        update_option('rtm_caps_version', self::CAPS_VERSION);
    }

    /** Remove the role and capabilities (uninstall). */
    public static function remove_caps() {
        remove_role(self::ROLE);
        foreach (wp_roles()->role_objects as $role) {
            foreach (array_merge(array_keys(self::MEMBER_CAPS), array('rtm_manage_all_teams', 'rtm_manage_publications')) as $cap) {
                $role->remove_cap($cap);
            }
        }
        delete_option('rtm_caps_version');
    }

    /** Taxonomies on team members are assigned with member capabilities. */
    public static function taxonomy_caps($args, $taxonomy, $object_type) {
        if (!in_array('rtm_team_member', (array) $object_type, true)) {
            return $args;
        }
        $caps = isset($args['capabilities']) ? (array) $args['capabilities'] : array();
        if (empty($caps['assign_terms']) || $caps['assign_terms'] === 'edit_posts') {
            $caps['assign_terms'] = 'edit_rtm_members';
        }
        if ($taxonomy === self::TAXONOMY) {
            // Team managers open their team's settings screen; adding and
            // deleting teams still needs manage_categories.
            $caps['manage_terms'] = 'edit_others_rtm_members';
        }
        $args['capabilities'] = $caps;
        return $args;
    }

    /** Whether team scoping applies to this user (restricted mode, not an admin/editor). */
    public static function is_scoped($user_id = 0) {
        $user_id = $user_id ? $user_id : get_current_user_id();
        return rtm_is_multiple_teams() && self::access_mode() === 'restricted' && !user_can($user_id, 'rtm_manage_all_teams');
    }

    /** Team IDs assigned to a user on their profile. */
    public static function assigned_team_ids($user_id) {
        return array_values(array_filter(array_map('intval', (array) get_user_meta($user_id, self::USER_META, true))));
    }

    /** Teams the user may manage: every team, or only their assigned ones when scoped. */
    public static function manageable_team_ids($user_id = 0) {
        $user_id = $user_id ? $user_id : get_current_user_id();
        if (!user_can($user_id, 'edit_others_rtm_members') && !user_can($user_id, 'rtm_manage_all_teams')) {
            return array();
        }
        $all = get_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids'));
        $all = is_wp_error($all) ? array() : array_map('intval', $all);
        if (!self::is_scoped($user_id)) {
            return $all;
        }
        return array_values(array_intersect($all, self::assigned_team_ids($user_id)));
    }

    public static function user_can_manage_team($team_id, $user_id = 0) {
        return in_array((int) $team_id, self::manageable_team_ids($user_id), true);
    }

    /** Teams a member post belongs to. */
    private static function member_team_ids($post_id) {
        $ids = wp_get_object_terms($post_id, self::TAXONOMY, array('fields' => 'ids'));
        return is_wp_error($ids) ? array() : array_map('intval', $ids);
    }

    /**
     * Scoped users can edit a member in one of their teams, or a member
     * without any team that they created (a new draft).
     */
    public static function map_meta_cap($caps, $cap, $user_id, $args) {
        if ($cap === 'edit_term' && !empty($args[0])) {
            $term = get_term((int) $args[0]);
            if ($term && !is_wp_error($term) && $term->taxonomy === self::TAXONOMY && rtm_is_multiple_teams() && self::user_can_manage_team($term->term_id, $user_id)) {
                return array('edit_others_rtm_members');
            }
            return $caps;
        }

        if (!in_array($cap, array('edit_post', 'delete_post', 'publish_post'), true) || empty($args[0])) {
            return $caps;
        }
        $post = get_post((int) $args[0]);
        if (!$post || $post->post_type !== 'rtm_team_member' || !self::is_scoped($user_id)) {
            return $caps;
        }
        $teams = self::member_team_ids($post->ID);
        $in_scope = $teams
            ? (bool) array_intersect($teams, self::assigned_team_ids($user_id))
            : (int) $post->post_author === (int) $user_id;
        if (!$in_scope) {
            $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    /** The member list only shows a scoped user's teams. */
    public static function scope_member_list($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'rtm_team_member' || !self::is_scoped()) {
            return;
        }
        $teams = self::manageable_team_ids();
        if (!$teams) {
            $query->set('post__in', array(0));
            return;
        }
        $tax_query   = (array) $query->get('tax_query');
        $tax_query[] = array('taxonomy' => self::TAXONOMY, 'field' => 'term_id', 'terms' => $teams);
        $query->set('tax_query', $tax_query);
    }

    /** Status counts cover every team, so scoped users see the links without them. */
    public static function scope_list_views($views) {
        if (!self::is_scoped()) {
            return $views;
        }
        foreach ($views as $key => $view) {
            $views[$key] = preg_replace('~\s*<span class="count">.*?</span>~', '', $view);
        }
        return $views;
    }

    /** "Add New" on a team's member list adds the member to that team. */
    public static function add_new_in_team($url, $path) {
        if ($path !== 'post-new.php?post_type=rtm_team_member' || !is_admin() || empty($_GET[self::TAXONOMY]) || !is_string($_GET[self::TAXONOMY])) {
            return $url;
        }
        $term = get_term_by('slug', sanitize_title(wp_unslash($_GET[self::TAXONOMY])), self::TAXONOMY);
        return $term ? add_query_arg('rtm_team', (int) $term->term_id, $url) : $url;
    }

    /**
     * A scoped user can only add or remove their own teams on a member.
     * Assignments to other teams are put back whatever the request sent.
     */
    public static function keep_team_assignments($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if (self::$correcting || $taxonomy !== self::TAXONOMY || get_post_type($object_id) !== 'rtm_team_member' || !self::is_scoped()) {
            return;
        }
        $allowed = self::manageable_team_ids();
        $to_ids  = function ($tt) {
            $term = get_term_by('term_taxonomy_id', (int) $tt, self::TAXONOMY);
            return $term ? (int) $term->term_id : 0;
        };
        $new   = array_filter(array_map($to_ids, (array) $tt_ids));
        $old   = array_filter(array_map($to_ids, (array) $old_tt_ids));
        $fixed = array_values(array_unique(array_merge(array_intersect($new, $allowed), array_diff($old, $allowed))));
        sort($fixed);
        $current = array_values(array_unique($new));
        sort($current);
        if ($fixed !== $current) {
            self::$correcting = true;
            wp_set_object_terms($object_id, $fixed, self::TAXONOMY);
            self::$correcting = false;
        }
    }

    /** New members created by a scoped user join the team they were added from. */
    public static function assign_default_team($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || $post->post_status === 'auto-draft' || !self::is_scoped()) {
            return;
        }
        $allowed = self::manageable_team_ids();
        if (!$allowed || array_intersect(self::member_team_ids($post_id), $allowed)) {
            return;
        }
        $context = isset($_POST['rtm_context_team']) ? (int) $_POST['rtm_context_team'] : 0;
        self::$correcting = true;
        wp_set_object_terms($post_id, array(in_array($context, $allowed, true) ? $context : $allowed[0]), self::TAXONOMY, true);
        self::$correcting = false;
    }

    /* ------------------------------------------------------------------
     * Sidebar menus
     * ---------------------------------------------------------------- */

    /** Teams that get a sidebar menu for the current user. */
    public static function menu_teams() {
        if (!rtm_is_multiple_teams()) {
            return array();
        }
        $ids = self::manageable_team_ids();
        if (!self::is_scoped()) {
            if (!self::menus_enabled()) {
                return array();
            }
            $chosen = self::menu_team_ids();
            if ($chosen) {
                $ids = array_values(array_intersect($ids, $chosen));
            }
        }
        if (!$ids) {
            return array();
        }
        $terms = get_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'include' => $ids, 'orderby' => 'name'));
        return is_wp_error($terms) ? array() : $terms;
    }

    public static function menu_label($term) {
        if (self::menu_label_mode() === 'pi') {
            $lead = rtm_team_lead_text($term->term_id);
            if ($lead !== '') {
                return $lead;
            }
        }
        return $term->name;
    }

    /** Links for a team's workspace, keyed by submenu label. */
    public static function team_links($term) {
        $id = (int) $term->term_id;
        return array(
            'members'      => array(__('Members', 'research-team-manager'), 'edit_others_rtm_members', 'edit.php?post_type=rtm_team_member&' . self::TAXONOMY . '=' . $term->slug),
            'add'          => array(__('Add Member', 'research-team-manager'), 'edit_others_rtm_members', 'post-new.php?post_type=rtm_team_member&rtm_team=' . $id),
            'publications' => array(__('Publications', 'research-team-manager'), 'rtm_manage_publications', 'edit.php?post_type=rtm_team_member&page=rtm-publications&rtm_team=' . $id),
            'settings'     => array(__('Team Settings', 'research-team-manager'), 'edit_others_rtm_members', 'term.php?taxonomy=' . self::TAXONOMY . '&tag_ID=' . $id . '&post_type=rtm_team_member'),
        );
    }

    public static function register_menus() {
        $position = 20.1;
        foreach (self::menu_teams() as $term) {
            $slug = self::MENU_PREFIX . $term->term_id;
            add_menu_page($term->name, esc_html(self::menu_label($term)), 'edit_others_rtm_members', $slug, array(__CLASS__, 'render_overview'), 'dashicons-groups', (string) $position);
            $position += 0.01;
            add_submenu_page($slug, $term->name, __('Overview', 'research-team-manager'), 'edit_others_rtm_members', $slug, array(__CLASS__, 'render_overview'));
            foreach (self::team_links($term) as $link) {
                add_submenu_page($slug, $link[0], $link[0], $link[1], $link[2]);
            }
        }
        // Scoped users work only from their team menus.
        if (self::is_scoped()) {
            remove_menu_page('edit.php?post_type=rtm_team_member');
        }
    }

    /** The team the current admin screen is about, if it has a menu. */
    private static function context_team() {
        global $pagenow;
        $term = null;
        if (!empty($_GET['page']) && strpos($_GET['page'], self::MENU_PREFIX) === 0) {
            $term = get_term((int) substr($_GET['page'], strlen(self::MENU_PREFIX)), self::TAXONOMY);
        } elseif (!empty($_GET[self::TAXONOMY]) && is_string($_GET[self::TAXONOMY])) {
            $term = get_term_by('slug', sanitize_title(wp_unslash($_GET[self::TAXONOMY])), self::TAXONOMY);
        } elseif (!empty($_GET['rtm_team'])) {
            $term = get_term((int) $_GET['rtm_team'], self::TAXONOMY);
        } elseif ($pagenow === 'term.php' && ($_GET['taxonomy'] ?? '') === self::TAXONOMY && !empty($_GET['tag_ID'])) {
            $term = get_term((int) $_GET['tag_ID'], self::TAXONOMY);
        } elseif ($pagenow === 'post.php' && !empty($_GET['post']) && get_post_type((int) $_GET['post']) === 'rtm_team_member') {
            $menu_ids = wp_list_pluck(self::menu_teams(), 'term_id');
            foreach (self::member_team_ids((int) $_GET['post']) as $team_id) {
                if (in_array($team_id, $menu_ids, true)) {
                    $term = get_term($team_id, self::TAXONOMY);
                    break;
                }
            }
        }
        if (!$term || is_wp_error($term) || !in_array((int) $term->term_id, wp_list_pluck(self::menu_teams(), 'term_id'), true)) {
            return null;
        }
        return $term;
    }

    public static function parent_file($parent_file) {
        global $_wp_real_parent_file;
        $term = self::context_team();
        if (!$term) {
            return $parent_file;
        }
        // get_admin_page_parent() runs after this filter and would match the
        // member list or Publications under the global menu first; point that
        // parent at the team menu for this request.
        $_wp_real_parent_file['edit.php?post_type=rtm_team_member'] = self::MENU_PREFIX . $term->term_id;
        return self::MENU_PREFIX . $term->term_id;
    }

    public static function submenu_file($submenu_file) {
        global $pagenow;
        $term = self::context_team();
        if (!$term) {
            return $submenu_file;
        }
        $links = self::team_links($term);
        if (!empty($_GET['page']) && $_GET['page'] === 'rtm-publications') {
            return $links['publications'][2];
        }
        if ($pagenow === 'post-new.php') {
            return $links['add'][2];
        }
        if ($pagenow === 'term.php') {
            return $links['settings'][2];
        }
        if ($pagenow === 'edit.php' || $pagenow === 'post.php') {
            return $links['members'][2];
        }
        return $submenu_file;
    }

    public static function render_overview() {
        $id   = isset($_GET['page']) ? (int) substr(sanitize_key($_GET['page']), strlen(self::MENU_PREFIX)) : 0;
        $term = get_term($id, self::TAXONOMY);
        if (!$term || is_wp_error($term) || !self::user_can_manage_team($id)) {
            wp_die(esc_html__('You cannot manage this team.', 'research-team-manager'));
        }
        global $wpdb;
        $members = (int) (new WP_Query(array('post_type' => 'rtm_team_member', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array(array('taxonomy' => self::TAXONOMY, 'terms' => $id)))))->found_posts;
        $pubs    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}rtm_publications WHERE team_id = %d", $id));
        $lead    = rtm_team_lead_text($id);
        $page    = get_term_link($term);
        $managers = get_users(array('meta_key' => self::USER_META, 'fields' => array('ID', 'display_name')));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($term->name); ?></h1>
            <?php if ($lead): ?><p style="font-size:14px;"><?php printf(esc_html__('Led by %s', 'research-team-manager'), '<strong>' . esc_html($lead) . '</strong>'); ?></p><?php endif; ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; max-width:760px; margin:20px 0;">
                <div class="card" style="margin:0;"><h2 style="margin:0 0 4px;"><?php echo esc_html(number_format_i18n($members)); ?></h2><p style="margin:0;"><?php esc_html_e('Members', 'research-team-manager'); ?></p></div>
                <div class="card" style="margin:0;"><h2 style="margin:0 0 4px;"><?php echo esc_html(number_format_i18n($pubs)); ?></h2><p style="margin:0;"><?php esc_html_e('Publications', 'research-team-manager'); ?></p></div>
            </div>
            <p>
                <?php foreach (self::team_links($term) as $link): if (current_user_can($link[1])): ?>
                    <a class="button" href="<?php echo esc_url(admin_url($link[2])); ?>"><?php echo esc_html($link[0]); ?></a>
                <?php endif; endforeach; ?>
                <?php if (!is_wp_error($page)): ?><a class="button" href="<?php echo esc_url($page); ?>" target="_blank"><?php esc_html_e('View team page', 'research-team-manager'); ?></a><?php endif; ?>
            </p>
            <?php if (self::access_mode() === 'restricted'): ?>
                <h2><?php esc_html_e('Team managers', 'research-team-manager'); ?></h2>
                <?php
                $names = array();
                foreach ($managers as $user) {
                    if (in_array($id, self::assigned_team_ids($user->ID), true)) {
                        $names[] = $user->display_name;
                    }
                }
                ?>
                <p><?php echo $names ? esc_html(implode(', ', $names)) : esc_html__('No one is assigned to this team yet. Administrators and editors can still manage it.', 'research-team-manager'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Assigning teams to users
     * ---------------------------------------------------------------- */

    private static function all_teams() {
        $terms = get_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'orderby' => 'name'));
        return is_wp_error($terms) ? array() : $terms;
    }

    /** "Research teams" checkboxes on the user profile and Add New User screens. */
    public static function user_teams_field($user) {
        if (!rtm_is_multiple_teams() || !current_user_can('promote_users')) {
            return;
        }
        $assigned = $user instanceof WP_User ? self::assigned_team_ids($user->ID) : array();
        $teams    = self::all_teams();
        ?>
        <h2><?php esc_html_e('Research teams', 'research-team-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Teams this user manages', 'research-team-manager'); ?></th>
                <td>
                    <input type="hidden" name="rtm_managed_teams_present" value="1" />
                    <?php if (!$teams): ?>
                        <p class="description"><?php esc_html_e('No teams yet.', 'research-team-manager'); ?></p>
                    <?php endif; ?>
                    <?php foreach ($teams as $team): ?>
                        <label style="display:block; margin:4px 0;"><input type="checkbox" name="rtm_managed_teams[]" value="<?php echo (int) $team->term_id; ?>" <?php checked(in_array((int) $team->term_id, $assigned, true)); ?> /> <?php echo esc_html($team->name); ?></label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php echo self::access_mode() === 'restricted'
                            ? esc_html__('With restricted team access, this user only sees and edits these teams (unless they are an administrator or editor). Give them the “Research Team Manager (PI)” role to manage members without other site access.', 'research-team-manager')
                            : esc_html__('Team access is currently open, so these assignments take effect when you switch to restricted access in Team Settings.', 'research-team-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_user_teams($user_id) {
        if (empty($_POST['rtm_managed_teams_present']) || !current_user_can('promote_users') || !current_user_can('edit_user', $user_id)) {
            return;
        }
        $valid = wp_list_pluck(self::all_teams(), 'term_id');
        $ids   = array_values(array_intersect(array_map('intval', (array) ($_POST['rtm_managed_teams'] ?? array())), array_map('intval', $valid)));
        if ($ids) {
            update_user_meta($user_id, self::USER_META, $ids);
        } else {
            delete_user_meta($user_id, self::USER_META);
        }
    }

    public static function users_column($columns) {
        if (rtm_is_multiple_teams()) {
            $columns['rtm_teams'] = __('Research teams', 'research-team-manager');
        }
        return $columns;
    }

    public static function users_column_value($value, $column, $user_id) {
        if ($column !== 'rtm_teams') {
            return $value;
        }
        $names = array();
        foreach (self::assigned_team_ids($user_id) as $id) {
            $term = get_term($id, self::TAXONOMY);
            if ($term && !is_wp_error($term)) {
                $names[] = esc_html($term->name);
            }
        }
        return $names ? implode(', ', $names) : '—';
    }

    /** Users who can be assigned on the team screen: PIs plus anyone already assigned. */
    private static function eligible_users() {
        $out = array();
        $lists = array(
            get_users(array('role' => self::ROLE, 'orderby' => 'display_name')),
            get_users(array('meta_key' => self::USER_META, 'orderby' => 'display_name')),
        );
        foreach ($lists as $users) {
            foreach ($users as $user) {
                $out[$user->ID] = $user;
            }
        }
        return $out;
    }

    /** "Team managers" checkboxes on the team edit screen. */
    public static function team_managers_field($term) {
        if (!rtm_is_multiple_teams() || !current_user_can('promote_users')) {
            return;
        }
        $users = self::eligible_users();
        ?>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Team managers', 'research-team-manager'); ?></th>
            <td>
                <input type="hidden" name="rtm_team_managers_present" value="1" />
                <?php if (!$users): ?>
                    <p class="description"><?php printf(
                        /* translators: %s: Add New User URL */
                        wp_kses_post(__('No team manager accounts yet. <a href="%s">Add a user</a> with the “Research Team Manager (PI)” role.', 'research-team-manager')),
                        esc_url(admin_url('user-new.php'))
                    ); ?></p>
                <?php endif; ?>
                <?php foreach ($users as $user): ?>
                    <label style="display:block; margin:4px 0;"><input type="checkbox" name="rtm_team_managers[]" value="<?php echo (int) $user->ID; ?>" <?php checked(in_array((int) $term->term_id, self::assigned_team_ids($user->ID), true)); ?> /> <?php echo esc_html($user->display_name); ?> <span class="description">(<?php echo esc_html($user->user_login); ?>)</span></label>
                <?php endforeach; ?>
                <p class="description"><?php esc_html_e('WordPress users who manage this team’s members, publications and settings when team access is restricted.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_team_managers($term_id) {
        if (empty($_POST['rtm_team_managers_present']) || !current_user_can('promote_users')) {
            return;
        }
        $chosen = array_map('intval', (array) ($_POST['rtm_team_managers'] ?? array()));
        foreach (self::eligible_users() as $user) {
            $teams = self::assigned_team_ids($user->ID);
            $has   = in_array((int) $term_id, $teams, true);
            $want  = in_array((int) $user->ID, $chosen, true);
            if ($want && !$has) {
                $teams[] = (int) $term_id;
            } elseif (!$want && $has) {
                $teams = array_values(array_diff($teams, array((int) $term_id)));
            } else {
                continue;
            }
            if ($teams) {
                update_user_meta($user->ID, self::USER_META, $teams);
            } else {
                delete_user_meta($user->ID, self::USER_META);
            }
        }
    }
}
