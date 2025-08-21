<?php
/**
 * Google Scholar Scraper for Research Team Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Scholar_Scraper {
    
    private $base_url = 'https://scholar.google.com/citations';
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=team_member',
            __('Scholar Settings', 'research-team-manager'),
            __('Scholar Settings', 'research-team-manager'),
            'manage_options',
            'rtm-scholar-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('rtm_scholar_settings', 'rtm_google_scholar_user_id');
        register_setting('rtm_scholar_settings', 'rtm_enable_auto_sync');
        register_setting('rtm_scholar_settings', 'rtm_sync_frequency');
    }
    
    public function settings_page() {
        $user_id = get_option('rtm_google_scholar_user_id', '');
        $auto_sync = get_option('rtm_enable_auto_sync', false);
        $sync_frequency = get_option('rtm_sync_frequency', 'daily');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Google Scholar Settings', 'research-team-manager'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('rtm_scholar_settings'); ?>
                <?php do_settings_sections('rtm_scholar_settings'); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="rtm_google_scholar_user_id"><?php _e('Google Scholar User ID', 'research-team-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="rtm_google_scholar_user_id" name="rtm_google_scholar_user_id" value="<?php echo esc_attr($user_id); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Enter the user ID from Google Scholar profile URL (e.g., m0_aWlQAAAAJ from https://scholar.google.ca/citations?user=m0_aWlQAAAAJ)', 'research-team-manager'); ?>
                            </p>
                            <?php if (!empty($user_id)): ?>
                                <p>
                                    <a href="<?php echo esc_url($this->base_url . '?user=' . $user_id); ?>" target="_blank" class="button button-secondary">
                                        <?php _e('View Profile', 'research-team-manager'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="rtm_enable_auto_sync"><?php _e('Enable Automatic Sync', 'research-team-manager'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="rtm_enable_auto_sync" name="rtm_enable_auto_sync" value="1" <?php checked($auto_sync, 1); ?> />
                            <label for="rtm_enable_auto_sync"><?php _e('Automatically sync publications on schedule', 'research-team-manager'); ?></label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="rtm_sync_frequency"><?php _e('Sync Frequency', 'research-team-manager'); ?></label>
                        </th>
                        <td>
                            <select id="rtm_sync_frequency" name="rtm_sync_frequency">
                                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>><?php _e('Daily', 'research-team-manager'); ?></option>
                                <option value="weekly" <?php selected($sync_frequency, 'weekly'); ?>><?php _e('Weekly', 'research-team-manager'); ?></option>
                                <option value="monthly" <?php selected($sync_frequency, 'monthly'); ?>><?php _e('Monthly', 'research-team-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="rtm-test-connection" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                <h3><?php _e('Test Connection', 'research-team-manager'); ?></h3>
                <p><?php _e('Test the Google Scholar connection and preview publications:', 'research-team-manager'); ?></p>
                <button type="button" id="rtm-test-scholar" class="button button-secondary" data-user-id="<?php echo esc_attr($user_id); ?>">
                    <?php _e('Test Scholar Connection', 'research-team-manager'); ?>
                </button>
                <div id="rtm-test-results" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#rtm-test-scholar').on('click', function() {
                var button = $(this);
                var userId = button.data('user-id');
                var resultsDiv = $('#rtm-test-results');
                
                if (!userId) {
                    resultsDiv.html('<div class="notice notice-error"><p><?php _e('Please enter a Google Scholar User ID first.', 'research-team-manager'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'research-team-manager'); ?>');
                resultsDiv.html('<div class="notice notice-info"><p><?php _e('Testing connection to Google Scholar...', 'research-team-manager'); ?></p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rtm_test_scholar_connection',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('rtm_test_scholar'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success"><p><?php _e('Connection successful!', 'research-team-manager'); ?></p></div>';
                            if (response.data.publications && response.data.publications.length > 0) {
                                html += '<h4><?php _e('Recent Publications:', 'research-team-manager'); ?></h4>';
                                html += '<ul>';
                                response.data.publications.slice(0, 5).forEach(function(pub) {
                                    html += '<li><strong>' + pub.title + '</strong> (' + pub.year + ') - ' + pub.citations + ' citations</li>';
                                });
                                html += '</ul>';
                            }
                            resultsDiv.html(html);
                        } else {
                            resultsDiv.html('<div class="notice notice-error"><p><?php _e('Connection failed:', 'research-team-manager'); ?> ' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultsDiv.html('<div class="notice notice-error"><p><?php _e('Error testing connection.', 'research-team-manager'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Scholar Connection', 'research-team-manager'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function fetch_publications($user_id = null) {
        if (!$user_id) {
            $user_id = get_option('rtm_google_scholar_user_id', '');
        }
        
        if (empty($user_id)) {
            return false;
        }
        
        $url = $this->base_url . '?user=' . $user_id . '&hl=en&cstart=0&pagesize=100';
        
        $args = array(
            'timeout' => 30,
            'user-agent' => $this->user_agent,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ),
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('RTM Scholar Scraper Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('RTM Scholar Scraper HTTP Error: ' . $status_code);
            return false;
        }
        
        return $this->parse_publications_html($body, $user_id);
    }
    
    private function parse_publications_html($html, $user_id) {
        if (empty($html)) {
            return false;
        }
        
        $publications = array();
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);
        
        $publication_rows = $xpath->query("//tr[@class='gsc_a_tr']");
        
        foreach ($publication_rows as $row) {
            $title_element = $xpath->query(".//td[@class='gsc_a_t']/a", $row)->item(0);
            $authors_element = $xpath->query(".//td[@class='gsc_a_t']/div[@class='gs_gray'][1]", $row)->item(0);
            $journal_element = $xpath->query(".//td[@class='gsc_a_t']/div[@class='gs_gray'][2]", $row)->item(0);
            $citations_element = $xpath->query(".//td[@class='gsc_a_c']/a", $row)->item(0);
            $year_element = $xpath->query(".//td[@class='gsc_a_y']/span", $row)->item(0);
            
            if ($title_element) {
                $title = trim($title_element->textContent);
                $publication_url = '';
                
                if ($title_element->hasAttribute('href')) {
                    $publication_url = 'https://scholar.google.com' . $title_element->getAttribute('href');
                }
                
                $authors = $authors_element ? trim($authors_element->textContent) : '';
                $journal = $journal_element ? trim($journal_element->textContent) : '';
                $citations = $citations_element ? intval(trim($citations_element->textContent)) : 0;
                $year = $year_element ? intval(trim($year_element->textContent)) : 0;
                
                if (!empty($title)) {
                    $publications[] = array(
                        'title' => $title,
                        'authors' => $authors,
                        'journal' => $journal,
                        'year' => $year,
                        'citations' => $citations,
                        'url' => $publication_url,
                        'google_scholar_id' => md5($user_id . '_' . $title . '_' . $year),
                        'abstract' => '',
                    );
                }
            }
        }
        
        return $publications;
    }
    
    public function test_connection() {
        if (!wp_doing_ajax()) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'rtm_test_scholar')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $user_id = sanitize_text_field($_POST['user_id']);
        
        if (empty($user_id)) {
            wp_send_json_error(array('message' => 'User ID is required'));
        }
        
        $publications = $this->fetch_publications($user_id);
        
        if ($publications === false) {
            wp_send_json_error(array('message' => 'Failed to fetch publications from Google Scholar'));
        }
        
        wp_send_json_success(array(
            'message' => 'Connection successful',
            'publications' => $publications,
            'count' => count($publications)
        ));
    }
}

add_action('wp_ajax_rtm_test_scholar_connection', function() {
    $scraper = new RTM_Scholar_Scraper();
    $scraper->test_connection();
});