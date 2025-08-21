<?php
/**
 * Team Members Import Script for Research Team Manager Plugin
 * 
 * This script imports team member data from the JSON export
 * created by the export-team-members.php script.
 * 
 * Place this file in the Research Team Manager plugin directory
 * and run it via: http://yoursite.com/wp-content/plugins/research-team-manager/import-team-members.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    // Try to locate wp-load.php
    $wp_load_paths = array(
        '../../../../wp-load.php',
        '../../../wp-load.php', 
        '../../wp-load.php',
        '../wp-load.php'
    );
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once(__DIR__ . '/' . $path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('Could not locate WordPress. Please ensure this script is in the correct directory.');
    }
}

// Check if user is logged in and has proper capabilities
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('You do not have permission to access this script.');
}

// Handle file upload and import
$import_results = array();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $import_results = handle_import($_FILES['import_file']);
} elseif (isset($_GET['action']) && $_GET['action'] === 'download_sample') {
    download_sample_format();
    exit;
}

/**
 * Handle the import process
 */
function handle_import($uploaded_file) {
    global $errors;
    
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . $uploaded_file['error'];
        return array();
    }
    
    $file_content = file_get_contents($uploaded_file['tmp_name']);
    if ($file_content === false) {
        $errors[] = 'Could not read the uploaded file.';
        return array();
    }
    
    $import_data = json_decode($file_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid JSON format: ' . json_last_error_msg();
        return array();
    }
    
    if (!isset($import_data['members']) || !is_array($import_data['members'])) {
        $errors[] = 'Invalid import format: missing or invalid members data.';
        return array();
    }
    
    return process_members_import($import_data);
}

/**
 * Process the imported members data
 */
function process_members_import($import_data) {
    global $errors;
    
    $results = array(
        'total_members' => count($import_data['members']),
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'created_terms' => 0,
        'processed_images' => 0,
        'details' => array()
    );
    
    // First, create any missing member status terms
    if (isset($import_data['member_status_terms'])) {
        $results['created_terms'] = create_member_status_terms($import_data['member_status_terms']);
    }
    
    foreach ($import_data['members'] as $member_data) {
        try {
            $result = import_single_member($member_data);
            
            if ($result['success']) {
                if ($result['action'] === 'created') {
                    $results['imported']++;
                } elseif ($result['action'] === 'updated') {
                    $results['updated']++;
                }
                
                if ($result['image_processed']) {
                    $results['processed_images']++;
                }
            } else {
                $results['failed']++;
                $errors[] = "Failed to import member '{$member_data['title']}': " . $result['error'];
            }
            
            $results['details'][] = $result;
            
        } catch (Exception $e) {
            $results['failed']++;
            $errors[] = "Exception importing member '{$member_data['title']}': " . $e->getMessage();
        }
    }
    
    return $results;
}

/**
 * Create member status terms
 */
function create_member_status_terms($terms_data) {
    $created_count = 0;
    
    foreach ($terms_data as $term_data) {
        // Check if term already exists
        $existing_term = get_term_by('slug', $term_data['slug'], 'member_status');
        
        if (!$existing_term) {
            $result = wp_insert_term(
                $term_data['name'],
                'member_status',
                array(
                    'slug' => $term_data['slug'],
                    'description' => $term_data['description'] ?? ''
                )
            );
            
            if (!is_wp_error($result)) {
                $created_count++;
            }
        }
    }
    
    return $created_count;
}

/**
 * Import a single member
 */
