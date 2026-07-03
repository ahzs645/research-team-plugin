<?php
/**
 * Positions: an orderable taxonomy for team-member positions/ranks.
 *
 * "Position" reuses the plugin's existing `rtm_team_role` taxonomy — relabeled here
 * to "Position" so we don't add a second taxonomy and the block's `team_roles` field
 * keeps working. Each position term carries an integer `rtm_position_order` (its rank,
 * lower = higher up). Every member gets a denormalised `_rtm_position_rank` post-meta
 * (their term's order) so core Query Loop blocks can sort members by position without a
 * taxonomy-meta join in the block. The detailed free-text title (`_rtm_position`) is
 * left untouched — it still drives what shows on the profile.
 *
 * @package ResearchTeamManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Positions {

    /** Taxonomy used for positions (existing plugin taxonomy, relabeled). */
    const TAXONOMY   = 'rtm_team_role';
    /** Term meta holding a position's sort order (rank). */
    const ORDER_META = 'rtm_position_order';
    /** Post meta holding a member's numeric position rank (denormalised for querying). */
    const RANK_META  = '_rtm_position_rank';
    /** Rank assigned to members with no position, so they sort last. */
    const NO_RANK    = 9999;

    public function __construct() {
        // Relabel the taxonomy to "Position" and expose it to REST.
        add_filter('register_taxonomy_args', array($this, 'relabel_taxonomy'), 10, 2);

        // Order meta registration + admin UI on the term screen.
        add_action('init', array($this, 'register_meta'));
        add_action(self::TAXONOMY . '_add_form_fields',  array($this, 'render_add_field'));
        add_action(self::TAXONOMY . '_edit_form_fields', array($this, 'render_edit_field'), 10, 2);
        add_action('created_' . self::TAXONOMY, array($this, 'save_order'), 10, 1);
        add_action('edited_'  . self::TAXONOMY, array($this, 'save_order'), 10, 1);
        add_filter('manage_edit-' . self::TAXONOMY . '_columns', array($this, 'add_order_column'));
        add_filter('manage_' . self::TAXONOMY . '_custom_column', array($this, 'render_order_column'), 10, 3);
        add_filter('manage_edit-' . self::TAXONOMY . '_sortable_columns', array($this, 'add_sortable_column'));
        add_action('pre_get_terms', array($this, 'order_admin_term_list'));

        // Keep each member's denormalised rank in sync.
        add_action('save_post_rtm_team_member', array($this, 'sync_member_rank'), 20, 1);
        add_action('set_object_terms', array($this, 'sync_on_term_assignment'), 10, 4);
        add_action('edited_' . self::TAXONOMY, array($this, 'resync_all_members'), 20, 1);
        add_action('delete_' . self::TAXONOMY, array($this, 'resync_all_members'), 20, 1);

        // Order member Query Loops by position rank, then name.
        add_filter('query_loop_block_query_vars', array($this, 'order_query_loop_by_rank'), 10, 3);
    }

    /* ------------------------------------------------------------------ */
    /* Taxonomy relabel                                                    */
    /* ------------------------------------------------------------------ */

    public function relabel_taxonomy($args, $taxonomy) {
        if ($taxonomy !== self::TAXONOMY) {
            return $args;
        }
        $args['label']  = __('Positions', 'research-team-manager');
        $args['labels'] = array_merge((array) ($args['labels'] ?? array()), array(
            'name'          => __('Positions', 'research-team-manager'),
            'singular_name' => __('Position', 'research-team-manager'),
            'menu_name'     => __('Positions', 'research-team-manager'),
            'all_items'     => __('All Positions', 'research-team-manager'),
            'edit_item'     => __('Edit Position', 'research-team-manager'),
            'view_item'     => __('View Position', 'research-team-manager'),
            'update_item'   => __('Update Position', 'research-team-manager'),
            'add_new_item'  => __('Add New Position', 'research-team-manager'),
            'new_item_name' => __('New Position Name', 'research-team-manager'),
            'search_items'  => __('Search Positions', 'research-team-manager'),
            'not_found'     => __('No positions found', 'research-team-manager'),
        ));
        $args['show_in_rest']      = true;
        $args['show_admin_column'] = true;
        return $args;
    }

    /* ------------------------------------------------------------------ */
    /* Order meta + admin term UI                                          */
    /* ------------------------------------------------------------------ */

    public function register_meta() {
        register_term_meta(self::TAXONOMY, self::ORDER_META, array(
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ));
    }

    public function render_add_field($taxonomy) {
        ?>
        <div class="form-field term-<?php echo esc_attr(self::ORDER_META); ?>-wrap">
            <label for="<?php echo esc_attr(self::ORDER_META); ?>"><?php esc_html_e('Order', 'research-team-manager'); ?></label>
            <input type="number" name="<?php echo esc_attr(self::ORDER_META); ?>" id="<?php echo esc_attr(self::ORDER_META); ?>" value="0" step="1" min="0" />
            <p class="description"><?php esc_html_e('Lower numbers appear first when members are ordered by position (e.g. 1 = PI, 2 = Postdoc…).', 'research-team-manager'); ?></p>
        </div>
        <?php
    }

    public function render_edit_field($term, $taxonomy) {
        $value = (int) get_term_meta($term->term_id, self::ORDER_META, true);
        ?>
        <tr class="form-field term-<?php echo esc_attr(self::ORDER_META); ?>-wrap">
            <th scope="row"><label for="<?php echo esc_attr(self::ORDER_META); ?>"><?php esc_html_e('Order', 'research-team-manager'); ?></label></th>
            <td>
                <input type="number" name="<?php echo esc_attr(self::ORDER_META); ?>" id="<?php echo esc_attr(self::ORDER_META); ?>" value="<?php echo esc_attr($value); ?>" step="1" min="0" />
                <p class="description"><?php esc_html_e('Lower numbers appear first when members are ordered by position.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Persist the order for a term. Always writes a row (defaulting to 0) so the
     * meta_value_num ordering in order_admin_term_list() never drops a term.
     */
    public function save_order($term_id) {
        if (isset($_POST[self::ORDER_META])) {
            if (!current_user_can('manage_categories')) {
                return;
            }
            update_term_meta($term_id, self::ORDER_META, absint(wp_unslash($_POST[self::ORDER_META])));
        } elseif (!metadata_exists('term', $term_id, self::ORDER_META)) {
            update_term_meta($term_id, self::ORDER_META, 0);
        }
    }

    public function add_order_column($columns) {
        $columns[self::ORDER_META] = __('Order', 'research-team-manager');
        return $columns;
    }

    public function render_order_column($content, $column, $term_id) {
        if ($column === self::ORDER_META) {
            return (string) (int) get_term_meta($term_id, self::ORDER_META, true);
        }
        return $content;
    }

    public function add_sortable_column($columns) {
        $columns[self::ORDER_META] = self::ORDER_META;
        return $columns;
    }

    /**
     * Default the Positions admin list to rank order (and honour clicking the
     * "Order" column). Every term is guaranteed an order-meta row, so the
     * meta_value_num sort never excludes one.
     */
    public function order_admin_term_list($query) {
        if (!is_admin()) {
            return;
        }
        $taxes = (array) ($query->query_vars['taxonomy'] ?? array());
        if (!in_array(self::TAXONOMY, $taxes, true)) {
            return;
        }
        $requested = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : '';
        if ($requested === '' || $requested === self::ORDER_META) {
            $query->query_vars['meta_key'] = self::ORDER_META;
            $query->query_vars['orderby']  = 'meta_value_num';
            if (empty($_REQUEST['order'])) {
                $query->query_vars['order'] = 'ASC';
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Per-member rank sync                                                */
    /* ------------------------------------------------------------------ */

    /** Compute a member's rank: the lowest order among its position terms. */
    public function rank_for_member($post_id) {
        $terms = wp_get_object_terms($post_id, self::TAXONOMY, array('fields' => 'ids'));
        if (is_wp_error($terms) || empty($terms)) {
            return self::NO_RANK;
        }
        $min = self::NO_RANK;
        foreach ($terms as $tid) {
            $order = (int) get_term_meta($tid, self::ORDER_META, true);
            if ($order > 0 && $order < $min) {
                $min = $order;
            }
        }
        return $min;
    }

    public function sync_member_rank($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        update_post_meta($post_id, self::RANK_META, $this->rank_for_member($post_id));
    }

    public function sync_on_term_assignment($object_id, $terms, $tt_ids, $taxonomy) {
        if ($taxonomy !== self::TAXONOMY || get_post_type($object_id) !== 'rtm_team_member') {
            return;
        }
        update_post_meta($object_id, self::RANK_META, $this->rank_for_member($object_id));
    }

    /** Recompute every member's rank (used when a term's order changes/deletes). */
    public function resync_all_members() {
        $ids = get_posts(array(
            'post_type'   => 'rtm_team_member',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ));
        foreach ($ids as $id) {
            update_post_meta($id, self::RANK_META, $this->rank_for_member($id));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Front-end ordering                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Add "position" as a Query Loop orderby option: sort members by position
     * rank, then name. Scoped to orderBy === 'position' so it slots alongside the
     * plugin's existing member orderby options (start_date, custom_order, …)
     * instead of overriding them. Members all carry _rtm_position_rank (migration
     * + save sync), so the meta_value_num join never drops one.
     */
    public function order_query_loop_by_rank($query, $block = null, $page = null) {
        $post_type = $query['post_type'] ?? '';
        $is_member = ($post_type === 'rtm_team_member')
            || (is_array($post_type) && in_array('rtm_team_member', $post_type, true));
        if (!$is_member || ($query['orderby'] ?? '') !== 'position') {
            return $query;
        }
        $query['meta_key'] = self::RANK_META;
        $query['orderby']  = array('meta_value_num' => 'ASC', 'title' => 'ASC');
        return $query;
    }
}
