<?php
/**
 * Server-side rendering for the Team Member Field block
 *
 * @param array $attributes Block attributes.
 * @param string $content Block content.
 * @param WP_Block $block Block instance.
 * @return string Returns the rendered block HTML.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get team member ID from current post context
$team_member_id = 0;

// Check if we're in a block context (Query Loop)
if (isset($block->context['postId'])) {
    $current_post_id = $block->context['postId'];
    $current_post = get_post($current_post_id);
    if ($current_post && $current_post->post_type === 'team_member') {
        $team_member_id = $current_post_id;
    }
} else {
    // Check if we're viewing a team member page directly
    if (is_singular('team_member')) {
        $team_member_id = get_queried_object_id();
    } else {
        // Fallback to global post
        global $post;
        if ($post && $post->post_type === 'team_member') {
            $team_member_id = $post->ID;
        }
    }
}

// Get the field value
$field_name = $attributes['fieldName'] ?? 'rtm_position';
$field_value = '';

if ($team_member_id) {
    // Handle special fields like taxonomies
    if ($field_name === 'research_areas') {
        $terms = get_the_terms($team_member_id, 'research_area');
        if ($terms && !is_wp_error($terms)) {
            $term_names = wp_list_pluck($terms, 'name');
            $field_value = implode(', ', $term_names);
        }
    } elseif ($field_name === 'team_roles') {
        $terms = get_the_terms($team_member_id, 'team_role');
        if ($terms && !is_wp_error($terms)) {
            $term_names = wp_list_pluck($terms, 'name');
            $field_value = implode(', ', $term_names);
        }
    } elseif ($field_name === 'member_status') {
        $terms = get_the_terms($team_member_id, 'member_status');
        if ($terms && !is_wp_error($terms)) {
            $term_names = wp_list_pluck($terms, 'name');
            $field_value = implode(', ', $term_names);
        }
    } else {
        // Regular meta fields - add underscore prefix
        $meta_key = '_' . $field_name;
        $field_value = get_post_meta($team_member_id, $meta_key, true);
    }
}

// Get attributes
$content = $attributes['content'] ?? '';
$show_label = $attributes['showLabel'] ?? true;
$custom_label = $attributes['customLabel'] ?? '';
$fallback_text = $attributes['fallbackText'] ?? '';
$make_link = $attributes['makeLink'] ?? false;
$link_text = $attributes['linkText'] ?? '';

// Field labels mapping
$field_labels = [
    'rtm_position' => __('Position', 'research-team-manager'),
    'rtm_short_description' => __('Short Description', 'research-team-manager'),
    'rtm_long_description' => __('Long Description', 'research-team-manager'),
    'rtm_email' => __('Email', 'research-team-manager'),
    'rtm_phonenumber' => __('Phone Number', 'research-team-manager'),
    'rtm_website' => __('Website', 'research-team-manager'),
    'rtm_linkedin_url' => __('LinkedIn', 'research-team-manager'),
    'rtm_google_scholar_url' => __('Google Scholar', 'research-team-manager'),
    'rtm_researchgate_url' => __('ResearchGate', 'research-team-manager'),
    'member_status' => __('Member Status', 'research-team-manager'),
    'research_areas' => __('Research Areas', 'research-team-manager'),
    'team_roles' => __('Team Role', 'research-team-manager')
];

// Get the label
$label = $custom_label ?: ($field_labels[$field_name] ?? '');

// Fields that can be links
$linkable_fields = [
    'rtm_email', 'rtm_website', 'rtm_linkedin_url', 
    'rtm_google_scholar_url', 'rtm_researchgate_url'
];

// Format the field value based on field type and link settings
$formatted_value = '';
if ($field_value) {
    $display_text = $link_text ?: $field_value; // Use custom link text if provided
    
    switch ($field_name) {
        case 'rtm_website':
        case 'rtm_linkedin_url':
        case 'rtm_google_scholar_url':
        case 'rtm_researchgate_url':
            // URL fields
            if ($make_link) {
                $formatted_value = sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', 
                    esc_url($field_value), 
                    esc_html($display_text)
                );
            } else {
                $formatted_value = esc_html($field_value);
            }
            break;
        
        case 'rtm_email':
            // Email field
            if ($make_link) {
                $formatted_value = sprintf('<a href="mailto:%s">%s</a>', 
                    esc_attr($field_value), 
                    esc_html($display_text)
                );
            } else {
                $formatted_value = esc_html($field_value);
            }
            break;
        
        case 'rtm_member_status':
            // Format member status
            $status_text = ($field_value === 'current') ? __('Current Member', 'research-team-manager') : __('Alumni Member', 'research-team-manager');
            $formatted_value = esc_html($status_text);
            break;
        
        case 'rtm_short_description':
            // Allow HTML in descriptions and limit to 50 words or 350 characters
            $formatted_value = wp_kses_post($field_value);
            // Truncate to 50 words or 350 characters, whichever is shorter
            $words = explode(' ', strip_tags($formatted_value));
            if (count($words) > 50) {
                $formatted_value = implode(' ', array_slice($words, 0, 50)) . '...';
            } elseif (strlen($formatted_value) > 350) {
                $formatted_value = substr($formatted_value, 0, 347) . '...';
            }
            break;
        
        case 'rtm_long_description':
            // Allow HTML in descriptions
            $formatted_value = wp_kses_post($field_value);
            break;
        
        default:
            // Regular text fields
            $formatted_value = esc_html($field_value);
            break;
    }
} else if ($fallback_text) {
    $formatted_value = esc_html($fallback_text);
}

// Determine what content to display
$display_content = '';
if ($content) {
    // If custom content is provided, use it
    $display_content = $content;
} else if ($formatted_value) {
    // Use the formatted field value
    $display_content = $formatted_value;
}

// Don't render anything if there's no content to show
if (!$display_content) {
    return '';
}

// Build the output
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'team-member-field-block']);
$output = '<div ' . $wrapper_attributes . '>';

if ($show_label && $label) {
    $output .= '<span class="team-member-field-label">' . esc_html($label) . ': </span>';
}

$output .= '<span class="team-member-field-content">' . $display_content . '</span>';
$output .= '</div>';

echo $output;
?>