<?php
/**
 * Single Team Member Template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="rtm-single-member">
    <?php while (have_posts()): the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('rtm-team-member-single'); ?>>
            <div class="rtm-member-header">
                <?php if (has_post_thumbnail()): ?>
                    <div class="rtm-member-photo-large">
                        <?php the_post_thumbnail('large', array('class' => 'rtm-member-image-large')); ?>
                    </div>
                <?php endif; ?>
                
                <div class="rtm-member-info">
                    <h1 class="rtm-member-name"><?php the_title(); ?></h1>
                    
                    <?php
                    $position = get_post_meta(get_the_ID(), '_rtm_position', true);
                    $department = get_post_meta(get_the_ID(), '_rtm_department', true);
                    $education = get_post_meta(get_the_ID(), '_rtm_education', true);
                    ?>
                    
                    <?php if ($position): ?>
                        <div class="rtm-member-position"><?php echo esc_html($position); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($department): ?>
                        <div class="rtm-member-department"><?php echo esc_html($department); ?></div>
                    <?php endif; ?>
                    
                    <?php
                    $roles = get_the_terms(get_the_ID(), 'rtm_team_role');
                    $areas = get_the_terms(get_the_ID(), 'rtm_research_area');
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
            
            <div class="rtm-member-content">
                <?php if (get_the_content()): ?>
                    <div class="rtm-member-description">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
                
                <?php
                $biography = get_post_meta(get_the_ID(), '_rtm_biography', true);
                $research_interests = get_post_meta(get_the_ID(), '_rtm_research_interests', true);
                ?>
                
                <?php if ($research_interests): ?>
                    <div class="rtm-member-research">
                        <h3><?php _e('Research Interests', 'research-team-manager'); ?></h3>
                        <p><?php echo esc_html($research_interests); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($biography): ?>
                    <div class="rtm-member-biography">
                        <h3><?php _e('Biography', 'research-team-manager'); ?></h3>
                        <?php echo wp_kses_post($biography); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($education): ?>
                    <div class="rtm-member-education">
                        <h3><?php _e('Education', 'research-team-manager'); ?></h3>
                        <p><?php echo nl2br(esc_html($education)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="rtm-member-contact-section">
                <?php
                $email = get_post_meta(get_the_ID(), '_rtm_email', true);
                $phone = get_post_meta(get_the_ID(), '_rtm_phone', true);
                $office = get_post_meta(get_the_ID(), '_rtm_office', true);
                $website = get_post_meta(get_the_ID(), '_rtm_website', true);
                
                $social_links = array(
                    'linkedin' => get_post_meta(get_the_ID(), '_rtm_linkedin', true),
                    'twitter' => get_post_meta(get_the_ID(), '_rtm_twitter', true),
                    'google_scholar' => get_post_meta(get_the_ID(), '_rtm_google_scholar', true),
                    'orcid' => get_post_meta(get_the_ID(), '_rtm_orcid', true),
                    'researchgate' => get_post_meta(get_the_ID(), '_rtm_researchgate', true),
                    'github' => get_post_meta(get_the_ID(), '_rtm_github', true),
                );
                ?>
                
                <?php if ($email || $phone || $office || $website || array_filter($social_links)): ?>
                    <div class="rtm-member-contact-info">
                        <h3><?php _e('Contact Information', 'research-team-manager'); ?></h3>
                        
                        <div class="rtm-contact-details">
                            <?php if ($email): ?>
                                <div class="rtm-contact-item rtm-email">
                                    <span class="rtm-contact-icon">📧</span>
                                    <span class="rtm-contact-label"><?php _e('Email:', 'research-team-manager'); ?></span>
                                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($phone): ?>
                                <div class="rtm-contact-item rtm-phone">
                                    <span class="rtm-contact-icon">📞</span>
                                    <span class="rtm-contact-label"><?php _e('Phone:', 'research-team-manager'); ?></span>
                                    <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($office): ?>
                                <div class="rtm-contact-item rtm-office">
                                    <span class="rtm-contact-icon">🏢</span>
                                    <span class="rtm-contact-label"><?php _e('Office:', 'research-team-manager'); ?></span>
                                    <?php echo esc_html($office); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($website): ?>
                                <div class="rtm-contact-item rtm-website">
                                    <span class="rtm-contact-icon">🌐</span>
                                    <span class="rtm-contact-label"><?php _e('Website:', 'research-team-manager'); ?></span>
                                    <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (array_filter($social_links)): ?>
                            <div class="rtm-social-links">
                                <h4><?php _e('Professional Links', 'research-team-manager'); ?></h4>
                                <div class="rtm-social-grid">
                                    <?php foreach ($social_links as $platform => $url): ?>
                                        <?php if (!empty($url)): ?>
                                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="rtm-social-link rtm-<?php echo esc_attr($platform); ?>">
                                                <span class="rtm-social-icon"><?php echo get_social_icon($platform); ?></span>
                                                <span class="rtm-social-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $platform))); ?></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="rtm-member-navigation">
                <?php
                $prev_post = get_previous_post(true, '', 'rtm_team_role');
                $next_post = get_next_post(true, '', 'rtm_team_role');
                ?>
                
                <?php if ($prev_post || $next_post): ?>
                    <nav class="rtm-post-navigation">
                        <?php if ($prev_post): ?>
                            <div class="rtm-nav-previous">
                                <a href="<?php echo get_permalink($prev_post->ID); ?>">
                                    <span class="rtm-nav-subtitle"><?php _e('Previous Member', 'research-team-manager'); ?></span>
                                    <span class="rtm-nav-title"><?php echo get_the_title($prev_post->ID); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($next_post): ?>
                            <div class="rtm-nav-next">
                                <a href="<?php echo get_permalink($next_post->ID); ?>">
                                    <span class="rtm-nav-subtitle"><?php _e('Next Member', 'research-team-manager'); ?></span>
                                    <span class="rtm-nav-title"><?php echo get_the_title($next_post->ID); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
                
                <div class="rtm-back-to-team">
                    <a href="<?php echo get_post_type_archive_link('rtm_team_member'); ?>" class="rtm-back-link">
                        <?php _e('← Back to Team', 'research-team-manager'); ?>
                    </a>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<?php
if (!function_exists('get_social_icon')) {
    function get_social_icon($platform) {
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
}
?>

<?php get_footer(); ?>