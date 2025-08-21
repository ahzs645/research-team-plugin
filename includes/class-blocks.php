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
        error_log('RTM: Blocks class initialized');
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
    }
    
    /**
     * Register the Team Member Field Block
     */
    private function register_team_member_field_block() {
        $block_path = plugin_dir_path(dirname(__FILE__)) . 'blocks/team-member-field';
        
        // Check if block.json exists
        if (file_exists($block_path . '/block.json')) {
            $result = register_block_type($block_path, array(
                'render_callback' => array($this, 'render_team_member_field_block')
            ));
            if ($result) {
                error_log('RTM: Team Member Field block registered successfully');
            } else {
                error_log('RTM: Failed to register Team Member Field block');
            }
        } else {
            error_log('RTM: block.json not found at ' . $block_path);
            
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
}