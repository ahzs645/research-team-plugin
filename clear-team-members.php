<?php
/**
 * Clear Team Members Script for Research Team Manager Plugin
 * 
 * This script removes all existing team members from the database
 * before importing new ones. Use with caution!
 * 
 * Place this file in the Research Team Manager plugin directory
 * and run it via: http://yoursite.com/wp-content/plugins/research-team-manager/clear-team-members.php
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

// Handle the clear action
$clear_results = array();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_clear'])) {
    if ($_POST['confirm_clear'] === 'DELETE_ALL_TEAM_MEMBERS') {
        $clear_results = clear_all_team_members();
    } else {
        $errors[] = 'Invalid confirmation code. Please type exactly: DELETE_ALL_TEAM_MEMBERS';
    }
}

/**
 * Clear all team members from the database
 */
function clear_all_team_members() {
    global $wpdb;
    
    $results = array(
        'members_deleted' => 0,
        'attachments_deleted' => 0,
        'terms_deleted' => 0,
        'meta_deleted' => 0,
        'details' => array()
    );
    
    // Get all team members
    $args = array(
        'post_type' => 'team_member',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'private', 'trash'),
        'fields' => 'ids'
    );
    
    $team_member_ids = get_posts($args);
    
    foreach ($team_member_ids as $member_id) {
        // Get member title for logging
        $member_title = get_the_title($member_id);
        
        // Get attached images to delete
        $thumbnail_id = get_post_thumbnail_id($member_id);
        
        // Delete all post meta
        $deleted_meta = $wpdb->delete(
            $wpdb->postmeta,
            array('post_id' => $member_id),
            array('%d')
        );
        
        if ($deleted_meta) {
            $results['meta_deleted'] += $deleted_meta;
        }
        
        // Delete the post
        $deleted = wp_delete_post($member_id, true); // true = force delete (skip trash)
        
        if ($deleted) {
            $results['members_deleted']++;
            
            // Delete the featured image if it exists
            if ($thumbnail_id) {
                $attachment_deleted = wp_delete_attachment($thumbnail_id, true);
                if ($attachment_deleted) {
                    $results['attachments_deleted']++;
                }
            }
            
            $results['details'][] = array(
                'id' => $member_id,
                'title' => $member_title,
                'status' => 'deleted',
                'attachment_deleted' => ($thumbnail_id && $attachment_deleted)
            );
        } else {
            $results['details'][] = array(
                'id' => $member_id,
                'title' => $member_title,
                'status' => 'failed'
            );
        }
    }
    
    // Optionally clear member_status taxonomy terms
    if (isset($_POST['clear_terms']) && $_POST['clear_terms'] === 'yes') {
        $terms = get_terms(array(
            'taxonomy' => 'member_status',
            'hide_empty' => false,
            'fields' => 'ids'
        ));
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                $deleted = wp_delete_term($term_id, 'member_status');
                if ($deleted && !is_wp_error($deleted)) {
                    $results['terms_deleted']++;
                }
            }
        }
    }
    
    // Clear any orphaned attachments in team-member-export folder
    if (isset($_POST['clear_export_folder']) && $_POST['clear_export_folder'] === 'yes') {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/team-member-export';
        
        if (is_dir($export_dir)) {
            // Delete the directory and its contents
            delete_directory_recursive($export_dir);
            $results['export_folder_cleared'] = true;
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    return $results;
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
        'trashed' => 0,
        'total_terms' => 0,
        'total_attachments' => 0
    );
    
    // Count team members
    $post_counts = wp_count_posts('team_member');
    $stats['total_members'] = $post_counts->publish + $post_counts->draft + $post_counts->private + $post_counts->trash;
    $stats['published'] = $post_counts->publish;
    $stats['draft'] = $post_counts->draft;
    $stats['trashed'] = $post_counts->trash;
    
    // Count taxonomy terms
    $terms = get_terms(array(
        'taxonomy' => 'member_status',
        'hide_empty' => false
    ));
    
    if (!is_wp_error($terms)) {
        $stats['total_terms'] = count($terms);
    }
    
    // Count attachments (featured images)
    $args = array(
        'post_type' => 'team_member',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids'
    );
    
    $team_member_ids = get_posts($args);
    foreach ($team_member_ids as $member_id) {
        if (has_post_thumbnail($member_id)) {
            $stats['total_attachments']++;
        }
    }
    
    return $stats;
}

