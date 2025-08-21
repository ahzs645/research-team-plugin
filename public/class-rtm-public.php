<?php
/**
 * Public-facing functionality for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Public {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('rtm_team_members', array($this, 'team_members_shortcode'));
        add_filter('single_template', array($this, 'team_member_single_template'));
        add_filter('archive_template', array($this, 'team_member_archive_template'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'rtm-public-style',
            RTM_PLUGIN_URL . 'assets/css/public.css',
            array(),
            RTM_VERSION
        );
        
        wp_enqueue_script(
            'rtm-public-script',
            RTM_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            RTM_VERSION,
            true
        );
    }
    
    public function team_members_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => -1,
            'role' => '',
            'research_area' => '',
            'show_current_only' => 'true',
            'order_by' => 'menu_order',
            'order' => 'ASC',
            'layout' => 'grid',
            'columns' => '3',
            'show_bio' => 'false',
            'show_contact' => 'true',
            'show_social' => 'true',
        ), $atts);
        
        $args = array(
            'post_type' => 'team_member',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'meta_key' => '_rtm_order',
            'orderby' => 'meta_value_num',
            'order' => $atts['order'],
        );
        
        if ($atts['show_current_only'] === 'true') {
            $args['meta_query'] = array(
                array(
                    'key' => '_rtm_is_current',
                    'value' => '1',
                    'compare' => '=',
                )
            );
        }
        
        $tax_query = array();
        
        if (!empty($atts['role'])) {
            $tax_query[] = array(
                'taxonomy' => 'team_role',
                'field' => 'slug',
                'terms' => explode(',', $atts['role']),
            );
        }
        
        if (!empty($atts['research_area'])) {
            $tax_query[] = array(
                'taxonomy' => 'research_area',
                'field' => 'slug',
                'terms' => explode(',', $atts['research_area']),
            );
        }
        
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
            if (count($tax_query) > 1) {
                $args['tax_query']['relation'] = 'AND';
            }
        }
        
        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            return '<p>' . __('No team members found.', 'research-team-manager') . '</p>';
        }
        
        ob_start();
        
        $layout_class = 'rtm-team-' . esc_attr($atts['layout']);
        if ($atts['layout'] === 'grid') {
            $layout_class .= ' rtm-grid-' . esc_attr($atts['columns']);
        }
        
        ?>
        <div class="rtm-team-members <?php echo esc_attr($layout_class); ?>">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <?php $this->render_team_member(get_the_ID(), $atts); ?>
            <?php endwhile; ?>
        </div>
        <?php
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    private function render_team_member($post_id, $atts) {
        $position = get_post_meta($post_id, '_rtm_position', true);
        $department = get_post_meta($post_id, '_rtm_department', true);
        $email = get_post_meta($post_id, '_rtm_email', true);
        $phone = get_post_meta($post_id, '_rtm_phone', true);
        $office = get_post_meta($post_id, '_rtm_office', true);
        $website = get_post_meta($post_id, '_rtm_website', true);
        $biography = get_post_meta($post_id, '_rtm_biography', true);
        $research_interests = get_post_meta($post_id, '_rtm_research_interests', true);
        
        $social_links = array(
            'linkedin' => get_post_meta($post_id, '_rtm_linkedin', true),
            'twitter' => get_post_meta($post_id, '_rtm_twitter', true),
            'google_scholar' => get_post_meta($post_id, '_rtm_google_scholar', true),
            'orcid' => get_post_meta($post_id, '_rtm_orcid', true),
            'researchgate' => get_post_meta($post_id, '_rtm_researchgate', true),
            'github' => get_post_meta($post_id, '_rtm_github', true),
        );
        
        ?>
        <div class="rtm-team-member" id="team-member-<?php echo esc_attr($post_id); ?>">
            <?php if (has_post_thumbnail($post_id)): ?>
                <div class="rtm-member-photo">
                    <a href="<?php echo get_permalink($post_id); ?>">
                        <?php echo get_the_post_thumbnail($post_id, 'medium', array('class' => 'rtm-member-image')); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="rtm-member-content">
                <div class="rtm-member-header">
                    <h3 class="rtm-member-name">
                        <a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a>
                    </h3>
                    <?php if ($position): ?>
                        <div class="rtm-member-position"><?php echo esc_html($position); ?></div>
                    <?php endif; ?>
                    <?php if ($department): ?>
                        <div class="rtm-member-department"><?php echo esc_html($department); ?></div>
                    <?php endif; ?>
                </div>
                
                <?php if ($research_interests && $atts['show_bio'] === 'true'): ?>
                    <div class="rtm-member-research">
                        <strong><?php _e('Research Interests:', 'research-team-manager'); ?></strong>
                        <p><?php echo esc_html($research_interests); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($biography && $atts['show_bio'] === 'true'): ?>
                    <div class="rtm-member-bio">
                        <?php echo wp_kses_post($biography); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_contact'] === 'true'): ?>
                    <div class="rtm-member-contact">
                        <?php if ($email): ?>
                            <div class="rtm-contact-item rtm-email">
                                <span class="rtm-contact-icon">📧</span>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($phone): ?>
                            <div class="rtm-contact-item rtm-phone">
                                <span class="rtm-contact-icon">📞</span>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($office): ?>
                            <div class="rtm-contact-item rtm-office">
                                <span class="rtm-contact-icon">🏢</span>
                                <?php echo esc_html($office); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($website): ?>
                            <div class="rtm-contact-item rtm-website">
                                <span class="rtm-contact-icon">🌐</span>
                                <a href="<?php echo esc_url($website); ?>" target="_blank"><?php _e('Website', 'research-team-manager'); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_social'] === 'true'): ?>
                    <div class="rtm-member-social">
                        <?php foreach ($social_links as $platform => $url): ?>
                            <?php if (!empty($url)): ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" class="rtm-social-link rtm-<?php echo esc_attr($platform); ?>" title="<?php echo esc_attr(ucfirst($platform)); ?>">
                                    <?php echo $this->get_social_icon($platform); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="rtm-member-taxonomies">
                    <?php
                    $roles = get_the_terms($post_id, 'team_role');
                    $areas = get_the_terms($post_id, 'research_area');
                    ?>
                    
                    <?php if ($roles && !is_wp_error($roles)): ?>
                        <div class="rtm-member-roles">
                            <strong><?php _e('Role:', 'research-team-manager'); ?></strong>
                            <?php foreach ($roles as $role): ?>
                                <span class="rtm-role-tag"><?php echo esc_html($role->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($areas && !is_wp_error($areas)): ?>
                        <div class="rtm-member-areas">
                            <strong><?php _e('Research Areas:', 'research-team-manager'); ?></strong>
                            <?php foreach ($areas as $area): ?>
                                <span class="rtm-area-tag"><?php echo esc_html($area->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_social_icon($platform) {
        $icons = array(
            'linkedin' => '🔗',
            'twitter' => '🐦',
            'google_scholar' => '🎓',
            'orcid' => '🆔',
            'researchgate' => '🔬',
            'github' => '💻',
        );
        
        return isset($icons[$platform]) ? $icons[$platform] : '🌐';
    }
    
    public function team_member_single_template($single_template) {
        global $post;
        
        if ($post->post_type === 'team_member') {
            $plugin_template = RTM_PLUGIN_PATH . 'templates/single-team_member.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $single_template;
    }
    
    public function team_member_archive_template($archive_template) {
        if (is_post_type_archive('team_member')) {
            $plugin_template = RTM_PLUGIN_PATH . 'templates/archive-team_member.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $archive_template;
    }
}