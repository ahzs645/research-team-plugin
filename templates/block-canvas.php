<?php
/**
 * Block-theme canvas for plugin-owned pages (lab archive + member profile).
 *
 * Rendered via template_include when the active block theme has no matching
 * block template. Pulls in the theme's header/footer parts and global styles
 * (wp_head/wp_footer) and renders the plugin's shortcodes — so the page works
 * without any theme files, while a real theme/Site Editor template still wins.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">

    <?php block_template_part('header'); ?>

    <main id="wp--skip-link--target" class="wp-block-group rtm-canvas-main" style="padding-top:8rem;padding-bottom:4rem;padding-left:1.5rem;padding-right:1.5rem;">
        <div class="rtm-canvas-inner">
            <?php
            if (is_singular('rtm_team_member')) {
                while (have_posts()) {
                    the_post();
                    echo do_shortcode('[rtm_member_profile]');
                }
            } elseif (is_tax('rtm_research_team')) {
                echo do_shortcode('[rtm_team_header]');
                echo do_shortcode('[rtm_team_roster]');
                echo do_shortcode('[rtm_team_publications]');
            }
            ?>
        </div>
    </main>

    <?php block_template_part('footer'); ?>

</div>
<?php wp_footer(); ?>
</body>
</html>
