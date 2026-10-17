<?php
/**
 * Research Team Manager Blocks Handler
 * Registers and manages Gutenberg blocks for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Blocks {
    
    public function __construct() {
        add_action('init', array($this, 'register_blocks'), 20); // Later priority
        add_action('init', array($this, 'register_block_category'), 5); // Early priority for category
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Register custom block category
     */
    public function register_block_category() {
        // Register for all WordPress versions
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
        
        // Also try the older filter for compatibility
        add_filter('block_categories', array($this, 'add_block_category'), 10, 2);
    }
    
    /**
     * Add block category
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug'  => 'rtm-blocks',
                    'title' => __('Research Team', 'research-team-manager'),
                    'icon'  => 'groups',
                ),
            )
        );
    }
    
    /**
     * Register all blocks
     */
    public function register_blocks() {
        // Check if we have block registration functions
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register Team Member Field Block
        $this->register_team_member_field_block();

        // Team Section block (header / roster / publications for a chosen team)
        wp_register_script(
            'rtm-team-section-block',
            plugin_dir_url(dirname(__FILE__)) . 'assets/team-section-block.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'),
            RTM_VERSION,
            true
        );
        register_block_type('rtm/team-section', array(
            'api_version'     => 3,
            'title'           => __('Team Section', 'research-team-manager'),
            'category'        => 'rtm-blocks',
            'editor_script'   => 'rtm-team-section-block',
            'attributes'      => array(
                'team'    => array('type' => 'number', 'default' => 0),
                'section' => array('type' => 'string', 'default' => 'roster'),
                'heading' => array('type' => 'string', 'default' => ''),
            ),
            'supports'        => array('html' => false, 'align' => array('wide', 'full')),
            'render_callback' => array($this, 'render_team_section_block'),
        ));
    }

    /**
     * Render a Team Section with the matching shortcode. Team 0 means the
     * team of the page being viewed.
     */
    public function render_team_section_block($attributes) {
        $team    = !empty($attributes['team']) ? (int) $attributes['team'] : '';
        $heading = isset($attributes['heading']) ? trim((string) $attributes['heading']) : '';
        switch ($attributes['section'] ?? 'roster') {
            case 'header':
                $html = rtm_team_header_shortcode(array('team' => $team));
                break;
            case 'publications':
                $html = rtm_team_publications_shortcode(array('team' => $team, 'heading' => $heading !== '' ? $heading : 'Publications'));
                break;
            default:
                $html = rtm_team_roster_shortcode(array('team' => $team, 'heading' => $heading !== '' ? $heading : 'Lab Team'));
        }
        return $html !== '' ? '<div ' . get_block_wrapper_attributes(array('class' => 'rtm-team-section')) . '>' . $html . '</div>' : '';
    }

    /**
     * Teams offered by the editor's team pickers.
     */
    public static function editor_data() {
        $teams = array();
        if (rtm_is_multiple_teams()) {
            $terms = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'orderby' => 'name'));
            foreach (is_wp_error($terms) ? array() : $terms as $term) {
                $teams[] = array('id' => (int) $term->term_id, 'name' => $term->name, 'lead' => rtm_team_lead_text($term->term_id));
            }
        }
        return array(
            'multiple'         => rtm_is_multiple_teams(),
            'teams'            => $teams,
            // WordPress 7.0 changed the Query Loop's taxQuery to {include, exclude}.
            'taxIncludeFormat' => version_compare(get_bloginfo('version'), '7.0-alpha', '>='),
        );
    }
    
    /**
     * Register the Team Member Field Block
     */
    private function register_team_member_field_block() {
        $block_path = plugin_dir_path(dirname(__FILE__)) . 'blocks/team-member-field';
        
        // Check if block.json exists
        if (file_exists($block_path . '/block.json')) {
            register_block_type($block_path, array(
                'render_callback' => array($this, 'render_team_member_field_block')
            ));
        } else {
            // Fallback manual registration if block.json is missing
            register_block_type('rtm/team-member-field', array(
                'title' => __('Team Member Field', 'research-team-manager'),
                'description' => __('Display team member custom fields with customizable text.', 'research-team-manager'),
                'category' => 'rtm-blocks',
                'icon' => 'groups',
                'supports' => array(
                    'html' => false,
                    'align' => true,
                    'color' => array(
                        'gradients' => true,
                        'link' => true,
                        '__experimentalDefaultControls' => array(
                            'background' => true,
                            'text' => true
                        )
                    ),
                    'spacing' => array(
                        'margin' => true,
                        'padding' => true,
                        '__experimentalDefaultControls' => array(
                            'margin' => false,
                            'padding' => false
                        )
                    ),
                    'typography' => array(
                        'fontSize' => true,
                        'lineHeight' => true,
                        '__experimentalFontFamily' => true,
                        '__experimentalFontWeight' => true,
                        '__experimentalFontStyle' => true,
                        '__experimentalTextTransform' => true,
                        '__experimentalTextDecoration' => true,
                        '__experimentalLetterSpacing' => true,
                        '__experimentalDefaultControls' => array(
                            'fontSize' => true
                        )
                    )
                ),
                'attributes' => array(
                    'content' => array(
                        'type' => 'string',
                        'source' => 'html',
                        'selector' => '.team-member-field-content',
                        'default' => ''
                    ),
                    'fieldName' => array(
                        'type' => 'string',
                        'default' => 'rtm_position'
                    ),
                    'showLabel' => array(
                        'type' => 'boolean',
                        'default' => true
                    ),
                    'customLabel' => array(
                        'type' => 'string',
                        'default' => ''
                    ),
                    'fallbackText' => array(
                        'type' => 'string',
                        'default' => ''
                    ),
                    'makeLink' => array(
                        'type' => 'boolean',
                        'default' => false
                    ),
                    'linkText' => array(
                        'type' => 'string',
                        'default' => ''
                    )
                ),
                'render_callback' => array($this, 'render_team_member_field_block')
            ));
        }
    }
    
    /**
     * Render callback for Team Member Field Block
     */
    public function render_team_member_field_block($attributes, $content, $block) {
        // Include the render file
        $render_file = plugin_dir_path(dirname(__FILE__)) . 'blocks/team-member-field/render.php';
        if (file_exists($render_file)) {
            ob_start();
            include $render_file;
            return ob_get_clean();
        }
        
        return '';
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'rtm-block-editor-extensions',
            plugin_dir_url(dirname(__FILE__)) . 'assets/block-editor-extensions.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks'),
            RTM_VERSION,
            true
        );
        $data = 'window.rtmBlockData = ' . wp_json_encode(self::editor_data()) . ';';
        wp_add_inline_script('rtm-block-editor-extensions', $data, 'before');
        wp_add_inline_script('rtm-team-section-block', $data, 'before');
    }
}