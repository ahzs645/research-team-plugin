<?php
/**
 * Archive Team Members Template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header(); ?>

<div class="rtm-team-archive">
    <header class="rtm-archive-header">
        <h1 class="rtm-archive-title"><?php _e('Research Team', 'research-team-manager'); ?></h1>
        <?php if (term_description()): ?>
            <div class="rtm-archive-description">
                <?php echo term_description(); ?>
            </div>
        <?php endif; ?>
    </header>
    
    <?php if (have_posts()): ?>
        <div class="rtm-team-filters">
            <?php
            $roles = get_terms(array(
                'taxonomy' => 'rtm_team_role',
                'hide_empty' => true,
            ));
            
            $research_areas = get_terms(array(
                'taxonomy' => 'rtm_research_area',
                'hide_empty' => true,
            ));
            ?>
            
            <?php if (!empty($roles) || !empty($research_areas)): ?>
                <div class="rtm-filter-controls">
                    <button class="rtm-filter-toggle"><?php _e('Filter Team Members', 'research-team-manager'); ?></button>
                    
                    <div class="rtm-filters-panel">
                        <?php if (!empty($roles)): ?>
                            <div class="rtm-filter-group">
                                <h4><?php _e('Filter by Role', 'research-team-manager'); ?></h4>
                                <div class="rtm-filter-options">
                                    <label>
                                        <input type="radio" name="role_filter" value="" checked>
                                        <?php _e('All Roles', 'research-team-manager'); ?>
                                    </label>
                                    <?php foreach ($roles as $role): ?>
                                        <label>
                                            <input type="radio" name="role_filter" value="<?php echo esc_attr($role->slug); ?>">
                                            <?php echo esc_html($role->name); ?> (<?php echo $role->count; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($research_areas)): ?>
                            <div class="rtm-filter-group">
                                <h4><?php _e('Filter by Research Area', 'research-team-manager'); ?></h4>
                                <div class="rtm-filter-options">
                                    <label>
                                        <input type="radio" name="area_filter" value="" checked>
                                        <?php _e('All Areas', 'research-team-manager'); ?>
                                    </label>
                                    <?php foreach ($research_areas as $area): ?>
                                        <label>
                                            <input type="radio" name="area_filter" value="<?php echo esc_attr($area->slug); ?>">
                                            <?php echo esc_html($area->name); ?> (<?php echo $area->count; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="rtm-filter-actions">
                            <button type="button" class="rtm-apply-filters"><?php _e('Apply Filters', 'research-team-manager'); ?></button>
                            <button type="button" class="rtm-clear-filters"><?php _e('Clear All', 'research-team-manager'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="rtm-team-members rtm-team-grid rtm-grid-3" id="rtm-team-container">
            <?php while (have_posts()): the_post(); ?>
                <?php
                $post_roles = wp_get_post_terms(get_the_ID(), 'rtm_team_role', array('fields' => 'slugs'));
                $post_areas = wp_get_post_terms(get_the_ID(), 'rtm_research_area', array('fields' => 'slugs'));
                
                $data_roles = implode(' ', $post_roles);
                $data_areas = implode(' ', $post_areas);
                ?>
                
                <div class="rtm-team-member" 
                     data-roles="<?php echo esc_attr($data_roles); ?>" 
                     data-areas="<?php echo esc_attr($data_areas); ?>">
                    
                    <?php if (has_post_thumbnail()): ?>
                        <div class="rtm-member-photo">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium', array('class' => 'rtm-member-image')); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="rtm-member-content">
                        <div class="rtm-member-header">
                            <h3 class="rtm-member-name">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            
                            <?php
                            $position = get_post_meta(get_the_ID(), '_rtm_position', true);
                            $department = get_post_meta(get_the_ID(), '_rtm_department', true);
                            ?>
                            
                            <?php if ($position): ?>
                                <div class="rtm-member-position"><?php echo esc_html($position); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($department): ?>
                                <div class="rtm-member-department"><?php echo esc_html($department); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (get_the_excerpt()): ?>
                            <div class="rtm-member-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="rtm-member-taxonomies">
                            <?php
                            $roles = get_the_terms(get_the_ID(), 'rtm_team_role');
                            $areas = get_the_terms(get_the_ID(), 'rtm_research_area');
                            ?>
                            
                            <?php if ($roles && !is_wp_error($roles)): ?>
                                <div class="rtm-member-roles">
                                    <?php foreach ($roles as $role): ?>
                                        <span class="rtm-role-tag"><?php echo esc_html($role->name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($areas && !is_wp_error($areas)): ?>
                                <div class="rtm-member-areas">
                                    <?php foreach ($areas as $area): ?>
                                        <span class="rtm-area-tag"><?php echo esc_html($area->name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rtm-member-actions">
                            <a href="<?php the_permalink(); ?>" class="rtm-member-link">
                                <?php _e('View Profile', 'research-team-manager'); ?>
                            </a>
                            
                            <?php
                            $email = get_post_meta(get_the_ID(), '_rtm_email', true);
                            if ($email):
                            ?>
                                <a href="mailto:<?php echo esc_attr($email); ?>" class="rtm-contact-link">
                                    <?php _e('Contact', 'research-team-manager'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="rtm-no-results" style="display: none;">
            <p><?php _e('No team members found matching your criteria.', 'research-team-manager'); ?></p>
        </div>
        
        <?php
        the_posts_pagination(array(
            'prev_text' => __('Previous', 'research-team-manager'),
            'next_text' => __('Next', 'research-team-manager'),
        ));
        ?>
        
    <?php else: ?>
        <div class="rtm-no-team-members">
            <p><?php _e('No team members found.', 'research-team-manager'); ?></p>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Filter toggle
    $('.rtm-filter-toggle').on('click', function() {
        $('.rtm-filters-panel').slideToggle();
    });
    
    // Filter functionality
    $('.rtm-apply-filters').on('click', function() {
        var selectedRole = $('input[name="role_filter"]:checked').val();
        var selectedArea = $('input[name="area_filter"]:checked').val();
        
        $('.rtm-team-member').each(function() {
            var $member = $(this);
            var memberRoles = $member.data('roles').split(' ');
            var memberAreas = $member.data('areas').split(' ');
            
            var showMember = true;
            
            if (selectedRole && memberRoles.indexOf(selectedRole) === -1) {
                showMember = false;
            }
            
            if (selectedArea && memberAreas.indexOf(selectedArea) === -1) {
                showMember = false;
            }
            
            if (showMember) {
                $member.show();
            } else {
                $member.hide();
            }
        });
        
        // Check if any members are visible
        var visibleMembers = $('.rtm-team-member:visible').length;
        if (visibleMembers === 0) {
            $('.rtm-no-results').show();
        } else {
            $('.rtm-no-results').hide();
        }
        
        $('.rtm-filters-panel').slideUp();
    });
    
    // Clear filters
    $('.rtm-clear-filters').on('click', function() {
        $('input[name="role_filter"]').first().prop('checked', true);
        $('input[name="area_filter"]').first().prop('checked', true);
        $('.rtm-team-member').show();
        $('.rtm-no-results').hide();
        $('.rtm-filters-panel').slideUp();
    });
});
</script>

<?php get_footer(); ?>