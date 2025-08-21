<?php
/**
 * Publications Management for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Publications {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rtm_publications';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_rtm_sync_publications', array($this, 'sync_publications'));
        add_action('wp_ajax_rtm_delete_publication', array($this, 'delete_publication'));
        add_action('rtm_daily_sync', array($this, 'scheduled_sync'));
        
        add_shortcode('rtm_publications', array($this, 'publications_shortcode'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=team_member',
            __('Publications', 'research-team-manager'),
            __('Publications', 'research-team-manager'),
            'manage_options',
            'rtm-publications',
            array($this, 'publications_page')
        );
    }
    
    public function publications_page() {
        if (isset($_POST['sync_publications'])) {
            $this->sync_publications();
        }
        
        $publications = $this->get_publications();
        $total_publications = count($publications);
        $total_citations = array_sum(array_column($publications, 'citations'));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Research Publications', 'research-team-manager'); ?></h1>
            
            <div class="rtm-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="rtm-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php echo number_format($total_publications); ?></h3>
                    <p style="margin: 0; color: #646970;"><?php _e('Total Publications', 'research-team-manager'); ?></p>
                </div>
                <div class="rtm-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php echo number_format($total_citations); ?></h3>
                    <p style="margin: 0; color: #646970;"><?php _e('Total Citations', 'research-team-manager'); ?></p>
                </div>
            </div>
            
            <form method="post" style="margin: 20px 0;">
                <?php wp_nonce_field('rtm_sync_publications', 'rtm_sync_nonce'); ?>
                <p>
                    <input type="submit" name="sync_publications" class="button button-primary" value="<?php _e('Sync Publications from Google Scholar', 'research-team-manager'); ?>" />
                    <span class="description" style="margin-left: 10px;"><?php _e('This will fetch the latest publications from the configured Google Scholar profile.', 'research-team-manager'); ?></span>
                </p>
            </form>
            
            <div class="rtm-publications-table">
                <h2><?php _e('Recent Publications', 'research-team-manager'); ?></h2>
                
                <?php if (empty($publications)): ?>
                    <p><?php _e('No publications found. Click "Sync Publications" to fetch from Google Scholar.', 'research-team-manager'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Title', 'research-team-manager'); ?></th>
                                <th scope="col"><?php _e('Authors', 'research-team-manager'); ?></th>
                                <th scope="col"><?php _e('Journal', 'research-team-manager'); ?></th>
                                <th scope="col"><?php _e('Year', 'research-team-manager'); ?></th>
                                <th scope="col"><?php _e('Citations', 'research-team-manager'); ?></th>
                                <th scope="col"><?php _e('Actions', 'research-team-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($publications as $pub): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php if ($pub['url']): ?>
                                                <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                                            <?php else: ?>
                                                <?php echo esc_html($pub['title']); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($pub['authors']); ?></td>
                                    <td><?php echo esc_html($pub['journal']); ?></td>
                                    <td><?php echo esc_html($pub['year']); ?></td>
                                    <td><?php echo esc_html($pub['citations']); ?></td>
                                    <td>
                                        <button class="button button-small rtm-delete-pub" data-id="<?php echo esc_attr($pub['id']); ?>" data-nonce="<?php echo wp_create_nonce('rtm_delete_pub_' . $pub['id']); ?>">
                                            <?php _e('Delete', 'research-team-manager'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.rtm-delete-pub').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php _e('Are you sure you want to delete this publication?', 'research-team-manager'); ?>')) {
                    return;
                }
                
                var button = $(this);
                var pubId = button.data('id');
                var nonce = button.data('nonce');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rtm_delete_publication',
                        publication_id: pubId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut();
                        } else {
                            alert('<?php _e('Error deleting publication.', 'research-team-manager'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function sync_publications() {
        if (!check_ajax_referer('rtm_sync_publications', 'rtm_sync_nonce', false) && !check_admin_referer('rtm_sync_publications', 'rtm_sync_nonce')) {
            if (wp_doing_ajax()) {
                wp_die('Security check failed');
            }
            return;
        }
        
        $scholar_scraper = new RTM_Scholar_Scraper();
        $publications = $scholar_scraper->fetch_publications('m0_aWlQAAAAJ');
        
        if ($publications && is_array($publications)) {
            foreach ($publications as $pub) {
                $this->save_publication($pub);
            }
            
            if (wp_doing_ajax()) {
                wp_send_json_success(array('message' => 'Publications synced successfully'));
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Publications synced successfully!', 'research-team-manager') . '</p></div>';
                });
            }
        } else {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Failed to fetch publications'));
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to sync publications. Please try again later.', 'research-team-manager') . '</p></div>';
                });
            }
        }
    }
    
    public function delete_publication() {
        if (!wp_doing_ajax()) {
            return;
        }
        
        $pub_id = intval($_POST['publication_id']);
        $nonce = sanitize_text_field($_POST['nonce']);
        
        if (!wp_verify_nonce($nonce, 'rtm_delete_pub_' . $pub_id)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wpdb;
        $result = $wpdb->delete($this->table_name, array('id' => $pub_id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Publication deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete publication'));
        }
    }
    
    public function save_publication($publication_data) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE google_scholar_id = %s",
            $publication_data['google_scholar_id']
        ));
        
        $data = array(
            'title' => sanitize_text_field($publication_data['title']),
            'authors' => sanitize_text_field($publication_data['authors']),
            'journal' => sanitize_text_field($publication_data['journal']),
            'year' => intval($publication_data['year']),
            'citations' => intval($publication_data['citations']),
            'url' => esc_url_raw($publication_data['url']),
            'google_scholar_id' => sanitize_text_field($publication_data['google_scholar_id']),
            'abstract' => sanitize_textarea_field($publication_data['abstract']),
        );
        
        if ($existing) {
            $wpdb->update($this->table_name, $data, array('id' => $existing->id));
        } else {
            $wpdb->insert($this->table_name, $data);
        }
    }
    
    public function get_publications($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY year DESC, citations DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    public function publications_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_citations' => 'true',
            'show_abstract' => 'false',
            'group_by_year' => 'false',
        ), $atts);
        
        $publications = $this->get_publications(intval($atts['limit']));
        
        if (empty($publications)) {
            return '<p>' . __('No publications available.', 'research-team-manager') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="rtm-publications-list">
            <?php if ($atts['group_by_year'] === 'true'): ?>
                <?php
                $grouped = array();
                foreach ($publications as $pub) {
                    $grouped[$pub['year']][] = $pub;
                }
                krsort($grouped);
                ?>
                <?php foreach ($grouped as $year => $year_pubs): ?>
                    <div class="rtm-publications-year">
                        <h3 class="rtm-year-heading"><?php echo esc_html($year); ?></h3>
                        <?php foreach ($year_pubs as $pub): ?>
                            <?php $this->render_publication($pub, $atts); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($publications as $pub): ?>
                    <?php $this->render_publication($pub, $atts); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_publication($pub, $atts) {
        ?>
        <div class="rtm-publication-item">
            <div class="rtm-publication-title">
                <?php if ($pub['url']): ?>
                    <a href="<?php echo esc_url($pub['url']); ?>" target="_blank"><?php echo esc_html($pub['title']); ?></a>
                <?php else: ?>
                    <?php echo esc_html($pub['title']); ?>
                <?php endif; ?>
            </div>
            <div class="rtm-publication-authors"><?php echo esc_html($pub['authors']); ?></div>
            <?php if ($pub['journal']): ?>
                <div class="rtm-publication-journal"><em><?php echo esc_html($pub['journal']); ?></em> (<?php echo esc_html($pub['year']); ?>)</div>
            <?php else: ?>
                <div class="rtm-publication-year"><?php echo esc_html($pub['year']); ?></div>
            <?php endif; ?>
            <?php if ($atts['show_citations'] === 'true' && $pub['citations'] > 0): ?>
                <div class="rtm-publication-citations"><?php printf(__('Cited by %d', 'research-team-manager'), $pub['citations']); ?></div>
            <?php endif; ?>
            <?php if ($atts['show_abstract'] === 'true' && $pub['abstract']): ?>
                <div class="rtm-publication-abstract">
                    <details>
                        <summary><?php _e('Abstract', 'research-team-manager'); ?></summary>
                        <p><?php echo esc_html($pub['abstract']); ?></p>
                    </details>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function scheduled_sync() {
        $this->sync_publications();
    }
}