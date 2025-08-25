<?php
/**
 * Direct Database Import Script for Team Members
 * 
 * This script directly connects to the source database and imports team members
 * into the Research Team Manager plugin on the current site.
 * 
 * Place this file in the Research Team Manager plugin directory
 * and run it via: http://matter2.local/wp-content/plugins/research-team-manager/direct-import.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
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

// Source database configuration for matter.local
$source_db_config = array(
    'host' => 'localhost',
    'socket' => '/Users/ahzs645/Library/Application Support/Local/run/5cSaRH-C6/mysql/mysqld.sock',
    'database' => 'local',
    'username' => 'root',
    'password' => 'root',
    'prefix' => 'wp_' // WordPress table prefix for source site
);

// Process import
$import_results = array();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'import') {
        $import_results = perform_direct_import();
    } elseif ($_POST['action'] === 'test_connection') {
        test_source_connection();
        exit;
    }
}

/**
 * Test connection to source database
 */
function test_source_connection() {
    global $source_db_config, $errors;
    
    try {
        $source_db = new mysqli(
            $source_db_config['host'],
            $source_db_config['username'],
            $source_db_config['password'],
            $source_db_config['database'],
            null,
            $source_db_config['socket']
        );
        
        if ($source_db->connect_error) {
            echo json_encode(array(
                'success' => false,
                'error' => 'Connection failed: ' . $source_db->connect_error
            ));
        } else {
            // Test query to check tables
            $query = "SELECT COUNT(*) as count FROM {$source_db_config['prefix']}posts WHERE post_type = 'team_member'";
            $result = $source_db->query($query);
            
            if ($result) {
                $row = $result->fetch_assoc();
                echo json_encode(array(
                    'success' => true,
                    'message' => 'Connected successfully!',
                    'team_members_found' => $row['count']
                ));
            } else {
                echo json_encode(array(
                    'success' => false,
                    'error' => 'Query failed: ' . $source_db->error
                ));
            }
            
            $source_db->close();
        }
    } catch (Exception $e) {
        echo json_encode(array(
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ));
    }
}

/**
 * Perform direct database import
 */