function import_single_member($member_data) {
    $result = array(
        'success' => false,
        'action' => '',
        'post_id' => 0,
        'image_processed' => false,
        'error' => '',
        'member_title' => $member_data['title'] ?? 'Unknown'
    );
    
    // Check if member already exists (by title)
    $existing_post = get_page_by_title($member_data['title'], OBJECT, 'team_member');
    
    $post_data = array(
        'post_title' => $member_data['title'],
        'post_content' => $member_data['content'] ?? '',
        'post_excerpt' => $member_data['excerpt'] ?? '',
        'post_type' => 'team_member',
        'post_status' => $member_data['status'] ?? 'publish',
        'meta_input' => array()
    );
    
    if ($existing_post) {
        // Update existing member
        $post_data['ID'] = $existing_post->ID;
        $post_id = wp_update_post($post_data);
        $result['action'] = 'updated';
    } else {
        // Create new member
        $post_id = wp_insert_post($post_data);
        $result['action'] = 'created';
    }
    
    if (is_wp_error($post_id) || !$post_id) {
        $result['error'] = is_wp_error($post_id) ? $post_id->get_error_message() : 'Failed to create/update post';
        return $result;
    }
    
    $result['post_id'] = $post_id;
    $result['success'] = true;
    
    // Import custom fields with field mapping
    $field_mappings = array(
        // Old field => New field (matching the actual plugin structure)
        'email' => '_rtm_email',
        'phonenumber' => '_rtm_phone', 
        'website' => '_rtm_website',
        'long_description' => '_rtm_long_description',
        'short_description' => '_rtm_short_description',
        'linkedin_url' => '_rtm_linkedin_url',
        'google_scholar_url' => '_rtm_google_scholar_url',
        'researchgate_url' => '_rtm_researchgate_url'
    );
    
    foreach ($field_mappings as $old_field => $new_field) {
        if (isset($member_data[$old_field]) && $member_data[$old_field] !== '') {
            $value = $member_data[$old_field];
            
            // Handle special cases
            if ($new_field === 'post_excerpt') {
                // Update post excerpt
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $value
                ));
            } else {
                // Regular meta field update
                update_post_meta($post_id, $new_field, $value);
            }
        }
    }
    
    // Map member status taxonomy to member status field, position, and type
    $member_status_value = 'Alumni Member'; // Default
    $position = ''; // Will be set based on status
    $member_type = ''; // New field for grad/undergrad/visiting scholar/honorary
    
    if (isset($member_data['member_status']) && !empty($member_data['member_status'])) {
        foreach ($member_data['member_status'] as $status_data) {
            $status_name = $status_data['name'];
            $status_slug = strtolower($status_data['slug']);
            
            // Map to member status dropdown value - plugin uses 'current' and 'alumni' values
            if (in_array($status_slug, array('team_member', 'team-member', 'matters-team-members', 'current', 'faculty', 'phd-student', 'postdoc', 'research-assistant', 'current-member', 'graduate', 'undergraduate', 'visiting', 'honorary_member', 'honorary-member'))) {
                $member_status_value = 'current';
            } elseif (in_array($status_slug, array('matter_alumni_member', 'matter-alumni-member', 'alumni', 'alumni-member'))) {
                $member_status_value = 'alumni';
            } else {
                // Default logic based on name analysis
                $lower_name = strtolower($status_name);
                if (strpos($lower_name, 'alumni') !== false) {
                    $member_status_value = 'alumni';
                } elseif (strpos($lower_name, 'team') !== false || strpos($lower_name, 'current') !== false || strpos($lower_name, 'visiting') !== false || strpos($lower_name, 'honorary') !== false) {
                    $member_status_value = 'current';
                } else {
                    $member_status_value = 'alumni'; // Default to alumni
                }
            }
            
            // Set position and member type based on your specific taxonomy
            $lower_name = strtolower($status_name);
            if ($status_slug === 'matter_alumni_member' || strpos($lower_name, 'alumni') !== false) {
                $position = 'Alumni Member';
                $member_type = 'Graduate Student'; // Default for alumni
            } elseif ($status_slug === 'team_member' || strpos($lower_name, 'team members') !== false) {
                $position = 'Team Member';
                $member_type = 'Graduate Student'; // Default for current team
            } elseif ($status_slug === 'honorary_member' || strpos($lower_name, 'visiting') !== false || strpos($lower_name, 'honorary') !== false) {
                // Parse "Visiting/ Honorary member"
                if (strpos($lower_name, 'visiting') !== false && strpos($lower_name, 'honorary') !== false) {
                    $position = 'Visiting/Honorary Member';
                    $member_type = 'Visiting Scholar'; // Default to visiting scholar
                } elseif (strpos($lower_name, 'visiting') !== false) {
                    $position = 'Visiting Scholar';
                    $member_type = 'Visiting Scholar';
                } elseif (strpos($lower_name, 'honorary') !== false) {
                    $position = 'Honorary Member';
                    $member_type = 'Honorary Member';
                } else {
                    $position = 'Visiting/Honorary Member';
                    $member_type = 'Visiting Scholar';
                }
            } elseif (strpos($status_slug, 'faculty') !== false) {
                $position = 'Faculty';
                $member_type = 'Faculty';
            } elseif (strpos($status_slug, 'phd') !== false || strpos($status_slug, 'graduate') !== false) {
                $position = 'PhD Student';
                $member_type = 'Graduate Student';
            } elseif (strpos($status_slug, 'postdoc') !== false) {
                $position = 'Postdoctoral Researcher';
                $member_type = 'Postdoc';
            } elseif (strpos($status_slug, 'master') !== false) {
                $position = 'Master\'s Student';
                $member_type = 'Graduate Student';
            } elseif (strpos($status_slug, 'undergraduate') !== false || strpos($status_slug, 'undergrad') !== false) {
                $position = 'Undergraduate Student';
                $member_type = 'Undergraduate Student';
            } elseif (strpos($status_slug, 'research') !== false) {
                $position = 'Research Assistant';
                $member_type = 'Graduate Student'; // Assume grad student unless specified
            } else {
                // Fallback: use the original name as position and try to infer type
                $position = $status_name;
                
                // Try to infer type from position name
                $lower_name = strtolower($status_name);
                if (strpos($lower_name, 'alumni') !== false) {
                    $member_type = 'Graduate Student'; // Alumni default
                } elseif (strpos($lower_name, 'phd') !== false || strpos($lower_name, 'graduate') !== false || strpos($lower_name, 'master') !== false) {
                    $member_type = 'Graduate Student';
                } elseif (strpos($lower_name, 'undergraduate') !== false || strpos($lower_name, 'undergrad') !== false) {
                    $member_type = 'Undergraduate Student';
                } elseif (strpos($lower_name, 'visiting') !== false) {
                    $member_type = 'Visiting Scholar';
                } elseif (strpos($lower_name, 'honorary') !== false) {
                    $member_type = 'Honorary Member';
                } elseif (strpos($lower_name, 'team') !== false) {
                    $member_type = 'Graduate Student'; // Team members default
                } else {
                    $member_type = 'Graduate Student'; // Final default
                }
            }
            
            break; // Use first status only
        }
    }
    
    // If no member type was determined, set a default based on current/alumni status
    if (empty($member_type)) {
        $member_type = ($member_status_value === 'Current Member') ? 'Graduate Student' : 'Graduate Student';
    }
    
    // Save member status, position, and type
    update_post_meta($post_id, '_rtm_member_status', $member_status_value);
    update_post_meta($post_id, '_rtm_position', $position);
    update_post_meta($post_id, '_rtm_member_type', $member_type);
    update_post_meta($post_id, '_rtm_is_current', ($member_status_value === 'current') ? '1' : '0');
    
    // Set display order (use member ID as fallback)
    $display_order = isset($member_data['id']) ? $member_data['id'] : $post_id;
    update_post_meta($post_id, '_rtm_order', $display_order);
    
    // Handle profile picture - this should become the featured image
    $image_url_to_import = null;
    $image_processed = false;
    
    // First priority: ACF profile_picture field
    if (isset($member_data['profile_picture']) && !empty($member_data['profile_picture'])) {
        if (is_array($member_data['profile_picture']) && isset($member_data['profile_picture']['url'])) {
            $image_url_to_import = $member_data['profile_picture']['url'];
        } elseif (is_string($member_data['profile_picture'])) {
            $image_url_to_import = $member_data['profile_picture'];
        }
    }
    
    // Second priority: featured_image field
    if (!$image_url_to_import && isset($member_data['featured_image']) && !empty($member_data['featured_image'])) {
        if (is_array($member_data['featured_image']) && isset($member_data['featured_image']['url'])) {
            $image_url_to_import = $member_data['featured_image']['url'];
        } elseif (is_string($member_data['featured_image'])) {
            $image_url_to_import = $member_data['featured_image'];
        }
    }
    
    // Import and set as featured image
    if ($image_url_to_import) {
        $image_result = import_and_attach_image($post_id, $image_url_to_import, $member_data['title']);
        if ($image_result['success']) {
            $result['image_processed'] = true;
            // Also store the URL for reference
            update_post_meta($post_id, '_rtm_profile_picture_url', $image_url_to_import);
            // Store debug info
            update_post_meta($post_id, '_rtm_import_debug', 'Image attached: ID ' . $image_result['attachment_id']);
        } else {
            // Store error for debugging
            update_post_meta($post_id, '_rtm_import_debug', 'Image failed: ' . $image_result['error']);
        }
    } else {
        update_post_meta($post_id, '_rtm_import_debug', 'No image URL found to import');
    }
    
    // Set member status terms
    if (isset($member_data['member_status']) && !empty($member_data['member_status'])) {
        $term_slugs = array();
        foreach ($member_data['member_status'] as $status_data) {
            $term_slugs[] = $status_data['slug'];
        }
        wp_set_post_terms($post_id, $term_slugs, 'member_status');
    }
    
    // Import additional meta fields
    if (isset($member_data['additional_meta'])) {
        foreach ($member_data['additional_meta'] as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }
    
    return $result;
}