$current_stats = get_current_stats();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Clear Team Members - Research Team Manager</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border: 2px solid #ffc107; border-radius: 5px; margin: 10px 0; }
        .danger { background: #f8d7da; color: #721c24; padding: 15px; border: 2px solid #dc3545; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #dc3545; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        form { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; border: 2px solid #dc3545; border-radius: 3px; font-size: 16px; }
        input[type="checkbox"] { margin-right: 10px; }
        .checkbox-group { margin: 15px 0; padding: 10px; background: white; border-radius: 3px; }
        button { background: #dc3545; color: white; padding: 12px 30px; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
        button:hover { background: #c82333; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .confirmation-text { font-family: monospace; background: #fff; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        h2 { color: #dc3545; }
    </style>
</head>
<body>
    <h1>⚠️ Clear Team Members - Research Team Manager</h1>
    
    <div class="danger">
        <h2>⚠️ WARNING: DESTRUCTIVE ACTION</h2>
        <p><strong>This action will permanently delete ALL team members from your database!</strong></p>
        <p>This includes:</p>
        <ul>
            <li>All team member posts</li>
            <li>All associated metadata</li>
            <li>Featured images (if selected)</li>
            <li>Taxonomy terms (if selected)</li>
        </ul>
        <p><strong>This action CANNOT be undone!</strong> Please ensure you have a backup before proceeding.</p>
    </div>
    
    <h2>Current Team Members Statistics</h2>
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['total_members']; ?></div>
            <div class="stat-label">Total Members</div>
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
            <div class="stat-number"><?php echo $current_stats['trashed']; ?></div>
            <div class="stat-label">Trashed</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['total_terms']; ?></div>
            <div class="stat-label">Status Terms</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $current_stats['total_attachments']; ?></div>
            <div class="stat-label">Images</div>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Errors occurred:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($clear_results)): ?>
        <div class="success">
            <h2>Clear Operation Completed</h2>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $clear_results['members_deleted']; ?></div>
                    <div class="stat-label">Members Deleted</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $clear_results['attachments_deleted']; ?></div>
                    <div class="stat-label">Images Deleted</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $clear_results['meta_deleted']; ?></div>
                    <div class="stat-label">Meta Entries Deleted</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $clear_results['terms_deleted']; ?></div>
                    <div class="stat-label">Terms Deleted</div>
                </div>
            </div>
            
            <?php if (!empty($clear_results['details'])): ?>
                <h3>Deletion Details</h3>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Member Name</th>
                        <th>Status</th>
                        <th>Image Deleted</th>
                    </tr>
                    <?php foreach ($clear_results['details'] as $detail): ?>
                        <tr>
                            <td><?php echo $detail['id']; ?></td>
                            <td><?php echo htmlspecialchars($detail['title']); ?></td>
                            <td><?php echo ucfirst($detail['status']); ?></td>
                            <td><?php echo isset($detail['attachment_deleted']) && $detail['attachment_deleted'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            
            <p><strong>All team members have been successfully deleted from the database.</strong></p>
        </div>
    <?php endif; ?>
    
    <?php if ($current_stats['total_members'] > 0): ?>
        <form method="post" onsubmit="return confirmClear();">
            <h2>Clear All Team Members</h2>
            
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="clear_terms" value="yes">
                    Also delete all member status taxonomy terms
                </label>
            </div>
            
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="clear_export_folder" value="yes">
                    Clear team-member-export folder (if exists)
                </label>
            </div>
            
            <p><strong>To confirm this action, please type exactly:</strong></p>
            <p class="confirmation-text">DELETE_ALL_TEAM_MEMBERS</p>
            
            <input type="text" 
                   name="confirm_clear" 
                   placeholder="Type the confirmation text here" 
                   required
                   pattern="DELETE_ALL_TEAM_MEMBERS"
                   title="Please type exactly: DELETE_ALL_TEAM_MEMBERS">
            
            <p style="margin-top: 20px;">
                <button type="submit">🗑️ Delete All Team Members</button>
            </p>
        </form>
    <?php else: ?>
        <div class="info">
            <h2>No Team Members Found</h2>
            <p>There are currently no team members in the database to delete.</p>
            <p>You can now proceed to import new team members using the import script.</p>
        </div>
    <?php endif; ?>
    
    <div class="warning">
        <h3>Before You Clear:</h3>
        <ul>
            <li>✅ Make sure you have exported all team members from the old site</li>
            <li>✅ Ensure you have a complete database backup</li>
            <li>✅ Verify that the export file contains all necessary data</li>
            <li>✅ Confirm that you really want to delete all existing team members</li>
        </ul>
    </div>
    
    <script>
        function confirmClear() {
            const confirmText = document.querySelector('input[name="confirm_clear"]').value;
            
            if (confirmText !== 'DELETE_ALL_TEAM_MEMBERS') {
                alert('Please type the confirmation text exactly as shown: DELETE_ALL_TEAM_MEMBERS');
                return false;
            }
            
            return confirm('⚠️ FINAL WARNING ⚠️\n\nThis will PERMANENTLY DELETE all team members!\n\nAre you absolutely sure you want to proceed?');
        }
    </script>
    
</body>
</html>