function perform_direct_import() {
    global $source_db_config, $errors;
    
    $results = array(
        'total_members' => 0,
        'imported' => 0,
        'updated' => 0,
        'failed' => 0,
        'created_terms' => 0,
        'processed_images' => 0,
        'details' => array()
    );
    
    try {
        // Connect to source database
        $source_db = new mysqli(
            $source_db_config['host'],
            $source_db_config['username'],
            $source_db_config['password'],
            $source_db_config['database'],
            null,
            $source_db_config['socket']
        );
        
        if ($source_db->connect_error) {
            $errors[] = 'Failed to connect to source database: ' . $source_db->connect_error;
            return $results;
        }
        
        // Set charset
        $source_db->set_charset("utf8mb4");
        
        // First, import member status taxonomy terms
        $terms_imported = import_taxonomy_terms($source_db);
        $results['created_terms'] = $terms_imported;
        
        // Get all team members from source database
        $query = "SELECT p.*, pm_name.meta_value as acf_name, 
                  pm_short_desc.meta_value as acf_short_description,
                  pm_long_desc.meta_value as acf_long_description,
                  pm_linkedin.meta_value as acf_linkedin_url,
                  pm_scholar.meta_value as acf_google_scholar_url,
                  pm_researchgate.meta_value as acf_researchgate_url,
                  pm_email.meta_value as acf_email,
                  pm_phone.meta_value as acf_phonenumber,
                  pm_website.meta_value as acf_website,
                  pm_position.meta_value as acf_position,
                  pm_picture.meta_value as acf_profile_picture,
                  pm_thumbnail.meta_value as thumbnail_id
                  FROM {$source_db_config['prefix']}posts p
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_name 
                    ON p.ID = pm_name.post_id AND pm_name.meta_key = 'name'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_short_desc 
                    ON p.ID = pm_short_desc.post_id AND pm_short_desc.meta_key = 'short_description'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_long_desc 
                    ON p.ID = pm_long_desc.post_id AND pm_long_desc.meta_key = 'long_description'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_linkedin 
                    ON p.ID = pm_linkedin.post_id AND pm_linkedin.meta_key = 'linkedin_url'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_scholar 
                    ON p.ID = pm_scholar.post_id AND pm_scholar.meta_key = 'google_scholar_url'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_researchgate 
                    ON p.ID = pm_researchgate.post_id AND pm_researchgate.meta_key = 'researchgate_url'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_email 
                    ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_phone 
                    ON p.ID = pm_phone.post_id AND pm_phone.meta_key = 'phonenumber'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_website 
                    ON p.ID = pm_website.post_id AND pm_website.meta_key = 'website'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_position 
                    ON p.ID = pm_position.post_id AND pm_position.meta_key = 'position'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_picture 
                    ON p.ID = pm_picture.post_id AND pm_picture.meta_key = 'profile_picture'
                  LEFT JOIN {$source_db_config['prefix']}postmeta pm_thumbnail 
                    ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
                  WHERE p.post_type = 'team_member'
                  AND p.post_status IN ('publish', 'draft', 'private')
                  ORDER BY p.post_title ASC";
        
        $members_result = $source_db->query($query);
        
        if (!$members_result) {
            $errors[] = 'Failed to query team members: ' . $source_db->error;
            return $results;
        }
        
        $results['total_members'] = $members_result->num_rows;
        
        while ($member = $members_result->fetch_assoc()) {
            $import_result = import_single_member_direct($member, $source_db);
            
            if ($import_result['success']) {
                if ($import_result['action'] === 'created') {
                    $results['imported']++;
                } elseif ($import_result['action'] === 'updated') {
                    $results['updated']++;
                }
                
                if ($import_result['image_processed']) {
                    $results['processed_images']++;
                }
            } else {
                $results['failed']++;
                $errors[] = "Failed to import member '{$member['post_title']}': " . $import_result['error'];
            }
            
            $results['details'][] = $import_result;
        }
        
        $source_db->close();
        
    } catch (Exception $e) {
        $errors[] = 'Exception during import: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Import taxonomy terms from source database
 */
function import_taxonomy_terms($source_db) {
    global $source_db_config;
    
    $created_count = 0;
    
    $query = "SELECT t.*, tt.description, tt.parent, tt.count
              FROM {$source_db_config['prefix']}terms t
              INNER JOIN {$source_db_config['prefix']}term_taxonomy tt 
                ON t.term_id = tt.term_id
              WHERE tt.taxonomy = 'member_status'";
    
    $result = $source_db->query($query);
    
    if ($result) {
        while ($term = $result->fetch_assoc()) {
            // Check if term already exists
            $existing_term = get_term_by('slug', $term['slug'], 'member_status');
            
            if (!$existing_term) {
                $new_term = wp_insert_term(
                    $term['name'],
                    'member_status',
                    array(
                        'slug' => $term['slug'],
                        'description' => $term['description']
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    $created_count++;
                }
            }
        }
    }
    
    return $created_count;
}

/**
 * Import a single member directly from database
 */
function import_single_member_direct($member_data, $source_db) {
    global $source_db_config;
    
    $result = array(
        'success' => false,
        'action' => '',
        'post_id' => 0,
        'image_processed' => false,
        'error' => '',
        'member_title' => $member_data['post_title'] ?? 'Unknown'
    );
    
    // Check if member already exists
    $existing_post = get_page_by_title($member_data['post_title'], OBJECT, 'team_member');
    
    $post_data = array(
        'post_title' => $member_data['post_title'],
        'post_content' => $member_data['post_content'],
        'post_excerpt' => $member_data['post_excerpt'],
        'post_type' => 'team_member',
        'post_status' => $member_data['post_status'],
        'post_date' => $member_data['post_date'],
        'post_modified' => $member_data['post_modified']
    );
    
    if ($existing_post) {
        $post_data['ID'] = $existing_post->ID;
        $post_id = wp_update_post($post_data);
        $result['action'] = 'updated';
    } else {
        $post_id = wp_insert_post($post_data);
        $result['action'] = 'created';
    }
    
    if (is_wp_error($post_id) || !$post_id) {
        $result['error'] = is_wp_error($post_id) ? $post_id->get_error_message() : 'Failed to create/update post';
        return $result;
    }
    
    $result['post_id'] = $post_id;
    $result['success'] = true;
    
    // Import ACF fields to RTM fields
    $field_mappings = array(
        'acf_email' => '_rtm_email',
        'acf_phonenumber' => '_rtm_phone',
        'acf_website' => '_rtm_website',
        'acf_long_description' => '_rtm_long_description',
        'acf_short_description' => '_rtm_short_description',
        'acf_linkedin_url' => '_rtm_linkedin_url',
        'acf_google_scholar_url' => '_rtm_google_scholar_url',
        'acf_researchgate_url' => '_rtm_researchgate_url',
        'acf_position' => '_rtm_position'
    );
    
    foreach ($field_mappings as $source_field => $target_field) {
        if (!empty($member_data[$source_field])) {
            update_post_meta($post_id, $target_field, $member_data[$source_field]);
        }
    }
    
    // Get and set member status from taxonomy
    $term_query = "SELECT t.name, t.slug 
                   FROM {$source_db_config['prefix']}terms t
                   INNER JOIN {$source_db_config['prefix']}term_taxonomy tt 
                     ON t.term_id = tt.term_id
                   INNER JOIN {$source_db_config['prefix']}term_relationships tr 
                     ON tt.term_taxonomy_id = tr.term_taxonomy_id
                   WHERE tr.object_id = {$member_data['ID']}
                   AND tt.taxonomy = 'member_status'";
    
    $terms_result = $source_db->query($term_query);
    
    $member_status_value = 'alumni'; // Default
    $position = $member_data['acf_position'] ?? '';
    
    if ($terms_result && $terms_result->num_rows > 0) {
        $term_slugs = array();
        
        while ($term = $terms_result->fetch_assoc()) {
            $term_slugs[] = $term['slug'];
            
            // Determine member status based on taxonomy
            $status_slug = strtolower($term['slug']);
            $status_name = strtolower($term['name']);
            
            if (strpos($status_slug, 'alumni') !== false || strpos($status_name, 'alumni') !== false) {
                $member_status_value = 'alumni';
                if (empty($position)) $position = 'Alumni Member';
            } else {
                $member_status_value = 'current';
                if (empty($position)) $position = 'Team Member';
            }
        }
        
        // Set taxonomy terms in new site
        wp_set_post_terms($post_id, $term_slugs, 'member_status');
    }
    
    // Save member status and related fields
    update_post_meta($post_id, '_rtm_member_status', $member_status_value);
    update_post_meta($post_id, '_rtm_position', $position);
    update_post_meta($post_id, '_rtm_is_current', ($member_status_value === 'current') ? '1' : '0');
    update_post_meta($post_id, '_rtm_order', $member_data['ID']); // Use original ID for ordering
    
    // Handle featured image if exists
    if (!empty($member_data['thumbnail_id'])) {
        // Get image details from source database
        $image_query = "SELECT p.*, pm.meta_value as file_path
                        FROM {$source_db_config['prefix']}posts p
                        LEFT JOIN {$source_db_config['prefix']}postmeta pm 
                          ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                        WHERE p.ID = {$member_data['thumbnail_id']}
                        AND p.post_type = 'attachment'";
        
        $image_result = $source_db->query($image_query);
        
        if ($image_result && $image_result->num_rows > 0) {
            $image_data = $image_result->fetch_assoc();
            
            // Try to import the image
            $image_import_result = import_image_from_source($post_id, $image_data, $member_data['post_title']);
            
            if ($image_import_result['success']) {
                $result['image_processed'] = true;
            }
        }
    }
    
    // Handle ACF profile picture field if no featured image
    if (!$result['image_processed'] && !empty($member_data['acf_profile_picture'])) {
        // ACF stores attachment ID in this field
        $acf_image_id = $member_data['acf_profile_picture'];
        
        $image_query = "SELECT p.*, pm.meta_value as file_path
                        FROM {$source_db_config['prefix']}posts p
                        LEFT JOIN {$source_db_config['prefix']}postmeta pm 
                          ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                        WHERE p.ID = {$acf_image_id}
                        AND p.post_type = 'attachment'";
        
        $image_result = $source_db->query($image_query);
        
        if ($image_result && $image_result->num_rows > 0) {
            $image_data = $image_result->fetch_assoc();
            
            $image_import_result = import_image_from_source($post_id, $image_data, $member_data['post_title']);
            
            if ($image_import_result['success']) {
                $result['image_processed'] = true;
            }
        }
    }
    
    return $result;
}

/**
 * Import image from source database
 */
function import_image_from_source($post_id, $image_data, $member_title = '') {
    $result = array('success' => false, 'attachment_id' => 0, 'error' => '');
    
    if (empty($image_data['file_path'])) {
        $result['error'] = 'No image file path found';
        return $result;
    }
    
    // Build the source file path
    $source_site_path = '/Users/ahzs645/Local Sites/matter/app/public/';
    $source_file_path = $source_site_path . 'wp-content/uploads/' . $image_data['file_path'];
    
    if (!file_exists($source_file_path)) {
        $result['error'] = 'Source image file not found: ' . $source_file_path;
        return $result;
    }
    
    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    
    // Create a unique filename
    $filename = basename($image_data['file_path']);
    $new_filename = wp_unique_filename($upload_dir['path'], $filename);
    $new_file_path = $upload_dir['path'] . '/' . $new_filename;
    
    // Copy the file
    if (copy($source_file_path, $new_file_path)) {
        // Get file type
        $filetype = wp_check_filetype($new_filename, null);
        
        // Prepare attachment data
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $new_filename,
            'post_mime_type' => $filetype['type'],
            'post_title' => $member_title . ' - Profile Picture',
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert the attachment
        $attachment_id = wp_insert_attachment($attachment, $new_file_path, $post_id);
        
        if (!is_wp_error($attachment_id) && $attachment_id > 0) {
            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);
            
            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            
            $result['success'] = true;
            $result['attachment_id'] = $attachment_id;
        } else {
            $result['error'] = 'Failed to create attachment';
        }
    } else {
        $result['error'] = 'Failed to copy image file';
    }
    
    return $result;
}

/**
 * Get import statistics
 */
function get_source_stats() {
    global $source_db_config, $errors;
    
    $stats = array(
        'total_members' => 0,
        'published' => 0,
        'draft' => 0,
        'with_images' => 0,
        'total_terms' => 0,
        'connection_status' => 'Not tested'
    );
    
    try {
        $source_db = new mysqli(
            $source_db_config['host'],
            $source_db_config['username'],
            $source_db_config['password'],
            $source_db_config['database'],
            null,
            $source_db_config['socket']
        );
        
        if (!$source_db->connect_error) {
            $stats['connection_status'] = 'Connected';
            
            // Count team members
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) as published,
                        SUM(CASE WHEN post_status = 'draft' THEN 1 ELSE 0 END) as draft
                      FROM {$source_db_config['prefix']}posts 
                      WHERE post_type = 'team_member'";
            
            $result = $source_db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_members'] = $row['total'];
                $stats['published'] = $row['published'];
                $stats['draft'] = $row['draft'];
            }
            
            // Count members with images
            $query = "SELECT COUNT(DISTINCT p.ID) as with_images
                      FROM {$source_db_config['prefix']}posts p
                      INNER JOIN {$source_db_config['prefix']}postmeta pm 
                        ON p.ID = pm.post_id
                      WHERE p.post_type = 'team_member'
                      AND pm.meta_key = '_thumbnail_id'
                      AND pm.meta_value != ''";
            
            $result = $source_db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['with_images'] = $row['with_images'];
            }
            
            // Count taxonomy terms
            $query = "SELECT COUNT(*) as total_terms
                      FROM {$source_db_config['prefix']}terms t
                      INNER JOIN {$source_db_config['prefix']}term_taxonomy tt 
                        ON t.term_id = tt.term_id
                      WHERE tt.taxonomy = 'member_status'";
            
            $result = $source_db->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['total_terms'] = $row['total_terms'];
            }
            
            $source_db->close();
        } else {
            $stats['connection_status'] = 'Failed: ' . $source_db->connect_error;
        }
    } catch (Exception $e) {
        $stats['connection_status'] = 'Error: ' . $e->getMessage();
    }
    
    return $stats;
}