/**
 * Import and attach image as featured image
 */
function import_and_attach_image($post_id, $image_url, $member_title = '') {
    $result = array('success' => false, 'attachment_id' => 0, 'error' => '');
    
    if (empty($image_url)) {
        $result['error'] = 'No image URL provided';
        return $result;
    }
    
    // Check if this is a local transferred image
    $upload_dir = wp_upload_dir();
    $local_image_path = null;
    
    // Check if image was transferred to team-member-export folder
    if (strpos($image_url, '/team-member-export/') !== false) {
        // Extract the filename from URL
        $path_parts = parse_url($image_url);
        $path = $path_parts['path'] ?? '';
        
        // Try to find the file locally
        $relative_path = str_replace('/wp-content/uploads/', '', $path);
        $local_image_path = $upload_dir['basedir'] . '/' . $relative_path;
        
        if (!file_exists($local_image_path)) {
            // Try the original site structure
            $relative_path = str_replace('https://matters.local/wp-content/uploads/', '', $image_url);
            $local_image_path = $upload_dir['basedir'] . '/' . $relative_path;
        }
    } else {
        // Try to map from old site URL to transferred image location
        // Extract filename from URL and look in team-member-export
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        
        // Try multiple locations
        $possible_paths = array(
            $upload_dir['basedir'] . '/team-member-export/images/originals/' . $filename,
            $upload_dir['basedir'] . '/team-member-export/images/thumbnails/' . $filename,
            $upload_dir['basedir'] . '/' . str_replace('https://matters.local/wp-content/uploads/', '', $image_url)
        );
        
        foreach ($possible_paths as $test_path) {
            if (file_exists($test_path)) {
                $local_image_path = $test_path;
                break;
            }
        }
        
        // If still not found, try to find by partial filename match
        if (!$local_image_path) {
            $export_originals = $upload_dir['basedir'] . '/team-member-export/images/originals/';
            if (is_dir($export_originals)) {
                $files = scandir($export_originals);
                $base_filename = pathinfo($filename, PATHINFO_FILENAME);
                
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && strpos($file, $base_filename) !== false) {
                        $local_image_path = $export_originals . $file;
                        break;
                    }
                }
            }
        }
    }
    
    // If we have a local file, use it directly
    if ($local_image_path && file_exists($local_image_path)) {
        // Get file info
        $filename = basename($local_image_path);
        $filetype = wp_check_filetype($filename, null);
        
        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => $member_title . ' - Profile Picture',
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert the attachment
        $attachment_id = wp_insert_attachment($attachment, $local_image_path, $post_id);
        
        if (!is_wp_error($attachment_id) && $attachment_id > 0) {
            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $local_image_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            
            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            
            $result['success'] = true;
            $result['attachment_id'] = $attachment_id;
        } else {
            $result['error'] = 'Failed to create attachment from local file';
        }
    } else {
        // Try to download from URL (fallback)
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download image from URL
        $tmp = download_url($image_url);
        
        if (!is_wp_error($tmp)) {
            $file_array = array(
                'name' => basename($image_url),
                'tmp_name' => $tmp
            );
            
            // Do the actual import
            $attachment_id = media_handle_sideload($file_array, $post_id, $member_title . ' - Profile Picture');
            
            // Clean up temp file
            @unlink($tmp);
            
            if (!is_wp_error($attachment_id)) {
                // Set as featured image
                set_post_thumbnail($post_id, $attachment_id);
                
                $result['success'] = true;
                $result['attachment_id'] = $attachment_id;
            } else {
                $result['error'] = 'Failed to import image: ' . $attachment_id->get_error_message();
            }
        } else {
            $result['error'] = 'Failed to download image from URL: ' . $tmp->get_error_message();
        }
    }
    
    return $result;
}

