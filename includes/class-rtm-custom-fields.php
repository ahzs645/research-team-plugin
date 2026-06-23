<?php
/**
 * Custom Fields for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Custom_Fields {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'rtm_team_member_dates',
            __('Team Member Dates & Priority', 'research-team-manager'),
            array($this, 'team_member_dates_callback'),
            'rtm_team_member',
            'normal',
            'high'
        );
    }
    
    public function team_member_dates_callback($post) {
        wp_nonce_field('rtm_save_meta_box_data', 'rtm_meta_box_nonce');
        
        $start_date = get_post_meta($post->ID, '_rtm_start_date', true);
        $end_date = get_post_meta($post->ID, '_rtm_end_date', true);
        $order = get_post_meta($post->ID, '_rtm_order', true);
        $date_priority = get_post_meta($post->ID, '_rtm_date_priority', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="rtm_start_date"><?php _e('Start Date', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="date" id="rtm_start_date" name="rtm_start_date" value="<?php echo esc_attr($start_date); ?>" />
                    <p class="description"><?php _e('Date when this member joined the team', 'research-team-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_end_date"><?php _e('End Date', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="date" id="rtm_end_date" name="rtm_end_date" value="<?php echo esc_attr($end_date); ?>" />
                    <p class="description"><?php _e('Date when this member left the team (leave blank for current members)', 'research-team-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_order"><?php _e('Display Order', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="rtm_order" name="rtm_order" value="<?php echo esc_attr($order); ?>" min="0" step="1" class="small-text" />
                    <p class="description"><?php _e('Lower numbers appear first (used for both custom order and priority sorting)', 'research-team-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_date_priority"><?php _e('Priority Override', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="rtm_date_priority" name="rtm_date_priority" value="1" <?php checked($date_priority, '1'); ?> />
                    <label for="rtm_date_priority"><?php _e('Prioritize this member when using date-based sorting', 'research-team-manager'); ?></label>
                    <p class="description"><?php _e('When checked and sorting by dates, this member will appear before non-prioritized members', 'research-team-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    
    public function contact_info_callback($post) {
        // This function is no longer used - ACF handles these fields
        $email = get_post_meta($post->ID, '_rtm_email', true);
        $phone = get_post_meta($post->ID, '_rtm_phone', true);
        $office = get_post_meta($post->ID, '_rtm_office', true);
        $website = get_post_meta($post->ID, '_rtm_website', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="rtm_email"><?php _e('Email', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="email" id="rtm_email" name="rtm_email" value="<?php echo esc_attr($email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_phone"><?php _e('Phone', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="tel" id="rtm_phone" name="rtm_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_office"><?php _e('Office Location', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="text" id="rtm_office" name="rtm_office" value="<?php echo esc_attr($office); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_website"><?php _e('Personal Website', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_website" name="rtm_website" value="<?php echo esc_attr($website); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function social_links_callback($post) {
        $linkedin = get_post_meta($post->ID, '_rtm_linkedin', true);
        $twitter = get_post_meta($post->ID, '_rtm_twitter', true);
        $google_scholar = get_post_meta($post->ID, '_rtm_google_scholar', true);
        $orcid = get_post_meta($post->ID, '_rtm_orcid', true);
        $researchgate = get_post_meta($post->ID, '_rtm_researchgate', true);
        $github = get_post_meta($post->ID, '_rtm_github', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="rtm_linkedin"><?php _e('LinkedIn', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_linkedin" name="rtm_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_twitter"><?php _e('Twitter/X', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_twitter" name="rtm_twitter" value="<?php echo esc_attr($twitter); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_google_scholar"><?php _e('Google Scholar', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_google_scholar" name="rtm_google_scholar" value="<?php echo esc_attr($google_scholar); ?>" class="regular-text" />
                    <p class="description"><?php _e('Full Google Scholar profile URL', 'research-team-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_orcid"><?php _e('ORCID', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_orcid" name="rtm_orcid" value="<?php echo esc_attr($orcid); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_researchgate"><?php _e('ResearchGate', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_researchgate" name="rtm_researchgate" value="<?php echo esc_attr($researchgate); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="rtm_github"><?php _e('GitHub', 'research-team-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="rtm_github" name="rtm_github" value="<?php echo esc_attr($github); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['rtm_meta_box_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['rtm_meta_box_nonce'], 'rtm_save_meta_box_data')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (isset($_POST['post_type']) && 'rtm_team_member' == $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        } else {
            return;
        }
        
        // Only save the fields we're actually using now
        $fields = array(
            'rtm_start_date' => 'sanitize_text_field',
            'rtm_end_date' => 'sanitize_text_field',
            'rtm_order' => 'absint',
        );
        
        foreach ($fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_function, wp_unslash($_POST[$field]));
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
        
        // Save date priority checkbox
        $date_priority = isset($_POST['rtm_date_priority']) ? '1' : '0';
        update_post_meta($post_id, '_rtm_date_priority', $date_priority);
    }
}