$source_stats = get_source_stats();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Direct Database Import - Research Team Manager</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        .connection-box { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .connection-status { font-weight: bold; padding: 5px 10px; border-radius: 3px; display: inline-block; }
        .status-connected { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        button { background: #007cba; color: white; padding: 12px 30px; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; margin: 5px; }
        button:hover { background: #005a87; }
        button.test-btn { background: #6c757d; }
        button.test-btn:hover { background: #5a6268; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        code { background: #f8f9fa; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .db-config { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
        .db-config dt { font-weight: bold; display: inline-block; width: 120px; }
        .db-config dd { display: inline-block; margin: 0; font-family: monospace; }
    </style>
</head>
<body>
    <h1>Direct Database Import - Research Team Manager</h1>
    
    <div class="info">
        <strong>Direct Import from Source Database</strong>
        <p>This tool directly connects to the source database (matter.local) and imports team members into the current site (matter2.local).</p>
        <p>No intermediate export file is needed - data is transferred directly between databases.</p>
    </div>
    
    <h2>Source Database Configuration</h2>
    <div class="connection-box">
        <dl class="db-config">
            <dt>Host:</dt>
            <dd><?php echo htmlspecialchars($source_db_config['host']); ?></dd><br>
            <dt>Socket:</dt>
            <dd><?php echo htmlspecialchars($source_db_config['socket']); ?></dd><br>
            <dt>Database:</dt>
            <dd><?php echo htmlspecialchars($source_db_config['database']); ?></dd><br>
            <dt>Username:</dt>
            <dd><?php echo htmlspecialchars($source_db_config['username']); ?></dd><br>
            <dt>Table Prefix:</dt>
            <dd><?php echo htmlspecialchars($source_db_config['prefix']); ?></dd><br>
            <dt>Status:</dt>
            <dd>
                <span class="connection-status <?php echo strpos($source_stats['connection_status'], 'Connected') !== false ? 'status-connected' : 'status-failed'; ?>">
                    <?php echo htmlspecialchars($source_stats['connection_status']); ?>
                </span>
            </dd>
        </dl>
    </div>
    
    <?php if (strpos($source_stats['connection_status'], 'Connected') !== false): ?>
        <h2>Source Database Statistics</h2>
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $source_stats['total_members']; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $source_stats['published']; ?></div>
                <div class="stat-label">Published</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $source_stats['draft']; ?></div>
                <div class="stat-label">Drafts</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $source_stats['with_images']; ?></div>
                <div class="stat-label">With Images</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $source_stats['total_terms']; ?></div>
                <div class="stat-label">Status Terms</div>
            </div>
        </div>
    <?php endif; ?>
    
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
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['imported']; ?></div>
                    <div class="stat-label">Imported</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['updated']; ?></div>
                    <div class="stat-label">Updated</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['created_terms']; ?></div>
                    <div class="stat-label">Terms Created</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['processed_images']; ?></div>
                    <div class="stat-label">Images Processed</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['failed']; ?></div>
                    <div class="stat-label">Failed</div>
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
                            <td><?php echo $detail['success'] ? 'Success' : 'Failed: ' . $detail['error']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" onsubmit="return confirmImport();">
        <h2>Import Team Members</h2>
        
        <div class="warning">
            <strong>Before importing:</strong>
            <ul>
                <li>✅ Ensure you have a backup of your current database</li>
                <li>✅ Consider clearing existing team members first using the clear-team-members.php script</li>
                <li>✅ Verify the source database connection is working</li>
                <li>✅ Check that both sites are using the same Local environment</li>
            </ul>
        </div>
        
        <p>
            <button type="button" class="test-btn" onclick="testConnection()">Test Connection</button>
            <button type="submit" name="action" value="import">Import All Team Members</button>
        </p>
    </form>
    
    <div class="info">
        <h3>Field Mapping</h3>
        <p>The following fields will be mapped during import:</p>
        <table>
            <tr>
                <th>ACF Field (Source)</th>
                <th>RTM Field (Target)</th>
            </tr>
            <tr><td>name</td><td>_rtm_name</td></tr>
            <tr><td>short_description</td><td>_rtm_short_description</td></tr>
            <tr><td>long_description</td><td>_rtm_long_description</td></tr>
            <tr><td>email</td><td>_rtm_email</td></tr>
            <tr><td>phonenumber</td><td>_rtm_phone</td></tr>
            <tr><td>website</td><td>_rtm_website</td></tr>
            <tr><td>linkedin_url</td><td>_rtm_linkedin_url</td></tr>
            <tr><td>google_scholar_url</td><td>_rtm_google_scholar_url</td></tr>
            <tr><td>researchgate_url</td><td>_rtm_researchgate_url</td></tr>
            <tr><td>position</td><td>_rtm_position</td></tr>
            <tr><td>profile_picture / _thumbnail_id</td><td>Featured Image</td></tr>
            <tr><td>member_status (taxonomy)</td><td>member_status (taxonomy) + _rtm_member_status</td></tr>
        </table>
    </div>
    
    <script>
        function testConnection() {
            fetch('?action=test_connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_connection'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message + '\n\nTeam members found: ' + data.team_members_found);
                } else {
                    alert('❌ Connection failed:\n\n' + data.error);
                }
            })
            .catch(error => {
                alert('Error testing connection: ' + error);
            });
        }
        
        function confirmImport() {
            return confirm('Are you sure you want to import all team members from the source database?\n\nThis will create or update team members on the current site.');
        }
    </script>
    
</body>
</html>