/**
 * Download sample import format
 */
function download_sample_format() {
    $sample_data = array(
        'export_info' => array(
            'source_site' => 'https://example.com',
            'export_date' => '2025-01-01 12:00:00',
            'total_members' => 2,
            'source_post_type' => 'team_member',
            'source_taxonomy' => 'member_status'
        ),
        'member_status_terms' => array(
            array(
                'term_id' => 1,
                'name' => 'Faculty',
                'slug' => 'faculty',
                'description' => 'Faculty members'
            ),
            array(
                'term_id' => 2,
                'name' => 'PhD Student',
                'slug' => 'phd-student',
                'description' => 'PhD students'
            )
        ),
        'members' => array(
            array(
                'id' => 123,
                'title' => 'Dr. John Smith',
                'content' => 'Dr. John Smith is a professor of...',
                'excerpt' => 'Brief description',
                'status' => 'publish',
                'date_created' => '2024-01-01 12:00:00',
                'phonenumber' => '+1234567890',
                'email' => 'john.smith@university.edu',
                'website' => 'https://johnsmith.com',
                'long_description' => 'Detailed description...',
                'short_description' => 'Brief bio',
                'linkedin_url' => 'https://linkedin.com/in/johnsmith',
                'google_scholar_url' => 'https://scholar.google.com/citations?user=xyz',
                'researchgate_url' => 'https://researchgate.net/profile/John_Smith',
                'profile_picture' => array(
                    'id' => 456,
                    'url' => 'https://oldsite.com/uploads/john-smith.jpg',
                    'alt' => 'Dr. John Smith'
                ),
                'member_status' => array(
                    array(
                        'term_id' => 1,
                        'name' => 'Faculty',
                        'slug' => 'faculty'
                    )
                )
            )
        )
    );
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="team-members-import-sample.json"');
    echo json_encode($sample_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Members Import - Research Team Manager</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .stats { display: flex; justify-content: space-between; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; min-width: 100px; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input[type="file"] { margin: 10px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a87; }
        .sample-link { background: #6c757d; color: white; text-decoration: none; padding: 5px 10px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Team Members Import - Research Team Manager</h1>
    
    <div class="info">
        <strong>Instructions:</strong>
        <ol>
            <li>Export your team member data using the export-team-members.php script from your old site</li>
            <li>Upload the generated JSON file using the form below</li>
            <li>Review the import results and verify the imported data</li>
        </ol>
        <p><a href="?action=download_sample" class="sample-link">Download Sample Format</a></p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Errors occurred during import:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($import_results)): ?>
        <div class="success">
            <h2>Import Completed Successfully!</h2>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['total_members']; ?></div>
                    <div>Total Members</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['imported']; ?></div>
                    <div>Imported</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['updated']; ?></div>
                    <div>Updated</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['created_terms']; ?></div>
                    <div>Terms Created</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['failed']; ?></div>
                    <div>Failed</div>
                </div>
            </div>
            
            <?php if (!empty($import_results['details'])): ?>
                <h3>Import Details</h3>
                <table>
                    <tr>
                        <th>Member Name</th>
                        <th>Action</th>
                        <th>Post ID</th>
                        <th>Image Processed</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($import_results['details'] as $detail): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['member_title']); ?></td>
                            <td><?php echo ucfirst($detail['action']); ?></td>
                            <td><?php echo $detail['post_id']; ?></td>
                            <td><?php echo $detail['image_processed'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo $detail['success'] ? 'Success' : 'Failed'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <h2>Upload Team Members Data</h2>
        <p>Select the JSON file exported from your previous WordPress site:</p>
        
        <input type="file" name="import_file" accept=".json" required>
        <br><br>
        
        <button type="submit">Import Team Members</button>
    </form>
    
    <div class="info">
        <strong>Note about Images:</strong>
        <p>This import script will store image URLs but won't automatically transfer the actual image files. 
        You'll need to manually copy images or use the export-team-images.php script to prepare them for transfer.</p>
    </div>
    
</body>
</html>