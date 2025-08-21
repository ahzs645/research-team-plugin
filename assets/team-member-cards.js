/**
 * Team Member Cards JavaScript
 * Handles "View Profile" button clicks to navigate to team member pages
 */

jQuery(document).ready(function($) {
    // Handle "View Profile" button clicks
    $('.rtm-team-member-cards .wp-block-button .wp-element-button').on('click', function(e) {
        e.preventDefault();
        
        // Find the closest team member post container
        const postContainer = $(this).closest('.wp-block-post');
        
        // Try to get the link from the post title
        const titleLink = postContainer.find('.wp-block-post-title a');
        
        if (titleLink.length && titleLink.attr('href')) {
            // Navigate to the team member's profile page
            window.location.href = titleLink.attr('href');
        } else {
            // Fallback: try to construct URL from post data
            const postId = postContainer.attr('class').match(/post-(\d+)/);
            if (postId && postId[1]) {
                // You might need to adjust this URL structure based on your permalink settings
                window.location.href = window.location.origin + '/team-member/' + postId[1] + '/';
            }
        }
    });
    
    // Add keyboard navigation support
    $('.rtm-team-member-cards .wp-block-button .wp-element-button').on('keypress', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            $(this).click();
        }
    });
    
    // Add hover effects for better UX
    $('.rtm-team-member-cards .wp-block-post').hover(
        function() {
            $(this).addClass('rtm-card-hover');
        },
        function() {
            $(this).removeClass('rtm-card-hover');
        }
    );
});