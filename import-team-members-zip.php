<?php
/**
 * Team Members ZIP Import Script for Research Team Manager Plugin
 * 
 * This script imports team member data and images from a ZIP package
 * created by the export-team-members-zip.php script.
 * 
 * Place this file in the Research Team Manager plugin directory
 * and run it via: http://matter2.local/wp-content/plugins/research-team-manager/import-team-members-zip.php
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

// Handle file upload and import
$import_results = array();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $import_results = handle_zip_import($_FILES['import_file']);
}

/**
 * Handle the ZIP import process
 */
function handle_zip_import($uploaded_file) {
    global $errors;
    
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . $uploaded_file['error'];
        return array();
    }
    
    // Check if file is a ZIP
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
    finfo_close($finfo);
    
    if ($mime_type !== 'application/zip') {
        $errors[] = 'Uploaded file is not a valid ZIP file';
        return array();
    }
    
    // Create temp directory for extraction
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/team-import-temp-' . uniqid();
    
    if (!wp_mkdir_p($temp_dir)) {
        $errors[] = 'Failed to create temporary directory';
        return array();
    }
    
    // Extract ZIP file
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($uploaded_file['tmp_name']) === TRUE) {
            $zip->extractTo($temp_dir);
            $zip->close();
        } else {
            $errors[] = 'Failed to extract ZIP file';
            delete_directory_recursive($temp_dir);
            return array();
        }
    } else {
        $errors[] = 'ZipArchive class not available';
        delete_directory_recursive($temp_dir);
        return array();
    }
    
    // Check for required data file
    $json_file = $temp_dir . '/team-members-data.json';
    if (!file_exists($json_file)) {
        $errors[] = 'Data file not found in ZIP package';
        delete_directory_recursive($temp_dir);
        return array();
    }
    
    // Read and parse JSON data
    $json_content = file_get_contents($json_file);
    $import_data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = 'Invalid JSON format: ' . json_last_error_msg();
        delete_directory_recursive($temp_dir);
        return array();
    }
    
    if (!isset($import_data['members']) || !is_array($import_data['members'])) {
        $errors[] = 'Invalid import format: missing or invalid members data';
        delete_directory_recursive($temp_dir);
        return array();
    }
    
    // Process the import
    $results = process_zip_import($import_data, $temp_dir);
    
    // Clean up temp directory
    delete_directory_recursive($temp_dir);
    
    return $results;
}

/**
 * Process the imported members data with images
 */
