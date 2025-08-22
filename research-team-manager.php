<?php
/**
 * Plugin Name: Research Team Manager Simple
 * Plugin URI: https://example.com/research-team-manager
 * Description: A comprehensive plugin to manage research team members and publications with Google Scholar integration.
 * Version: 1.0.0
 * Author: Research Team Manager
 * License: GPL v2 or later
 * Text Domain: research-team-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin activation hook
register_activation_hook(__FILE__, 'rtm_activate_plugin');
function rtm_activate_plugin() {
    // Create database table
    rtm_create_publications_table();
    
    // Register post type
    rtm_register_team_member_post_type();
    
    // Create default member status terms
    rtm_create_default_member_status_terms();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'rtm_deactivate_plugin');
function rtm_deactivate_plugin() {
    flush_rewrite_rules();
}

// Create publications table
function rtm_create_publications_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rtm_publications';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title text NOT NULL,
        authors text NOT NULL,
        journal varchar(255) DEFAULT '' NOT NULL,
        year int(4) NOT NULL,
        citations int(11) DEFAULT 0 NOT NULL,
        url varchar(500) DEFAULT '' NOT NULL,
        google_scholar_id varchar(100) DEFAULT '' NOT NULL,
        abstract text DEFAULT '' NOT NULL,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Create default member status terms
function rtm_create_default_member_status_terms() {
    // Default member status terms to create
    $default_terms = array(
        array(
            'name' => "MATTER's Team Members",
            'slug' => 'team_member',
            'description' => 'Current active team members'
        ),
        array(
            'name' => "MATTER's Alumni",
            'slug' => 'matter_alumni_member', 
            'description' => 'Former team members who have graduated or moved on'
        ),
        array(
            'name' => 'Visiting/ Honorary member',
            'slug' => 'honorary_member',
            'description' => 'Visiting researchers and honorary members'
        )
    );
    
    // Create terms if they don't exist
    foreach ($default_terms as $term_data) {
        if (!term_exists($term_data['slug'], 'member_status')) {
            wp_insert_term(
                $term_data['name'],
                'member_status',
                array(
                    'slug' => $term_data['slug'],
                    'description' => $term_data['description']
                )
            );
        }
    }
}

// Register Team Member Post Type
add_action('init', 'rtm_register_team_member_post_type');
function rtm_register_team_member_post_type() {
    
    $labels = array(
        'name'                  => 'Team Members',
        'singular_name'         => 'Team Member',
        'menu_name'             => 'Team Members',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Team Member',
        'edit_item'             => 'Edit Team Member',
        'new_item'              => 'New Team Member',
        'view_item'             => 'View Team Member',
        'search_items'          => 'Search Team Members',
        'not_found'             => 'No team members found',
        'not_found_in_trash'    => 'No team members found in trash',
    );
    
    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'team-member'),
        'capability_type'       => 'post',
        'has_archive'           => true,
        'hierarchical'          => false,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-groups',
        'supports'              => array('title', 'thumbnail'),
        'show_in_rest'          => true,
    );
    
    register_post_type('team_member', $args);
    
    // Register Research Area Taxonomy
    register_taxonomy(
        'research_area',
        'team_member',
        array(
            'label'             => 'Research Areas',
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'research-area'),
        )
    );
    
    // Register Team Role Taxonomy
    register_taxonomy(
        'team_role',
        'team_member',
        array(
            'label'             => 'Team Roles',
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'team-role'),
        )
    );
    
    // Register Member Status Taxonomy
    register_taxonomy(
        'member_status',
        'team_member',
        array(
            'label'             => 'Member Status',
            'labels'            => array(
                'name'              => 'Member Status',
                'singular_name'     => 'Member Status',
                'menu_name'         => 'Member Status',
                'all_items'         => 'All Member Status',
                'edit_item'         => 'Edit Member Status',
                'view_item'         => 'View Member Status',
                'update_item'       => 'Update Member Status',
                'add_new_item'      => 'Add New Member Status',
                'new_item_name'     => 'New Member Status Name',
                'search_items'      => 'Search Member Status',
                'not_found'         => 'No member status found',
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'member-status'),
            'capabilities'      => array(
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ),
        )
    );
}

// Add admin menu items
add_action('admin_menu', 'rtm_add_admin_menus');
function rtm_add_admin_menus() {
    // Add Publications submenu
    add_submenu_page(
        'edit.php?post_type=team_member',
        'Publications',
        'Publications',
        'manage_options',
        'rtm-publications',
        'rtm_publications_page'
    );
    
    // Add Scholar Settings submenu
    add_submenu_page(
        'edit.php?post_type=team_member',
        'Scholar Settings',
        'Scholar Settings',
        'manage_options',
        'rtm-scholar-settings',
        'rtm_scholar_settings_page'
    );
}

// Publications page
function rtm_publications_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    // Handle sync request
    if (isset($_POST['sync_publications']) && wp_verify_nonce($_POST['rtm_sync_nonce'], 'rtm_sync_publications')) {
        rtm_sync_publications();
    }
    
    // Handle import request
    if (isset($_POST['import_json']) && wp_verify_nonce($_POST['rtm_import_nonce'], 'rtm_import_json')) {
        rtm_import_json_publications();
    }
    
    // Get publications
    $publications = $wpdb->get_results("SELECT * FROM $table_name ORDER BY year DESC, citations DESC", ARRAY_A);
    $total_publications = count($publications);
    $total_citations = array_sum(array_column($publications, 'citations'));
    
    ?>
    <div class="wrap">
        <h1>Research Publications</h1>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0 0 10px 0;"><?php echo number_format($total_publications); ?></h3>
                <p style="margin: 0; color: #646970;">Total Publications</p>
            </div>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0 0 10px 0;"><?php echo number_format($total_citations); ?></h3>
                <p style="margin: 0; color: #646970;">Total Citations</p>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <form method="post" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field('rtm_sync_publications', 'rtm_sync_nonce'); ?>
                <input type="submit" name="sync_publications" class="button button-primary" value="Sync Publications from Google Scholar" />
            </form>
            
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('rtm_import_json', 'rtm_import_nonce'); ?>
                <input type="submit" name="import_json" class="button button-secondary" value="Import from JSON File" />
            </form>
        </div>
        
        <?php if (!empty($publications)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Authors</th>
                        <th>Journal</th>
                        <th>Year</th>
                        <th>Citations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publications as $pub): ?>
                        <tr>
                            <td>
                                <?php if ($pub['url']): ?>
                                    <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($pub['title']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($pub['authors']); ?></td>
                            <td><?php echo esc_html($pub['journal']); ?></td>
                            <td><?php echo esc_html($pub['year']); ?></td>
                            <td><?php echo esc_html($pub['citations']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No publications found. Click "Sync Publications" to fetch from Google Scholar.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Scholar Settings page
function rtm_scholar_settings_page() {
    // Save settings
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['rtm_settings_nonce'], 'rtm_save_settings')) {
        update_option('rtm_google_scholar_user_id', sanitize_text_field($_POST['rtm_google_scholar_user_id']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $user_id = get_option('rtm_google_scholar_user_id', 'm0_aWlQAAAAJ');
    
    ?>
    <div class="wrap">
        <h1>Google Scholar Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('rtm_save_settings', 'rtm_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rtm_google_scholar_user_id">Google Scholar User ID</label>
                    </th>
                    <td>
                        <input type="text" id="rtm_google_scholar_user_id" name="rtm_google_scholar_user_id" value="<?php echo esc_attr($user_id); ?>" class="regular-text" />
                        <p class="description">Enter the user ID from Google Scholar profile URL (e.g., m0_aWlQAAAAJ)</p>
                        <?php if ($user_id): ?>
                            <p><a href="https://scholar.google.com/citations?user=<?php echo esc_attr($user_id); ?>" target="_blank" class="button button-secondary">View Profile</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
            <h3>Test Connection</h3>
            <p>Current User ID: <strong><?php echo esc_html($user_id); ?></strong></p>
            <button type="button" onclick="testScholarConnection()" class="button button-secondary">Test Scholar Connection</button>
            <div id="test-results"></div>
        </div>
        
        <script>
        function testScholarConnection() {
            document.getElementById('test-results').innerHTML = '<p>Testing connection...</p>';
            // In production, this would make an AJAX call
            setTimeout(function() {
                document.getElementById('test-results').innerHTML = '<div class="notice notice-success"><p>Connection test would run here with AJAX.</p></div>';
            }, 1000);
        }
        </script>
    </div>
    <?php
}

// Sync publications function
function rtm_sync_publications() {
    $user_id = get_option('rtm_google_scholar_user_id', 'm0_aWlQAAAAJ');
    
    if (empty($user_id)) {
        return false;
    }
    
    // For now, add sample data
    // In production, this would scrape Google Scholar
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    // Sample publication data
    $sample_publications = array(
        array(
            'title' => 'Sample Research Paper on Machine Learning',
            'authors' => 'Smith J., Jones A., Williams B.',
            'journal' => 'Journal of AI Research',
            'year' => 2024,
            'citations' => 45,
            'url' => 'https://example.com/paper1',
            'google_scholar_id' => 'sample_id_1',
            'abstract' => 'This is a sample abstract for the research paper.'
        ),
        array(
            'title' => 'Advanced Neural Network Architectures',
            'authors' => 'Johnson M., Brown K., Davis L.',
            'journal' => 'Neural Computing Conference',
            'year' => 2023,
            'citations' => 128,
            'url' => 'https://example.com/paper2',
            'google_scholar_id' => 'sample_id_2',
            'abstract' => 'Exploring new architectures in neural networks.'
        ),
    );
    
    foreach ($sample_publications as $pub) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE google_scholar_id = %s",
            $pub['google_scholar_id']
        ));
        
        if (!$existing) {
            $wpdb->insert($table_name, $pub);
        }
    }
    
    echo '<div class="notice notice-success"><p>Publications synced successfully!</p></div>';
}

// Import JSON publications function
function rtm_import_json_publications() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    // Path to the JSON file
    $json_file = plugin_dir_path(__FILE__) . 'articles-import.json';
    
    if (!file_exists($json_file)) {
        echo '<div class="notice notice-error"><p>JSON import file not found: articles-import.json</p></div>';
        return false;
    }
    
    // Read and decode JSON file
    $json_content = file_get_contents($json_file);
    $publications = json_decode($json_content, true);
    
    if (!$publications) {
        echo '<div class="notice notice-error"><p>Invalid JSON file or empty data.</p></div>';
        return false;
    }
    
    $imported_count = 0;
    $updated_count = 0;
    
    foreach ($publications as $pub) {
        // Check if publication already exists by title and year
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE title = %s AND year = %d",
            $pub['title'],
            $pub['publication_year']
        ));
        
        // Prepare data for database insertion
        $pub_data = array(
            'title' => $pub['title'],
            'authors' => $pub['authors'],
            'journal' => $pub['journal'],
            'year' => $pub['publication_year'],
            'citations' => $pub['citation_count'],
            'url' => $pub['pub_url'],
            'google_scholar_id' => 'imported_' . $pub['extraction_order'],
            'abstract' => ''
        );
        
        if ($existing) {
            // Update existing publication
            $wpdb->update($table_name, $pub_data, array('id' => $existing));
            $updated_count++;
        } else {
            // Insert new publication
            $wpdb->insert($table_name, $pub_data);
            $imported_count++;
        }
    }
    
    echo '<div class="notice notice-success"><p>JSON import completed! ' . $imported_count . ' new publications imported, ' . $updated_count . ' publications updated.</p></div>';
}

// Add meta boxes for team members
add_action('add_meta_boxes', 'rtm_add_team_member_meta_boxes');
function rtm_add_team_member_meta_boxes() {
    add_meta_box(
        'rtm_member_details',
        'Team Member Details',
        'rtm_member_details_callback',
        'team_member',
        'normal',
        'high'
    );
}

// Meta box callback
function rtm_member_details_callback($post) {
    wp_nonce_field('rtm_save_member_meta', 'rtm_member_nonce');
    
    // Get existing values
    $short_description = get_post_meta($post->ID, '_rtm_short_description', true);
    $long_description = get_post_meta($post->ID, '_rtm_long_description', true);
    $linkedin_url = get_post_meta($post->ID, '_rtm_linkedin_url', true);
    $google_scholar_url = get_post_meta($post->ID, '_rtm_google_scholar_url', true);
    $researchgate_url = get_post_meta($post->ID, '_rtm_researchgate_url', true);
    $member_status = get_post_meta($post->ID, '_rtm_member_status', true);
    if (!$member_status) $member_status = 'current'; // Default to current member
    $email = get_post_meta($post->ID, '_rtm_email', true);
    $phonenumber = get_post_meta($post->ID, '_rtm_phonenumber', true);
    $website = get_post_meta($post->ID, '_rtm_website', true);
    $position = get_post_meta($post->ID, '_rtm_position', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="rtm_short_description">Short Description *</label></th>
            <td>
                <?php
                wp_editor($short_description, 'rtm_short_description', array(
                    'textarea_name' => 'rtm_short_description',
                    'media_buttons' => true,
                    'textarea_rows' => 5,
                    'teeny' => false,
                    'tinymce' => true,
                    'quicktags' => true,
                ));
                ?>
                <p class="description">Enter a brief description for the team listing page.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_sync_descriptions">Sync Descriptions</label></th>
            <td>
                <label>
                    <input type="checkbox" id="rtm_sync_descriptions" name="rtm_sync_descriptions" value="1" />
                    Use the same content for both short and long descriptions
                </label>
                <p class="description">When checked, the long description will automatically match the short description.</p>
            </td>
        </tr>
        <tr id="rtm_long_description_row">
            <th><label for="rtm_long_description">Long Description</label></th>
            <td>
                <?php
                wp_editor($long_description, 'rtm_long_description', array(
                    'textarea_name' => 'rtm_long_description',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny' => false,
                    'tinymce' => true,
                    'quicktags' => true,
                ));
                ?>
                <p class="description">Enter a detailed description for the individual profile page.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_linkedin_url">LinkedIn URL</label></th>
            <td>
                <input type="url" id="rtm_linkedin_url" name="rtm_linkedin_url" value="<?php echo esc_attr($linkedin_url); ?>" class="regular-text" />
                <p class="description">Enter the LinkedIn profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_google_scholar_url">Google Scholar URL</label></th>
            <td>
                <input type="url" id="rtm_google_scholar_url" name="rtm_google_scholar_url" value="<?php echo esc_attr($google_scholar_url); ?>" class="regular-text" />
                <p class="description">Enter the Google Scholar profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_researchgate_url">ResearchGate URL</label></th>
            <td>
                <input type="url" id="rtm_researchgate_url" name="rtm_researchgate_url" value="<?php echo esc_attr($researchgate_url); ?>" class="regular-text" />
                <p class="description">Enter the ResearchGate profile URL.</p>
            </td>
        </tr>
        <tr>
            <th><label for="rtm_email">Email</label></th>
            <td><input type="email" id="rtm_email" name="rtm_email" value="<?php echo esc_attr($email); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_phonenumber">Phone Number</label></th>
            <td><input type="text" id="rtm_phonenumber" name="rtm_phonenumber" value="<?php echo esc_attr($phonenumber); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_website">Website</label></th>
            <td><input type="url" id="rtm_website" name="rtm_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="rtm_position">Position</label></th>
            <td><input type="text" id="rtm_position" name="rtm_position" value="<?php echo esc_attr($position); ?>" class="regular-text" /></td>
        </tr>
    </table>
    
    <script>
    jQuery(document).ready(function($) {
        // Check if descriptions are currently the same
        function checkIfDescriptionsSynced() {
            var shortDesc = '';
            var longDesc = '';
            
            // Get short description content from TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_short_description')) {
                shortDesc = tinyMCE.get('rtm_short_description').getContent();
            } else {
                shortDesc = $('#rtm_short_description').val();
            }
            
            // Get long description content from TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_long_description')) {
                longDesc = tinyMCE.get('rtm_long_description').getContent();
            } else {
                longDesc = $('#rtm_long_description').val();
            }
            
            // If they're the same, check the box
            if (shortDesc === longDesc && shortDesc !== '') {
                $('#rtm_sync_descriptions').prop('checked', true);
                $('#rtm_long_description_row').hide();
            }
        }
        
        // Check on page load
        setTimeout(checkIfDescriptionsSynced, 1000);
        
        // Handle checkbox change
        $('#rtm_sync_descriptions').change(function() {
            if ($(this).is(':checked')) {
                // Hide long description field
                $('#rtm_long_description_row').hide();
                
                // Copy short description to long description
                var shortContent = '';
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('rtm_short_description')) {
                    shortContent = tinyMCE.get('rtm_short_description').getContent();
                    if (tinyMCE.get('rtm_long_description')) {
                        tinyMCE.get('rtm_long_description').setContent(shortContent);
                    }
                } else {
                    shortContent = $('#rtm_short_description').val();
                }
                $('#rtm_long_description').val(shortContent);
            } else {
                // Show long description field
                $('#rtm_long_description_row').show();
            }
        });
        
        // Sync content in real-time when checkbox is checked
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.on('AddEditor', function(e) {
                if (e.editor.id === 'rtm_short_description') {
                    e.editor.on('change keyup', function() {
                        if ($('#rtm_sync_descriptions').is(':checked')) {
                            var content = this.getContent();
                            if (tinyMCE.get('rtm_long_description')) {
                                tinyMCE.get('rtm_long_description').setContent(content);
                            }
                            $('#rtm_long_description').val(content);
                        }
                    });
                }
            });
        }
        
        // Fallback for text mode
        $('#rtm_short_description').on('input', function() {
            if ($('#rtm_sync_descriptions').is(':checked')) {
                $('#rtm_long_description').val($(this).val());
            }
        });
    });
    </script>
    <?php
}

// Save meta box data
add_action('save_post', 'rtm_save_team_member_meta');
function rtm_save_team_member_meta($post_id) {
    // Security checks
    if (!isset($_POST['rtm_member_nonce']) || !wp_verify_nonce($_POST['rtm_member_nonce'], 'rtm_save_member_meta')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Check if sync checkbox is checked
    if (isset($_POST['rtm_sync_descriptions']) && $_POST['rtm_sync_descriptions'] == '1') {
        // If syncing, copy short description to long description
        if (isset($_POST['rtm_short_description'])) {
            $short_desc = wp_kses_post($_POST['rtm_short_description']);
            update_post_meta($post_id, '_rtm_short_description', $short_desc);
            update_post_meta($post_id, '_rtm_long_description', $short_desc);
        }
    } else {
        // Save short and long descriptions separately
        if (isset($_POST['rtm_short_description'])) {
            update_post_meta($post_id, '_rtm_short_description', wp_kses_post($_POST['rtm_short_description']));
        }
        if (isset($_POST['rtm_long_description'])) {
            update_post_meta($post_id, '_rtm_long_description', wp_kses_post($_POST['rtm_long_description']));
        }
    }
    
    // Save other fields
    $fields = array(
        'rtm_linkedin_url' => 'esc_url_raw',
        'rtm_google_scholar_url' => 'esc_url_raw',
        'rtm_researchgate_url' => 'esc_url_raw',
        'rtm_email' => 'sanitize_email',
        'rtm_phonenumber' => 'sanitize_text_field',
        'rtm_website' => 'esc_url_raw',
        'rtm_position' => 'sanitize_text_field',
    );
    
    foreach ($fields as $field => $sanitize) {
        if (isset($_POST[$field])) {
            $value = call_user_func($sanitize, $_POST[$field]);
            update_post_meta($post_id, '_' . $field, $value);
        }
    }
}

// Register shortcodes
add_shortcode('rtm_team_members', 'rtm_team_members_shortcode');
function rtm_team_members_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10,
        'role' => '',
        'research_area' => '',
    ), $atts);
    
    $args = array(
        'post_type' => 'team_member',
        'posts_per_page' => intval($atts['limit']),
        'post_status' => 'publish',
    );
    
    $query = new WP_Query($args);
    
    ob_start();
    
    if ($query->have_posts()) {
        echo '<div class="rtm-team-members" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';
        
        while ($query->have_posts()) {
            $query->the_post();
            $position = get_post_meta(get_the_ID(), '_rtm_position', true);
            $email = get_post_meta(get_the_ID(), '_rtm_email', true);
            $short_description = get_post_meta(get_the_ID(), '_rtm_short_description', true);
            
            // Get member status from taxonomy
            $member_status_terms = get_the_terms(get_the_ID(), 'member_status');
            $member_status_display = '';
            if ($member_status_terms && !is_wp_error($member_status_terms)) {
                $member_status_names = wp_list_pluck($member_status_terms, 'name');
                $member_status_display = implode(', ', $member_status_names);
            }
            
            // Use post title as the display name
            $display_name = get_the_title();
            
            ?>
            <div style="border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
                <?php if (has_post_thumbnail()): ?>
                    <div style="margin-bottom: 15px;">
                        <?php the_post_thumbnail('medium', array('style' => 'width: 100%; height: auto;')); ?>
                    </div>
                <?php endif; ?>
                
                <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($display_name); ?></h3>
                
                <?php if ($position): ?>
                    <p style="color: #666; margin: 5px 0;"><strong><?php echo esc_html($position); ?></strong></p>
                <?php endif; ?>
                
                <?php if ($member_status_display): ?>
                    <p style="margin: 5px 0; font-size: 0.9em;">
                        <span style="background: #035642; color: white; padding: 2px 8px; border-radius: 12px;">
                            <?php echo esc_html($member_status_display); ?>
                        </span>
                    </p>
                <?php endif; ?>
                
                <?php if ($email): ?>
                    <p style="margin: 5px 0;"><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <?php if ($short_description): ?>
                        <?php echo wp_kses_post($short_description); ?>
                    <?php else: ?>
                        <?php the_excerpt(); ?>
                    <?php endif; ?>
                </div>
                
                <a href="<?php the_permalink(); ?>" style="color: #0073aa;">View Profile →</a>
            </div>
            <?php
        }
        
        echo '</div>';
    } else {
        echo '<p>No team members found.</p>';
    }
    
    wp_reset_postdata();
    
    return ob_get_clean();
}

// Register publications shortcode
add_shortcode('rtm_publications', 'rtm_publications_shortcode');
function rtm_publications_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    $atts = shortcode_atts(array(
        'limit' => 10,
    ), $atts);
    
    $publications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY year DESC, citations DESC LIMIT %d",
        intval($atts['limit'])
    ), ARRAY_A);
    
    ob_start();
    
    if (!empty($publications)) {
        echo '<div class="rtm-publications">';
        
        foreach ($publications as $pub) {
            ?>
            <div style="margin-bottom: 20px; padding: 15px; border-left: 3px solid #0073aa;">
                <h4 style="margin: 0 0 5px 0;">
                    <?php if ($pub['url']): ?>
                        <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($pub['title']); ?>
                    <?php endif; ?>
                </h4>
                <p style="margin: 5px 0; color: #666;"><?php echo esc_html($pub['authors']); ?></p>
                <p style="margin: 5px 0; font-style: italic;"><?php echo esc_html($pub['journal']); ?> (<?php echo esc_html($pub['year']); ?>)</p>
                <?php if ($pub['citations'] > 0): ?>
                    <p style="margin: 5px 0; color: #0073aa;">Cited by <?php echo esc_html($pub['citations']); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }
        
        echo '</div>';
    } else {
        echo '<p>No publications available.</p>';
    }
    
    return ob_get_clean();
}

// Allow block editor for team members (comment out to disable)
// add_filter('use_block_editor_for_post_type', 'rtm_disable_gutenberg', 10, 2);
// function rtm_disable_gutenberg($current_status, $post_type) {
//     if ($post_type === 'team_member') return false;
//     return $current_status;
// }

// Initialize on plugins_loaded to ensure everything is ready
add_action('plugins_loaded', 'rtm_init_plugin');
function rtm_init_plugin() {
    // Ensure tables exist
    rtm_create_publications_table();
}

// Include required classes
require_once plugin_dir_path(__FILE__) . 'includes/class-blocks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rtm-custom-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rtm-rest-api.php';

// Initialize blocks and custom fields
add_action('init', 'rtm_init_blocks');
function rtm_init_blocks() {
    new RTM_Blocks();
    new RTM_Custom_Fields();
}

// CSS and JS assets are now handled by the child theme
// add_action('wp_enqueue_scripts', 'rtm_enqueue_team_cards_assets');
// function rtm_enqueue_team_cards_assets() {
//     // Enqueue CSS
//     wp_enqueue_style(
//         'rtm-team-member-cards',
//         plugin_dir_url(__FILE__) . 'assets/team-member-cards.css',
//         array(),
//         '1.0.0'
//     );
//     
//     // Enqueue publications CSS
//     wp_enqueue_style(
//         'rtm-publications',
//         plugin_dir_url(__FILE__) . 'assets/publications.css',
//         array(),
//         '1.0.0'
//     );
//     
//     // Enqueue JS for button functionality
//     wp_enqueue_script(
//         'rtm-team-member-cards-js',
//         plugin_dir_url(__FILE__) . 'assets/team-member-cards.js',
//         array('jquery'),
//         '1.0.0',
//         true
//     );
// }

// Sorted publications shortcode using database
function rtm_sorted_publications_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rtm_publications';
    
    $atts = shortcode_atts(array(
        'limit' => -1,
    ), $atts);
    
    // Get publications from database, ordered by year desc
    $publications = $wpdb->get_results(
        "SELECT * FROM $table_name ORDER BY year DESC, citations DESC",
        ARRAY_A
    );
    
    if (empty($publications)) {
        return '<p>No publications available.</p>';
    }
    
    // Group publications by year
    $publications_by_year = array();
    foreach ($publications as $pub) {
        $year = $pub['year'];
        if (!isset($publications_by_year[$year])) {
            $publications_by_year[$year] = array();
        }
        $publications_by_year[$year][] = $pub;
    }
    
    ob_start();
    ?>
    <div class="rtm-publications-list">
        <div class="rtm-publications-content">
            <?php foreach ($publications_by_year as $year => $pubs): ?>
                <div class="rtm-publication-year" id="year-<?php echo esc_attr($year); ?>">
                    <h2 class="rtm-year-heading"><?php echo esc_html($year); ?></h2>
                    <?php foreach ($pubs as $pub): ?>
                        <div class="rtm-publication-item">
                            <div class="rtm-publication-title"><?php echo esc_html($pub['title']); ?></div>
                            <div class="rtm-publication-authors"><?php echo esc_html($pub['authors']); ?></div>
                            
                            <?php if (!empty($pub['journal'])): ?>
                                <div class="rtm-publication-journal"><em><?php echo esc_html($pub['journal']); ?></em></div>
                            <?php endif; ?>
                            
                            <div class="rtm-publication-links">
                                <?php if (!empty($pub['url'])): ?>
                                    <a href="<?php echo esc_url($pub['url']); ?>" target="_blank" class="rtm-url-link">View Publication</a>
                                <?php endif; ?>
                                
                                <?php if ($pub['citations'] > 0): ?>
                                    <span class="rtm-citation-count">Cited by <?php echo esc_html($pub['citations']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Year navigation -->
        <div class="rtm-year-navigation">
            <h3>Jump to Year:</h3>
            <ul>
                <?php foreach (array_keys($publications_by_year) as $year): ?>
                    <li><a href="#year-<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

// Register sorted_publications shortcode
add_shortcode('sorted_publications', 'rtm_sorted_publications_shortcode');