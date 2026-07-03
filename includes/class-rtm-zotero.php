<?php
/**
 * Zotero publications — read-only preview (proof of concept).
 *
 * Fetches a public Zotero group library and shows what a future sync WOULD import,
 * mapped to the wp_rtm_publications schema, with a dedup check against existing
 * rows. It NEVER writes to the publications table — this is a dry run only.
 *
 * Zotero Web API: https://www.zotero.org/support/dev/web_api/v3/start
 * Public groups (libraryReading = "all") need no API key.
 *
 * @package ResearchTeamManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Zotero {

    /** Default group to preview (ESHTA public group). */
    const DEFAULT_GROUP = 5544929;
    /** Scholarly item types worth importing (webpage/blog excluded by default). */
    const TYPES = array('journalArticle', 'conferencePaper', 'book', 'bookSection', 'report', 'preprint', 'thesis');
    /** Hard page cap so a runaway library can't hammer the request. */
    const MAX_PAGES = 15;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=rtm_team_member',
            __('Zotero Publications (Preview)', 'research-team-manager'),
            __('Zotero Preview', 'research-team-manager'),
            'manage_options',
            'rtm-zotero-preview',
            array($this, 'render_page')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Fetch (read-only)                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Fetch all top-level items of the given scholarly types, paginating the
     * Zotero API (max 100/request). Returns array on success or WP_Error.
     *
     * @return array|WP_Error { items, total, version, pages }
     */
    public function fetch($group_id, $types) {
        $group_id  = (int) $group_id;
        $type_expr = implode(' || ', array_map('sanitize_key', $types));
        $all       = array();
        $start     = 0;
        $limit     = 100;
        $total     = null;
        $version   = '';
        $pages     = 0;

        do {
            $url = add_query_arg(
                array(
                    'format'   => 'json',
                    'itemType' => $type_expr,
                    'limit'    => $limit,
                    'start'    => $start,
                ),
                'https://api.zotero.org/groups/' . $group_id . '/items/top'
            );

            $resp = wp_remote_get($url, array(
                'timeout' => 25,
                'headers' => array('Zotero-API-Version' => '3'),
            ));

            if (is_wp_error($resp)) {
                return $resp;
            }
            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code !== 200) {
                return new WP_Error('rtm_zotero_http', sprintf('Zotero returned HTTP %d.', $code));
            }
            if ($total === null) {
                $total   = (int) wp_remote_retrieve_header($resp, 'total-results');
                $version = wp_remote_retrieve_header($resp, 'last-modified-version');
            }

            $batch = json_decode(wp_remote_retrieve_body($resp), true);
            if (!is_array($batch) || empty($batch)) {
                break;
            }
            $all = array_merge($all, $batch);

            $start += $limit;
            $pages++;

            // Respect a short server-requested backoff between pages.
            $backoff = (int) wp_remote_retrieve_header($resp, 'backoff');
            if ($backoff > 0 && $backoff <= 5) {
                sleep($backoff);
            }
        } while ($total !== null && $start < $total && $pages < self::MAX_PAGES);

        return array('items' => $all, 'total' => $total, 'version' => $version, 'pages' => $pages);
    }

    /* ------------------------------------------------------------------ */
    /* Mapping + dedup                                                     */
    /* ------------------------------------------------------------------ */

    /** Map one Zotero item to the wp_rtm_publications shape. Citations are 0 by design. */
    public function map_item($item) {
        $d = isset($item['data']) ? $item['data'] : array();

        $names = array();
        foreach ((isset($d['creators']) ? $d['creators'] : array()) as $c) {
            $type = isset($c['creatorType']) ? $c['creatorType'] : '';
            if ($type !== 'author' && !isset($c['name'])) {
                continue; // skip editors/translators for the author line
            }
            if (isset($c['name'])) {
                $names[] = $c['name'];
            } else {
                $names[] = trim((isset($c['firstName']) ? $c['firstName'] : '') . ' ' . (isset($c['lastName']) ? $c['lastName'] : ''));
            }
        }

        $year = 0;
        if (!empty($d['date']) && preg_match('/\d{4}/', $d['date'], $m)) {
            $year = (int) $m[0];
        }

        $url = isset($d['url']) ? $d['url'] : '';
        if ($url === '' && !empty($d['DOI'])) {
            $url = 'https://doi.org/' . $d['DOI'];
        }

        $journal = '';
        foreach (array('publicationTitle', 'proceedingsTitle', 'bookTitle', 'publisher') as $k) {
            if (!empty($d[$k])) { $journal = $d[$k]; break; }
        }

        return array(
            'title'     => isset($d['title']) ? $d['title'] : '',
            'authors'   => implode(' and ', $names),
            'journal'   => $journal,
            'year'      => $year,
            'url'       => $url,
            'doi'       => isset($d['DOI']) ? $d['DOI'] : '',
            'abstract'  => isset($d['abstractNote']) ? $d['abstractNote'] : '',
            'citations' => 0,
            'item_type' => isset($d['itemType']) ? $d['itemType'] : '',
        );
    }

    /** Normalised title key used for dedup. */
    private function norm($title) {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $title));
    }

    /** Set of normalised titles already in wp_rtm_publications. */
    public function existing_titles() {
        global $wpdb;
        $table = $wpdb->prefix . 'rtm_publications';
        $index = array();
        // Read-only: only SELECT.
        $rows = $wpdb->get_col("SELECT title FROM {$table}");
        foreach ((array) $rows as $t) {
            $index[$this->norm($t)] = true;
        }
        return $index;
    }

    /* ------------------------------------------------------------------ */
    /* Admin page (read-only preview)                                      */
    /* ------------------------------------------------------------------ */

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'research-team-manager'));
        }

        $group = self::DEFAULT_GROUP;
        if (isset($_POST['rtm_zotero_group'])) {
            $group = (int) $_POST['rtm_zotero_group'];
        }
        $run = isset($_POST['rtm_zotero_preview']) && check_admin_referer('rtm_zotero_preview');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Zotero Publications — Preview', 'research-team-manager') . '</h1>';
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Read-only dry run.', 'research-team-manager') . '</strong> ' .
            esc_html__('This fetches the Zotero group and shows what a future sync would import. It does not write to your publications — your existing data is untouched.', 'research-team-manager') . '</p></div>';

        echo '<form method="post">';
        wp_nonce_field('rtm_zotero_preview');
        echo '<table class="form-table"><tr>';
        echo '<th scope="row"><label for="rtm_zotero_group">' . esc_html__('Zotero group ID', 'research-team-manager') . '</label></th>';
        echo '<td><input name="rtm_zotero_group" id="rtm_zotero_group" type="number" class="regular-text" value="' . esc_attr($group) . '" /> ';
        echo '<p class="description">' . esc_html__('Public group. Scholarly item types only (webpages excluded).', 'research-team-manager') . '</p></td>';
        echo '</tr></table>';
        submit_button(__('Run preview', 'research-team-manager'), 'primary', 'rtm_zotero_preview');
        echo '</form>';

        if ($run) {
            $this->render_results($group);
        }
        echo '</div>';
    }

    private function render_results($group) {
        $result = $this->fetch($group, self::TYPES);

        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Could not reach Zotero: ', 'research-team-manager') . esc_html($result->get_error_message()) . '</p></div>';
            return;
        }

        $existing = $this->existing_titles();
        $mapped   = array();
        $by_type  = array();
        $new = 0; $dupe = 0;
        foreach ($result['items'] as $item) {
            $row = $this->map_item($item);
            $row['_exists'] = isset($existing[$this->norm($row['title'])]);
            if ($row['_exists']) { $dupe++; } else { $new++; }
            $t = $row['item_type'] ?: 'other';
            $by_type[$t] = (isset($by_type[$t]) ? $by_type[$t] : 0) + 1;
            $mapped[] = $row;
        }

        echo '<h2>' . esc_html__('Summary', 'research-team-manager') . '</h2>';
        echo '<p>' . sprintf(
            esc_html__('Fetched %1$d scholarly items (of %2$d top-level in the group) in %3$d request(s). Library version %4$s.', 'research-team-manager'),
            count($mapped), (int) $result['total'], (int) $result['pages'], esc_html($result['version'])
        ) . '</p>';

        echo '<p><strong>' . sprintf(esc_html__('%d new', 'research-team-manager'), $new) . '</strong> ' .
            esc_html__('would be imported; ', 'research-team-manager') .
            '<strong>' . sprintf(esc_html__('%d already present', 'research-team-manager'), $dupe) . '</strong> ' .
            esc_html__('(matched by title) would be skipped/updated. No rows were written.', 'research-team-manager') . '</p>';

        $types_str = array();
        foreach ($by_type as $t => $n) { $types_str[] = esc_html($t) . ': ' . (int) $n; }
        echo '<p><em>' . esc_html__('Types: ', 'research-team-manager') . implode(' · ', $types_str) . '</em></p>';

        echo '<h2>' . esc_html__('Sample (first 20 mapped rows)', 'research-team-manager') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>' .
            '<th>' . esc_html__('Status', 'research-team-manager') . '</th>' .
            '<th>' . esc_html__('Year', 'research-team-manager') . '</th>' .
            '<th>' . esc_html__('Title', 'research-team-manager') . '</th>' .
            '<th>' . esc_html__('Authors', 'research-team-manager') . '</th>' .
            '<th>' . esc_html__('Journal', 'research-team-manager') . '</th>' .
            '<th>' . esc_html__('DOI', 'research-team-manager') . '</th></tr></thead><tbody>';
        foreach (array_slice($mapped, 0, 20) as $r) {
            $badge = $r['_exists']
                ? '<span style="color:#996800;">' . esc_html__('exists', 'research-team-manager') . '</span>'
                : '<span style="color:#1a7f37;font-weight:600;">' . esc_html__('new', 'research-team-manager') . '</span>';
            echo '<tr>' .
                '<td>' . $badge . '</td>' .
                '<td>' . esc_html($r['year'] ?: '—') . '</td>' .
                '<td>' . esc_html(mb_strimwidth($r['title'], 0, 90, '…')) . '</td>' .
                '<td>' . esc_html(mb_strimwidth($r['authors'], 0, 45, '…')) . '</td>' .
                '<td>' . esc_html(mb_strimwidth($r['journal'], 0, 40, '…')) . '</td>' .
                '<td>' . esc_html($r['doi']) . '</td>' .
                '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__('Citations are intentionally not imported from Zotero.', 'research-team-manager') . '</p>';
    }
}