function process_zip_import($import_data, $temp_dir) {
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
    
    // Process each member
    foreach ($import_data['members'] as $member_data) {
        try {
            $result = import_single_member_from_zip($member_data, $temp_dir);
            
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
 * Import a single member from ZIP package
 */
function import_single_member_from_zip($member_data, $temp_dir) {
    $result = array(
        'success' => false,
        'action' => '',
        'post_id' => 0,
        'image_processed' => false,
        'error' => '',
        'member_title' => $member_data['title'] ?? 'Unknown'
    );
    
    // Check if member already exists
    $existing_post = get_page_by_title($member_data['title'], OBJECT, 'team_member');
    
    $post_data = array(
        'post_title' => $member_data['title'],
        'post_content' => $member_data['content'] ?? '',
        'post_excerpt' => $member_data['excerpt'] ?? '',
        'post_type' => 'team_member',
        'post_status' => $member_data['status'] ?? 'publish',
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
    
    // Import custom fields with field mapping
    $field_mappings = array(
        'email' => '_rtm_email',
        'phonenumber' => '_rtm_phone',
        'website' => '_rtm_website',
        'long_description' => '_rtm_long_description',
        'short_description' => '_rtm_short_description',
        'linkedin_url' => '_rtm_linkedin_url',
        'google_scholar_url' => '_rtm_google_scholar_url',
        'researchgate_url' => '_rtm_researchgate_url',
        'position' => '_rtm_position'
    );
    
    foreach ($field_mappings as $source_field => $target_field) {
        if (isset($member_data[$source_field]) && $member_data[$source_field] !== '') {
            update_post_meta($post_id, $target_field, $member_data[$source_field]);
        }
    }
    
    // Process member status
    $member_status_value = 'alumni'; // Default
    $position = $member_data['position'] ?? '';
    $member_type = 'Graduate Student'; // Default
    
    if (isset($member_data['member_status']) && !empty($member_data['member_status'])) {
        $term_slugs = array();
        
        foreach ($member_data['member_status'] as $status_data) {
            $term_slugs[] = $status_data['slug'];
            
            $status_slug = strtolower($status_data['slug']);
            $status_name = strtolower($status_data['name']);
            
            // Determine member status
            if (strpos($status_slug, 'alumni') !== false || strpos($status_name, 'alumni') !== false) {
                $member_status_value = 'alumni';
                if (empty($position)) $position = 'Alumni Member';
            } else {
                $member_status_value = 'current';
                if (empty($position)) {
                    // Set position based on status
                    if (strpos($status_slug, 'faculty') !== false) {
                        $position = 'Faculty';
                        $member_type = 'Faculty';
                    } elseif (strpos($status_slug, 'phd') !== false) {
                        $position = 'PhD Student';
                        $member_type = 'Graduate Student';
                    } elseif (strpos($status_slug, 'postdoc') !== false) {
                        $position = 'Postdoctoral Researcher';
                        $member_type = 'Postdoc';
                    } elseif (strpos($status_slug, 'undergraduate') !== false) {
                        $position = 'Undergraduate Student';
                        $member_type = 'Undergraduate Student';
                    } elseif (strpos($status_slug, 'visiting') !== false) {
                        $position = 'Visiting Scholar';
                        $member_type = 'Visiting Scholar';
                    } elseif (strpos($status_slug, 'honorary') !== false) {
                        $position = 'Honorary Member';
                        $member_type = 'Honorary Member';
                    } else {
                        $position = 'Team Member';
                    }
                }
            }
        }
        
        // Set taxonomy terms
        wp_set_post_terms($post_id, $term_slugs, 'member_status');
    }
    
    // Save member status and related fields
    update_post_meta($post_id, '_rtm_member_status', $member_status_value);
    update_post_meta($post_id, '_rtm_position', $position);
    update_post_meta($post_id, '_rtm_member_type', $member_type);
    update_post_meta($post_id, '_rtm_is_current', ($member_status_value === 'current') ? '1' : '0');
    update_post_meta($post_id, '_rtm_order', $member_data['id'] ?? $post_id);
    
    // Import images
    $images_processed = 0;
    
    // Import featured image
    if (!empty($member_data['featured_image'])) {
        $image_path = $temp_dir . '/images/' . $member_data['featured_image'];
        if (file_exists($image_path)) {
            $attachment_id = import_image_from_file($image_path, $post_id, $member_data['title'] . ' - Featured');
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
                $images_processed++;
            }
        }
    }
    
    // Import profile picture if different from featured image
    if (!empty($member_data['profile_picture']) && $member_data['profile_picture'] !== $member_data['featured_image']) {
        $image_path = $temp_dir . '/images/' . $member_data['profile_picture'];
        if (file_exists($image_path)) {
            $attachment_id = import_image_from_file($image_path, $post_id, $member_data['title'] . ' - Profile');
            if ($attachment_id && !has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
            update_post_meta($post_id, '_rtm_profile_picture_id', $attachment_id);
            $images_processed++;
        }
    }
    
    if ($images_processed > 0) {
        $result['image_processed'] = true;
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
 * Import image from file
 */
function import_image_from_file($file_path, $post_id, $title = '') {
    if (!file_exists($file_path)) {
        return false;
    }
    
    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    
    // Create unique filename
    $filename = basename($file_path);
    // Remove any prefix added during export
    $filename = preg_replace('/^(featured|profile)_\d+_/', '', $filename);
    $new_filename = wp_unique_filename($upload_dir['path'], $filename);
    $new_file_path = $upload_dir['path'] . '/' . $new_filename;
    
    // Copy file to uploads directory
    if (!copy($file_path, $new_file_path)) {
        return false;
    }
    
    // Get file type
    $filetype = wp_check_filetype($new_filename, null);
    
    // Prepare attachment data
    $attachment = array(
        'guid' => $upload_dir['url'] . '/' . $new_filename,
        'post_mime_type' => $filetype['type'],
        'post_title' => $title ?: pathinfo($filename, PATHINFO_FILENAME),
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
        
        return $attachment_id;
    }
    
    return false;
}

/**
 * Recursively delete a directory
 */
function delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Get current statistics
 */
function get_current_stats() {
    $stats = array(
        'total_members' => 0,
        'published' => 0,
        'draft' => 0,
        'with_images' => 0
    );
    
    $post_counts = wp_count_posts('team_member');
    $stats['total_members'] = $post_counts->publish + $post_counts->draft + $post_counts->private;
    $stats['published'] = $post_counts->publish;
    $stats['draft'] = $post_counts->draft;
    
    // Count members with images
    $args = array(
        'post_type' => 'team_member',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids'
    );
    
    $team_member_ids = get_posts($args);
    foreach ($team_member_ids as $member_id) {
        if (has_post_thumbnail($member_id)) {
            $stats['with_images']++;
        }
    }
    
    return $stats;
}

$current_stats = get_current_stats();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Team Members ZIP Import - Research Team Manager</title>
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input[type="file"] { 
            padding: 10px;
            border: 2px dashed #007cba;
            border-radius: 5px;
            width: 100%;
            background: white;
            margin: 10px 0;
        }
        button { background: #007cba; color: white; padding: 12px 30px; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .feature-list { list-style: none; padding: 0; }
        .feature-list li { padding: 8px 0; padding-left: 25px; position: relative; }
        .feature-list li:before { content: "✓"; position: absolute; left: 0; color: #28a745; font-weight: bold; }
        .file-info { background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .progress { background: #e9ecef; height: 30px; border-radius: 5px; overflow: hidden; margin: 10px 0; display: none; }
        .progress-bar { background: #007cba; height: 100%; width: 0; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; }
    </style>
</head>
<body>
    <h1>📦 Team Members ZIP Import - Research Team Manager</h1>
    
    <div class="info">
        <strong>Import from ZIP Package</strong>
        <p>This tool imports team member data and images from a ZIP package created by the export tool.</p>
        <p>The import process will automatically extract and process all data and images.</p>
    </div>
    
    <h2>Current Database Statistics</h2>
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['total_members']; ?></div>
            <div class="stat-label">Current Members</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['published']; ?></div>
            <div class="stat-label">Published</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['draft']; ?></div>
            <div class="stat-label">Drafts</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['with_images']; ?></div>
            <div class="stat-label">With Images</div>
        </div>
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
            <h2>✅ Import Completed Successfully!</h2>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['total_members']; ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $import_results['imported']; ?></div>
                    <div class="stat-label">Created New</div>
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
                    <div class="stat-label">Images Imported</div>
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
                        <th>Image Imported</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($import_results['details'] as $detail): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['member_title']); ?></td>
                            <td><?php echo ucfirst($detail['action']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $detail['post_id'] . '&action=edit'); ?>" target="_blank">
                                    #<?php echo $detail['post_id']; ?>
                                </a>
                            </td>
                            <td><?php echo $detail['image_processed'] ? '✅ Yes' : '—'; ?></td>
                            <td><?php echo $detail['success'] ? '✅ Success' : '❌ Failed'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!class_exists('ZipArchive')): ?>
        <div class="error">
            <strong>ZipArchive Extension Not Available</strong>
            <p>The PHP ZipArchive extension is required to import ZIP packages. Please enable it in your PHP configuration.</p>
        </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" onsubmit="return validateForm();">
            <h2>Upload Team Members ZIP Package</h2>
            
            <div class="file-info">
                <strong>Package Requirements:</strong>
                <ul class="feature-list">
                    <li>Must be a valid ZIP file</li>
                    <li>Created by the export-team-members-zip.php script</li>
                    <li>Contains team-members-data.json file</li>
                    <li>Images should be in the images/ folder</li>
                    <li>Maximum file size: <?php echo ini_get('upload_max_filesize'); ?></li>
                </ul>
            </div>
            
            <input type="file" 
                   name="import_file" 
                   accept=".zip" 
                   required
                   id="import_file"
                   onchange="fileSelected(this)">
            
            <div id="file_selected" style="display: none;" class="file-info">
                <strong>Selected file:</strong> <span id="filename"></span><br>
                <strong>Size:</strong> <span id="filesize"></span>
            </div>
            
            <div class="progress" id="upload_progress">
                <div class="progress-bar" id="progress_bar">0%</div>
            </div>
            
            <p style="margin-top: 20px;">
                <button type="submit" id="import_button">📥 Import Team Members</button>
            </p>
        </form>
    <?php endif; ?>
    
    <div class="warning">
        <h3>Before Importing:</h3>
        <ul>
            <li>✅ Ensure you have a backup of your current database</li>
            <li>✅ Consider clearing existing team members first if you want a clean import</li>
            <li>✅ Verify the ZIP file is from the correct source site</li>
            <li>✅ Check available disk space for images</li>
        </ul>
        <p><strong>Note:</strong> Members with the same title will be updated rather than duplicated.</p>
    </div>
    
    <div class="info">
        <h3>Import Process:</h3>
        <ol>
            <li>Upload the ZIP package using the form above</li>
            <li>The script will extract and validate the package</li>
            <li>Team member data will be imported/updated</li>
            <li>Images will be uploaded and attached to members</li>
            <li>Taxonomy terms will be created if needed</li>
            <li>Review the import results</li>
        </ol>
    </div>
    
    <script>
        function fileSelected(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = (file.size / 1048576).toFixed(2); // Convert to MB
                
                document.getElementById('filename').textContent = file.name;
                document.getElementById('filesize').textContent = fileSize + ' MB';
                document.getElementById('file_selected').style.display = 'block';
                
                // Check file size
                const maxSize = <?php echo ini_get('upload_max_filesize'); ?>;
                const maxSizeMB = parseInt(maxSize) * (maxSize.includes('G') ? 1024 : 1);
                
                if (fileSize > maxSizeMB) {
                    alert('File size exceeds maximum upload size of ' + maxSize);
                    input.value = '';
                    document.getElementById('file_selected').style.display = 'none';
                }
            }
        }
        
        function validateForm() {
            const fileInput = document.getElementById('import_file');
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a ZIP file to import');
                return false;
            }
            
            const file = fileInput.files[0];
            if (!file.name.endsWith('.zip')) {
                alert('Please select a valid ZIP file');
                return false;
            }
            
            if (confirm('Are you ready to import the team members from this package?\n\nExisting members with the same title will be updated.')) {
                document.getElementById('import_button').disabled = true;
                document.getElementById('import_button').textContent = '⏳ Importing... Please wait';
                document.getElementById('upload_progress').style.display = 'block';
                
                // Simulate progress (actual progress would require AJAX)
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    if (progress <= 90) {
                        document.getElementById('progress_bar').style.width = progress + '%';
                        document.getElementById('progress_bar').textContent = progress + '%';
                    } else {
                        clearInterval(interval);
                    }
                }, 500);
                
                return true;
            }
            
            return false;
        }
    </script>
    
</body>
</html>