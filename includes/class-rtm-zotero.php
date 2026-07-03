<?php
/**
 * Zotero publications provider.
 *
 * Fetches a public Zotero group library via the Web API and maps items to the
 * plugin's publication-row shape. This is a data provider only — it does not
 * write anything or render admin UI; the unified publications sync (see
 * RTM_Pub_Sources) decides what to do with the rows.
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

    /** Default group id (ESHTA public group). */
    const DEFAULT_GROUP = 5544929;
    /** Scholarly item types worth importing (webpage/blog excluded by default). */
    const TYPES = array('journalArticle', 'conferencePaper', 'book', 'bookSection', 'report', 'preprint', 'thesis');
    /** Hard page cap so a runaway library can't hammer the request. */
    const MAX_PAGES = 20;

    /**
     * Fetch + map every scholarly top-level item in a group into publication rows.
     * Each row includes `source_id` (the Zotero item key) for stable dedup.
     *
     * @return array|WP_Error { rows, total, version }
     */
    public function provide($group_id) {
        $fetched = $this->fetch($group_id, self::TYPES);
        if (is_wp_error($fetched)) {
            return $fetched;
        }
        $rows = array();
        foreach ($fetched['items'] as $item) {
            $row = $this->map_item($item);
            if ($row['title'] !== '' && $row['source_id'] !== '') {
                $rows[] = $row;
            }
        }
        return array('rows' => $rows, 'total' => $fetched['total'], 'version' => $fetched['version']);
    }

    /**
     * Fetch all top-level items of the given scholarly types, paginating (100/req).
     *
     * @return array|WP_Error { items, total, version, pages }
     */
    public function fetch($group_id, $types) {
        $group_id  = (int) $group_id;
        if ($group_id <= 0) {
            return new WP_Error('rtm_zotero_group', __('No Zotero group ID is set.', 'research-team-manager'));
        }
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
                return new WP_Error('rtm_zotero_http', sprintf(__('Zotero returned HTTP %d.', 'research-team-manager'), $code));
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

            $backoff = (int) wp_remote_retrieve_header($resp, 'backoff');
            if ($backoff > 0 && $backoff <= 5) {
                sleep($backoff);
            }
        } while ($total !== null && $start < $total && $pages < self::MAX_PAGES);

        return array('items' => $all, 'total' => $total, 'version' => $version, 'pages' => $pages);
    }

    /** Map one Zotero item to the publication-row shape. Citations are 0 by design. */
    public function map_item($item) {
        $d = isset($item['data']) ? $item['data'] : array();

        $names = array();
        foreach ((isset($d['creators']) ? $d['creators'] : array()) as $c) {
            $type = isset($c['creatorType']) ? $c['creatorType'] : '';
            if ($type !== 'author' && !isset($c['name'])) {
                continue;
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
            'abstract'  => isset($d['abstractNote']) ? $d['abstractNote'] : '',
            'citations' => 0,
            'source_id' => isset($item['key']) ? $item['key'] : '',
        );
    }
}
