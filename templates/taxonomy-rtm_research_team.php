<?php
/**
 * Lab / Team archive template (classic themes).
 *
 * Renders one research team page: the lab header (logo, PI, intro from term
 * meta), the team's members, and that team's publications. On block themes this
 * file is not used — build the page in the Site Editor with the [rtm_team_header],
 * a Query Loop (filtered to the team), and [sorted_publications] instead.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$rtm_team    = get_queried_object();
$rtm_team_id = ($rtm_team instanceof WP_Term) ? (int) $rtm_team->term_id : 0;
?>

<div class="rtm-team-archive rtm-team-page">

    <?php echo rtm_get_team_header_html($rtm_team_id); ?>

    <section class="rtm-team-members-section">
        <h2 class="rtm-section-title"><?php esc_html_e('Team Members', 'research-team-manager'); ?></h2>

        <?php if (have_posts()): ?>
            <div class="rtm-team-members rtm-team-grid rtm-grid-3">
                <?php while (have_posts()): the_post(); ?>
                    <div class="rtm-team-member">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="rtm-member-photo">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium', array('class' => 'rtm-member-image')); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="rtm-member-content">
                            <h3 class="rtm-member-name">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>

                            <?php
                            $rtm_position = get_post_meta(get_the_ID(), '_rtm_position', true);
                            if ($rtm_position):
                            ?>
                                <div class="rtm-member-position"><?php echo esc_html($rtm_position); ?></div>
                            <?php endif; ?>

                            <?php
                            $rtm_status = get_the_terms(get_the_ID(), 'rtm_member_status');
                            if ($rtm_status && !is_wp_error($rtm_status)):
                            ?>
                                <div class="rtm-member-status">
                                    <?php foreach ($rtm_status as $rtm_term): ?>
                                        <span class="rtm-status-tag"><?php echo esc_html($rtm_term->name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $rtm_short = get_post_meta(get_the_ID(), '_rtm_short_description', true);
                            if ($rtm_short): ?>
                                <div class="rtm-member-excerpt"><?php echo wp_kses_post($rtm_short); ?></div>
                            <?php elseif (get_the_excerpt()): ?>
                                <div class="rtm-member-excerpt"><?php the_excerpt(); ?></div>
                            <?php endif; ?>

                            <a href="<?php the_permalink(); ?>" class="rtm-member-link">
                                <?php esc_html_e('View Profile', 'research-team-manager'); ?>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <?php
            the_posts_pagination(array(
                'prev_text' => __('Previous', 'research-team-manager'),
                'next_text' => __('Next', 'research-team-manager'),
            ));
            ?>
        <?php else: ?>
            <p class="rtm-no-team-members"><?php esc_html_e('No team members found for this team yet.', 'research-team-manager'); ?></p>
        <?php endif; ?>
    </section>

    <?php
    // This team's publications (the shortcode auto-detects the current team archive).
    $rtm_pubs = do_shortcode('[sorted_publications]');
    if ($rtm_pubs && strpos($rtm_pubs, 'No publications available') === false):
    ?>
        <section class="rtm-team-publications-section">
            <h2 class="rtm-section-title"><?php esc_html_e('Publications', 'research-team-manager'); ?></h2>
            <?php echo $rtm_pubs; ?>
        </section>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
