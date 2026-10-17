<?php
/**
 * Researcher directory ("research network") features.
 *
 * Merged from the former University Research Network add-on (1.2.1). Content
 * stays compatible with that add-on: the same taxonomy names, meta keys, block
 * names and pattern names are used, so existing pages, Query Loops and
 * imported records keep working after the add-on is deactivated.
 *
 * Adds profile taxonomies (faculty, department, appointment, supervision,
 * theme, membership, institution), institutional affiliations, a keyword
 * search index, the Researcher Field / Researcher Filters / Directory Tabs
 * blocks, a Researcher Directory Query Loop variation and directory/profile
 * patterns. Lab assignments stay in the Research Team taxonomy.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Network {

    const OPTION = 'rtm_network_enabled';

    /** Meta keys whose changes refresh the search index. */
    const INDEXED_META = array('_rtmn_unbc_record', '_rtmn_affiliations', '_rtm_position', '_rtm_long_description', '_rtm_short_description');

    /** Query-string filter key => taxonomy. */
    const FILTERS = array(
        'rn_faculty'     => 'rtmn_faculty',
        'rn_department'  => 'rtmn_department',
        'rn_appointment' => 'rtmn_appointment',
        'rn_supervision' => 'rtmn_supervision',
        'rn_topic'       => 'rtm_research_area',
        'rn_theme'       => 'rtmn_theme',
        'rn_membership'  => 'rtmn_membership',
        'rn_institution' => 'rtmn_institution',
    );

    /**
     * Boot after every plugin has loaded, so the former add-on is detected
     * whatever its folder name or load order.
     */
    public static function boot() {
        add_action('plugins_loaded', array(__CLASS__, 'maybe_start'), 20);
    }

    public static function maybe_start() {
        if (self::legacy_addon_active()) {
            // Both would register the same blocks and taxonomies; let the
            // add-on keep running until an administrator deactivates it.
            add_action('admin_notices', array(__CLASS__, 'legacy_addon_notice'));
            return;
        }
        if (!self::is_enabled()) {
            return;
        }

        add_action('init', array(__CLASS__, 'register'), 30);
        add_filter('register_taxonomy_args', array(__CLASS__, 'research_area_in_rest'), 10, 2);
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue_editor_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_public_assets'));

        add_action('save_post_rtm_team_member', array(__CLASS__, 'save_affiliations'), 50);
        add_action('save_post_rtm_team_member', array(__CLASS__, 'index'), 100);
        add_action('set_object_terms', array(__CLASS__, 'index_on_terms'), 100, 4);
        add_action('updated_post_meta', array(__CLASS__, 'index_on_meta'), 100, 3);
        add_action('added_post_meta', array(__CLASS__, 'index_on_meta'), 100, 3);
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));

        add_filter('query_loop_block_query_vars', array(__CLASS__, 'query_loop_vars'), 20, 2);
        add_filter('posts_search', array(__CLASS__, 'posts_search'), 20, 2);
        add_filter('pre_render_block', array(__CLASS__, 'hide_inactive_view'), 10, 2);
        add_filter('pre_render_block', array(__CLASS__, 'hide_empty_profile_sections'), 20, 2);
        add_action('template_redirect', array(__CLASS__, 'redirect_labs_page'));
    }

    /** Whether the separate University Research Network add-on is running. */
    public static function legacy_addon_active() {
        return defined('RTMN_VERSION') || function_exists('rtmn_taxonomies');
    }

    public static function legacy_addon_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('Research Team Manager now includes the researcher directory from the University Research Network add-on. Deactivate the add-on to use the built-in version; your researchers, filters and pages are kept.', 'research-team-manager') . '</p></div>';
    }

    /**
     * Enabled when an administrator turned it on, or, on first run, when the
     * site already has researcher directory data from the former add-on.
     */
    public static function is_enabled() {
        $value = get_option(self::OPTION, null);
        if ($value === null) {
            $value = self::has_existing_data() ? '1' : '0';
            update_option(self::OPTION, $value);
        }
        return $value === '1';
    }

    private static function has_existing_data() {
        global $wpdb;
        $found = $wpdb->get_var("SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key IN ('_rtmn_search', '_rtmn_affiliations', '_rtmn_unbc_record') LIMIT 1");
        return (bool) $found;
    }

    /** Taxonomy => singular label. Filterable so sites can rename sections. */
    public static function taxonomies() {
        return apply_filters('rtm_network_taxonomies', array(
            'rtmn_faculty'      => 'Faculty',
            'rtmn_department'   => 'Department',
            'rtmn_appointment'  => 'Appointment type',
            'rtmn_supervision'  => 'Graduate supervision',
            'rtm_research_area' => 'Research topic',
            'rtmn_theme'        => 'Research theme',
            'rtmn_membership'   => 'HRI membership',
            'rtmn_institution'  => 'Institution / affiliation',
        ));
    }

    /** Public URL of the researcher directory page. */
    public static function directory_url() {
        return apply_filters('rtm_network_directory_url', home_url('/researchers/'));
    }

    /** Page slug the directory tabs and Labs redirect apply to. */
    public static function directory_page() {
        return apply_filters('rtm_network_directory_page', 'researchers');
    }

    public static function register() {
        if (!post_type_exists('rtm_team_member')) {
            return;
        }
        add_post_type_support('rtm_team_member', array('editor', 'custom-fields', 'revisions'));

        $plural = array(
            'rtmn_faculty'     => 'Faculties',
            'rtmn_department'  => 'Departments',
            'rtmn_appointment' => 'Appointment Types',
            'rtmn_supervision' => 'Graduate Supervision',
            'rtmn_theme'       => 'Research Themes',
            'rtmn_membership'  => 'HRI Membership Categories',
            'rtmn_institution' => 'Institutions / Affiliations',
        );
        foreach (apply_filters('rtm_network_taxonomy_labels', $plural) as $name => $label) {
            register_taxonomy($name, array('rtm_team_member'), array(
                'label'             => $label,
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => false,
                'query_var'         => false,
            ));
        }

        register_post_meta('rtm_team_member', '_rtmn_affiliations', array(
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => array('schema' => array(
                'type'  => 'array',
                'items' => array(
                    'type'                 => 'object',
                    'properties'           => array('title' => array('type' => 'string'), 'affiliation' => array('type' => 'string')),
                    'additionalProperties' => false,
                ),
            )),
            'auth_callback'     => function ($allowed, $key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
            'sanitize_callback' => array(__CLASS__, 'clean_affiliations'),
        ));

        $base = RTM_PLUGIN_URL . 'assets/network/';
        wp_register_script('rtmn-editor', $base . 'editor.js', array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'), RTM_VERSION, true);
        wp_register_script('rtmn-filters-view', $base . 'filters.js', array(), RTM_VERSION, true);
        wp_register_script('rtmn-public', $base . 'public.js', array(), RTM_VERSION, true);
        wp_register_style('rtmn-directory', $base . 'network.css', array(), RTM_VERSION);

        $supports = array(
            'html'       => false,
            'color'      => array('text' => true, 'background' => true),
            'spacing'    => array('margin' => true, 'padding' => true),
            'typography' => array('fontSize' => true, 'lineHeight' => true),
        );
        register_block_type('rtmn/field', array(
            'api_version'     => 3,
            'title'           => 'Researcher Field',
            'category'        => 'widgets',
            'editor_script'   => 'rtmn-editor',
            'style'           => 'rtmn-directory',
            'uses_context'    => array('postId', 'postType'),
            'supports'        => $supports,
            'attributes'      => array(
                'field'         => array('type' => 'string', 'default' => 'position'),
                'previewPostId' => array('type' => 'number', 'default' => 0),
            ),
            'render_callback' => array(__CLASS__, 'render_field'),
        ));
        register_block_type('rtmn/filters', array(
            'api_version'     => 3,
            'title'           => 'Researcher Filters',
            'category'        => 'widgets',
            'editor_script'   => 'rtmn-editor',
            'view_script'     => 'rtmn-filters-view',
            'style'           => 'rtmn-directory',
            'supports'        => array('html' => false),
            'render_callback' => array(__CLASS__, 'render_filters'),
        ));
        register_block_type('rtmn/directory-tabs', array(
            'api_version'     => 3,
            'title'           => 'Directory Tabs',
            'category'        => 'widgets',
            'editor_script'   => 'rtmn-editor',
            'view_script'     => 'rtmn-filters-view',
            'style'           => 'rtmn-directory',
            'supports'        => array('html' => false),
            'render_callback' => array(__CLASS__, 'render_tabs'),
        ));

        register_block_pattern_category('rtmn', array('label' => 'Research Network'));
        register_block_pattern('rtmn/directory', array(
            'title'       => 'University Researcher Directory',
            'categories'  => array('rtmn'),
            'description' => 'Search filters, an editable Research Team Member Query Loop, and pagination.',
            'content'     => file_get_contents(RTM_PLUGIN_PATH . 'patterns/researcher-directory.html'),
        ));
        register_block_pattern('rtmn/profile', array(
            'title'         => 'Researcher Profile',
            'categories'    => array('rtmn'),
            'description'   => 'A researcher profile page built from Researcher Field blocks.',
            'templateTypes' => array('single-rtm_team_member'),
            'content'       => file_get_contents(RTM_PLUGIN_PATH . 'patterns/researcher-profile.html'),
        ));
    }

    /** Research topics appear as filters and in the block editor sidebar. */
    public static function research_area_in_rest($args, $taxonomy) {
        if ($taxonomy === 'rtm_research_area') {
            $args['show_in_rest'] = true;
            $args['rest_base']    = 'rtm_research_area';
        }
        return $args;
    }

    public static function enqueue_editor_assets() {
        wp_enqueue_script('rtmn-editor');
        wp_enqueue_style('rtmn-directory');
    }

    public static function enqueue_public_assets() {
        wp_enqueue_script('rtmn-public');
    }

    /* ------------------------------------------------------------------
     * Data helpers
     * ---------------------------------------------------------------- */

    public static function clean_affiliations($value) {
        if (!is_array($value)) {
            return array();
        }
        $out = array();
        foreach (array_slice($value, 0, 10) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $r = array(
                'title'       => sanitize_text_field($row['title'] ?? ''),
                'affiliation' => sanitize_text_field($row['affiliation'] ?? ''),
            );
            if ($r['title'] !== '' || $r['affiliation'] !== '') {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Numeric-only positions (sort keys) are not shown publicly. */
    public static function public_position($value) {
        return preg_match('/^[\d\s-]+$/', trim((string) $value)) ? '' : (string) $value;
    }

    public static function affiliations($id) {
        return self::clean_affiliations(get_post_meta($id, '_rtmn_affiliations', true));
    }

    /** Normalized institution names for the Institution taxonomy. */
    public static function institution_names($rows) {
        $names = array();
        foreach (self::clean_affiliations($rows) as $a) {
            $org = trim($a['affiliation']);
            if (!$org || in_array(strtolower($org), array('associate professor', 'stat 240'), true)) {
                continue;
            }
            if (preg_match('/\bunbc\b|univer(?:sity|isty) of northern british columbia/i', $org)) {
                $org = 'University of Northern British Columbia';
            } elseif (in_array(strtolower($org), array('ubc', 'university of british columbia'), true)) {
                $org = 'University of British Columbia';
            } elseif ($org === 'Northern Health Authority') {
                $org = 'Northern Health';
            }
            $names[] = $org;
        }
        return array_values(array_unique($names));
    }

    public static function term_names($id, $taxonomy) {
        $v = wp_get_object_terms($id, $taxonomy, array('fields' => 'names'));
        return is_wp_error($v) ? array() : $v;
    }

    /** Imported university directory record (read-only source values). */
    public static function source_record($id) {
        $r = get_post_meta($id, '_rtmn_unbc_record', true);
        return is_array($r) ? $r : array();
    }

    private static function request_value($key) {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(sanitize_text_field(wp_unslash($_GET[$key])), 0, 200) : '';
    }

    private static function initials($id) {
        $words = preg_split('/\s+/u', preg_replace('/^Dr\.\s+/i', '', get_the_title($id)));
        return mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1);
    }

    /* ------------------------------------------------------------------
     * Search index
     * ---------------------------------------------------------------- */

    public static function index($id) {
        if (get_post_type($id) !== 'rtm_team_member' || wp_is_post_revision($id)) {
            return;
        }
        $parts = array(
            get_the_title($id),
            get_post_meta($id, '_rtm_position', true),
            get_post_meta($id, '_rtm_long_description', true),
            get_post_meta($id, '_rtm_short_description', true),
            get_post_field('post_content', $id),
        );
        foreach (self::affiliations($id) as $a) {
            $parts[] = implode(' ', $a);
        }
        $record = self::source_record($id);
        foreach (array('name', 'positions', 'departments', 'faculties', 'expertise_terms', 'research_fields') as $key) {
            if (!empty($record[$key])) {
                $parts[] = is_array($record[$key]) ? implode(' ', $record[$key]) : $record[$key];
            }
        }
        foreach (array_keys(self::taxonomies()) as $tax) {
            $parts[] = implode(' ', self::term_names($id, $tax));
        }
        update_post_meta($id, '_rtmn_search', wp_strip_all_tags(implode(' ', $parts)));
    }

    public static function index_on_terms($id, $terms, $tt_ids, $taxonomy) {
        if (array_key_exists($taxonomy, self::taxonomies())) {
            self::index($id);
        }
    }

    public static function index_on_meta($meta_id, $id, $key) {
        if (in_array($key, self::INDEXED_META, true)) {
            self::index($id);
        }
    }

    /* ------------------------------------------------------------------
     * Directory query
     * ---------------------------------------------------------------- */

    public static function query_args($args) {
        $search = self::request_value('rn_q');
        if ($search !== '') {
            $args['rtmn_search'] = $search;
        }
        $tax_query = $args['tax_query'] ?? array();
        foreach (self::FILTERS as $key => $tax) {
            $value = self::request_value($key);
            if ($value === '') {
                continue;
            }
            $term        = get_term_by('slug', sanitize_title($value), $tax);
            $tax_query[] = array('taxonomy' => $tax, 'field' => 'term_id', 'terms' => array($term ? (int) $term->term_id : 0));
        }
        if ($tax_query) {
            $tax_query['relation'] = 'AND';
            $args['tax_query']     = $tax_query;
        }
        return $args;
    }

    public static function query_loop_vars($args, $block) {
        if (!empty($block->context['query']['rtmnDirectory']) && ($args['post_type'] ?? '') === 'rtm_team_member') {
            return self::query_args($args);
        }
        return $args;
    }

    /** Each search word must match the title or the search index. */
    public static function posts_search($search, $query) {
        if (!$query->get('rtmn_search') || $query->get('post_type') !== 'rtm_team_member') {
            return $search;
        }
        global $wpdb;
        foreach (array_slice(preg_split('/\s+/u', trim($query->get('rtmn_search'))), 0, 10) as $word) {
            $like    = '%' . $wpdb->esc_like($word) . '%';
            $search .= $wpdb->prepare(" AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} rn_search WHERE rn_search.post_id={$wpdb->posts}.ID AND rn_search.meta_key='_rtmn_search' AND rn_search.meta_value LIKE %s))", $like, $like);
        }
        return $search;
    }

    /* ------------------------------------------------------------------
     * Blocks
     * ---------------------------------------------------------------- */

    /** Researcher filters submit a normal GET request. Reset discards pagination. */
    public static function render_filters() {
        $url        = self::directory_url();
        $labels     = self::taxonomies();
        $active     = 0;
        foreach (array_keys(self::FILTERS) as $key) {
            if (self::request_value($key) !== '') {
                $active++;
            }
        }
        $panel_id = wp_unique_id('rtmn-filter-panel-');
        ob_start(); ?>
    <section <?php echo get_block_wrapper_attributes(array('class' => 'rtmn-filters')); ?> aria-label="Filter researchers">
    <form method="get" action="<?php echo esc_url($url); ?>">
      <label class="rtmn-search-label" for="rn-search">Find expertise or a researcher</label>
      <div class="rtmn-search-toolbar">
        <input id="rn-search" name="rn_q" type="search" value="<?php echo esc_attr(self::request_value('rn_q')); ?>" placeholder="Name, research topic or institution…" maxlength="200" />
        <button type="button" class="rtmn-filter-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>">Filters<?php if ($active) { echo ' <span class="rtmn-active-filter-count">' . (int) $active . ' active</span>'; } ?></button>
        <div class="rtmn-filter-actions"><button type="submit">Find researchers</button><a href="<?php echo esc_url($url); ?>">Clear filters</a></div>
      </div>
      <input type="hidden" name="directory" value="researchers" />
      <div id="<?php echo esc_attr($panel_id); ?>" class="rtmn-filter-panel" hidden>
        <div class="rtmn-filter-grid">
        <?php foreach (self::FILTERS as $key => $tax): $label = $labels[$tax] ?? $tax; ?>
          <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?><select aria-label="<?php echo esc_attr($label); ?>" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>"><option value="">All</option><?php
            $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => true, 'orderby' => 'name'));
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($term->slug), selected(self::request_value($key), $term->slug, false), esc_html($term->name));
                }
            }
          ?></select></label>
        <?php endforeach; ?>
        </div>
      </div>
    </form></section><?php
        return ob_get_clean();
    }

    public static function directory_view() {
        return self::request_value('directory') === 'labs' ? 'labs' : 'researchers';
    }

    public static function render_tabs() {
        $view   = self::directory_view();
        $url    = self::directory_url();
        $counts = wp_count_posts('rtm_team_member');
        $people = (int) $counts->publish;
        $labs   = wp_count_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false));
        $labs   = is_wp_error($labs) ? 0 : (int) $labs;
        $out    = '<nav ' . get_block_wrapper_attributes(array('class' => 'rtmn-directory-tabs')) . ' aria-label="Research directory">';
        foreach (array('researchers' => array('Researchers', $people), 'labs' => array('Labs &amp; institutes', $labs)) as $key => $item) {
            $href = $key === 'labs' ? add_query_arg('directory', 'labs', $url) : $url;
            $out .= '<a href="' . esc_url($href) . '"' . ($view === $key ? ' aria-current="page"' : '') . '>' . $item[0] . ' <span>' . (int) $item[1] . '</span></a>';
        }
        return $out . '</nav>';
    }

    public static function render_field($attrs, $content, $block) {
        $id = (int) ($block->context['postId'] ?? 0);
        if (!$id && !empty($attrs['previewPostId']) && defined('REST_REQUEST') && REST_REQUEST && current_user_can('edit_posts')) {
            $id = (int) $attrs['previewPostId'];
        }
        if (!$id) {
            $id = get_the_ID();
        }
        if (get_post_type($id) !== 'rtm_team_member' || (get_post_status($id) !== 'publish' && !current_user_can('edit_post', $id))) {
            return '';
        }
        $field = $attrs['field'] ?? 'position';
        $html  = self::field_html($field, $id);
        return $html !== '' ? '<div ' . get_block_wrapper_attributes(array('class' => 'rtmn-field rtmn-field--' . sanitize_html_class($field))) . '>' . $html . '</div>' : '';
    }

    /** Inner HTML for one Researcher Field, or '' when there is nothing to show. */
    public static function field_html($field, $id) {
        $r    = self::source_record($id);
        $html = '';
        switch ($field) {
            case 'portrait':
                $html = get_the_post_thumbnail($id, 'medium', array('class' => 'rtmn-portrait', 'alt' => get_the_title($id)));
                if (!$html && !empty($r['portrait_url']) && preg_match('#^https://www\.unbc\.ca/sites/#', $r['portrait_url'])) {
                    $html = '<img class="rtmn-portrait rtmn-source-portrait" src="' . esc_url($r['portrait_url']) . '" alt="' . esc_attr(get_the_title($id)) . '" width="160" height="160" loading="lazy" decoding="async" /><span hidden class="rtmn-initials rtmn-portrait-fallback" aria-label="No photograph available">' . esc_html(self::initials($id)) . '</span>';
                } elseif (!$html) {
                    $html = '<span class="rtmn-initials" aria-label="No photograph supplied">' . esc_html(self::initials($id)) . '</span>';
                }
                break;

            case 'position':
                $html = esc_html(self::public_position(get_post_meta($id, '_rtm_position', true)));
                break;

            case 'institution':
            case 'affiliations':
                $names = self::institution_names(self::affiliations($id));
                if (!$names && $r) {
                    $names = array('University of Northern British Columbia');
                }
                $html = esc_html(implode(' · ', $field === 'institution' ? array_slice($names, 0, 1) : $names));
                break;

            case 'academic_units':
                $deps = self::term_names($id, 'rtmn_department');
                $fac  = self::term_names($id, 'rtmn_faculty');
                $html = $deps ? '<p>' . esc_html(implode(' · ', $deps)) . '</p>' : '';
                if ($fac) {
                    $html .= '<p class="rtmn-muted">' . esc_html(implode(' · ', $fac)) . '</p>';
                }
                break;

            case 'expertise':
                $values = $r['expertise_terms'] ?? array();
                $html   = $values ? '<p>' . esc_html(implode(' ', $values)) . '</p>' : '';
                break;

            case 'appointments':
                $current = get_post_meta($id, '_rtm_position', true);
                $values  = array_values(array_filter($r['positions'] ?? array(), function ($v) use ($current) {
                    return $v !== $current;
                }));
                if (!empty($r['historical_listing'])) {
                    $html = '<p>Listed under former faculty on a UNBC departmental roster.</p>';
                } elseif (!empty($r['appointment_under_review'])) {
                    $html = '<p>UNBC has two profiles with different appointment titles: ' . esc_html(implode('; ', $r['positions'])) . '.</p>';
                } elseif ($values) {
                    $html = '<p>UNBC-listed appointments: ' . esc_html(implode('; ', $values)) . '</p>';
                }
                $lab_role = get_post_meta($id, '_rtmn_lab_role', true);
                if ($lab_role) {
                    $html .= '<p>Lab role: ' . esc_html($lab_role) . '</p>';
                }
                break;

            case 'supervision':
                $states = $r['supervision_status'] ?? array();
                if ($states && empty($r['historical_listing']) && empty($r['appointment_under_review'])) {
                    $html = '<p>' . esc_html(implode('; ', $states)) . '. <span class="rtmn-muted">As reported by UNBC' . (!empty($r['retrieved_at']) ? ' on ' . esc_html(wp_date('F j, Y', strtotime($r['retrieved_at']))) : '') . '.</span></p>';
                    if (!empty($r['supervision_programs'])) {
                        $html .= '<p>Programs: ' . esc_html(implode('; ', $r['supervision_programs'])) . '</p>';
                    }
                } else {
                    $html = '<p>Contact the researcher to ask about supervision or collaboration.</p>';
                }
                break;

            case 'topics':
            case 'themes':
            case 'membership':
                $tax = array('topics' => 'rtm_research_area', 'themes' => 'rtmn_theme', 'membership' => 'rtmn_membership')[$field];
                foreach (self::term_names($id, $tax) as $name) {
                    $html .= '<span class="rtmn-tag">' . esc_html($name) . '</span>';
                }
                if (!$html && $field === 'topics') {
                    $html = '<span class="rtmn-muted">Research topics not specified</span>';
                }
                break;

            case 'biography':
                $bio  = get_post_meta($id, '_rtm_long_description', true) ?: get_post_meta($id, '_rtm_short_description', true);
                $html = $bio ? wpautop(wp_kses_post($bio)) : '';
                break;

            case 'contact':
                $current = get_post_meta($id, '_rtm_email', true);
                if (is_email($current)) {
                    $html = '<a class="rtmn-contact-link" href="mailto:' . esc_attr($current) . '">Email researcher</a>';
                }
                foreach (array_filter($r['email'] ?? array(), 'is_email') as $email) {
                    if (strtolower($email) !== strtolower((string) $current)) {
                        $html .= '<a class="rtmn-contact-link" href="mailto:' . esc_attr($email) . '">UNBC-listed email</a>';
                    }
                }
                break;

            case 'links':
                $html = self::links_html($id, $r);
                break;

            case 'labs':
                $terms = wp_get_object_terms($id, 'rtm_research_team');
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $url = get_term_link($term);
                        if (!is_wp_error($url)) {
                            $html .= '<a class="rtmn-tag" href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
                        }
                    }
                }
                break;

            case 'source':
                $snapshot = get_post_meta($id, '_rtmn_source_snapshot', true);
                if ($snapshot) {
                    $html .= '<p class="rtmn-muted">HRI snapshot: ' . esc_html(wp_date('F j, Y', strtotime($snapshot))) . '.</p>';
                }
                if (!empty($r['source_url'])) {
                    $checked = !empty($r['retrieved_at']) ? ' <span class="rtmn-muted">· Source checked ' . esc_html(wp_date('F j, Y', strtotime($r['retrieved_at']))) . '</span>' : '';
                    $html   .= '<div class="rtmn-field--unbc-source"><p><a class="rtmn-contact-link" href="' . esc_url($r['source_url']) . '" target="_blank" rel="noopener noreferrer">UNBC profile ↗</a>' . $checked . '</p></div>';
                }
                break;
        }
        return $html;
    }

    private static function links_html($id, $record) {
        $links = array(
            'Official profile'     => get_post_meta($id, '_rtm_external_url', true),
            'HRI-supplied profile' => get_post_meta($id, '_rtmn_source_profile', true),
            'Website'              => get_post_meta($id, '_rtm_website', true),
            'Google Scholar'       => get_post_meta($id, '_rtm_google_scholar_url', true),
            'ORCID'                => get_post_meta($id, '_rtm_orcid', true),
        );
        foreach ($record['professional_links'] ?? array() as $link) {
            $url          = $link['url'] ?? '';
            $host         = wp_parse_url($url, PHP_URL_HOST);
            $source_field = $link['field'] ?? '';
            if (in_array($host, array('scholar.google.com', 'scholar.google.ca'), true) && strpos($url, 'citations?') !== false) {
                $links['Google Scholar'] = $links['Google Scholar'] ?: $url;
            } elseif ($host === 'orcid.org') {
                $links['ORCID'] = $links['ORCID'] ?: $url;
            } elseif ($source_field === 'field--name-field-website') {
                $links['Website'] = $links['Website'] ?: $url;
            } elseif ($source_field === 'field--name-field-curriculum-vitae') {
                $links['Curriculum vitae'] = $url;
            }
        }
        $html = '';
        $seen = array();
        foreach ($links as $label => $url) {
            if ($url && !in_array($url, $seen, true) && preg_match('#^https?://#i', $url)) {
                $seen[] = $url;
                $html  .= '<a class="rtmn-contact-link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . ' ↗</a>';
            }
        }
        return $html;
    }

    /* ------------------------------------------------------------------
     * Directory page behaviour
     * ---------------------------------------------------------------- */

    /**
     * Gutenberg keeps both directory Group blocks editable; the public page
     * shows just the requested view without running the inactive Query Loop.
     */
    public static function hide_inactive_view($pre, $block) {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || $block['blockName'] !== 'core/group' || !is_page(self::directory_page())) {
            return $pre;
        }
        $classes = explode(' ', $block['attrs']['className'] ?? '');
        foreach (array('researchers', 'labs') as $view) {
            if (in_array('rtmn-view-' . $view, $classes, true) && self::directory_view() !== $view) {
                return '';
            }
        }
        return $pre;
    }

    /** Profile About / Research sections disappear when they would be empty. */
    public static function hide_empty_profile_sections($pre, $parsed) {
        if (is_admin() || $parsed['blockName'] !== 'core/group' || !is_singular('rtm_team_member')) {
            return $pre;
        }
        $class = $parsed['attrs']['className'] ?? '';
        $id    = get_queried_object_id();
        if (strpos($class, 'rtmn-section-about') !== false && !get_post_meta($id, '_rtm_long_description', true) && !get_post_meta($id, '_rtm_short_description', true) && !trim(get_post_field('post_content', $id))) {
            return '';
        }
        if (strpos($class, 'rtmn-section-research') !== false && !self::term_names($id, 'rtm_research_area') && !self::term_names($id, 'rtmn_theme') && empty(self::source_record($id)['expertise_terms'])) {
            return '';
        }
        return $pre;
    }

    /** Keep a "Labs" page URL as an entrance to the shared directory. */
    public static function redirect_labs_page() {
        if (!is_page('labs') || is_preview() || !get_page_by_path(self::directory_page())) {
            return;
        }
        wp_safe_redirect(add_query_arg('directory', 'labs', self::directory_url()), 302);
        exit;
    }

    /* ------------------------------------------------------------------
     * Admin meta boxes
     * ---------------------------------------------------------------- */

    public static function add_meta_boxes() {
        add_meta_box('rtmn-affiliations', 'Research network affiliations', array(__CLASS__, 'affiliation_box'), 'rtm_team_member', 'normal');
        add_meta_box('rtmn-unbc-source', 'UNBC source and review notes', array(__CLASS__, 'source_box'), 'rtm_team_member', 'normal');
    }

    public static function affiliation_box($post) {
        wp_nonce_field('rtmn_save', 'rtmn_nonce');
        $rows = self::affiliations($post->ID);
        echo '<p>Institutional positions only. Lab assignments remain in Research Team.</p>';
        for ($i = 0; $i < max(3, count($rows) + 1); $i++) {
            printf(
                '<p><label>Position %d <input type="text" name="rtmn_affiliations[%d][title]" value="%s" /></label> <label>Institution <input type="text" name="rtmn_affiliations[%d][affiliation]" value="%s" /></label></p>',
                $i + 1, $i, esc_attr($rows[$i]['title'] ?? ''), $i, esc_attr($rows[$i]['affiliation'] ?? '')
            );
        }
        echo '<p>HRI source ID: ' . esc_html(get_post_meta($post->ID, '_rtmn_source_id', true) ?: 'Not imported') . '</p>';
    }

    public static function source_box($post) {
        $r = self::source_record($post->ID);
        if (!$r) {
            echo '<p>No UNBC source linked.</p>';
            return;
        }
        if (!empty($r['source_url'])) {
            echo '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url($r['source_url']) . '">Official UNBC profile</a></p>';
        }
        foreach (array('positions' => 'Appointments', 'departments' => 'Departments', 'faculties' => 'Faculties', 'email' => 'Source email', 'expertise_terms' => 'Expertise', 'supervision_status' => 'Supervision') as $key => $label) {
            if (!empty($r[$key])) {
                echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html(implode('; ', (array) $r[$key])) . '</p>';
            }
        }
        foreach ((array) get_post_meta($post->ID, '_rtmn_unbc_review_flags', true) as $flag) {
            if ($flag !== '') {
                echo '<p>' . esc_html($flag) . '</p>';
            }
        }
        echo '<p>Source values are retained separately. Edit the public position, biography and taxonomy panels normally; repeat imports preserve later manual edits.</p>';
    }

    public static function save_affiliations($id) {
        if (!isset($_POST['rtmn_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rtmn_nonce'])), 'rtmn_save') || !current_user_can('edit_post', $id) || wp_is_post_autosave($id) || wp_is_post_revision($id)) {
            return;
        }
        update_post_meta($id, '_rtmn_affiliations', self::clean_affiliations(wp_unslash($_POST['rtmn_affiliations'] ?? array())));
        wp_set_object_terms($id, self::institution_names(self::affiliations($id)), 'rtmn_institution', false);
    }
}
