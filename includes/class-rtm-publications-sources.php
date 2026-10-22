<?php
/**
 * Publications sources: pick where a team's publications come from
 * (Manual / Google Scholar / Zotero) and run a source-scoped sync.
 *
 * Sync policy = "replace that source only": for the selected source we upsert the
 * fetched rows (by source_id) and prune rows of THAT source that are no longer
 * present. Rows from other sources (and manual entries) are never touched. Pruning
 * is skipped when a fetch returns nothing, so a failed/empty fetch can't wipe data.
 *
 * @package ResearchTeamManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Pub_Sources {

    const MANUAL  = 'manual';
    const SCHOLAR = 'google_scholar';
    const ZOTERO  = 'zotero';

    const OPT_SOURCE   = 'rtm_publications_source';
    const OPT_ZOTERO   = 'rtm_zotero_group_id';
    const OPT_SCHEDULE = 'rtm_publications_schedule';
    const OPT_LAST     = 'rtm_publications_last_sync';
    const CRON_HOOK    = 'rtm_publications_sync_event';

    /** Known sources => label. */
    public static function sources() {
        return array(
            self::MANUAL  => __('Manual', 'research-team-manager'),
            self::SCHOLAR => __('Google Scholar', 'research-team-manager'),
            self::ZOTERO  => __('Zotero', 'research-team-manager'),
        );
    }

    public static function label($source) {
        $all = self::sources();
        return isset($all[$source]) ? $all[$source] : $source;
    }

    private static function valid($source) {
        return array_key_exists($source, self::sources()) ? $source : self::MANUAL;
    }

    /* ------------------------------------------------------------------ */
    /* Per-team config (global option + optional per-team term-meta)        */
    /* ------------------------------------------------------------------ */

    public static function get_source($team_id = 0) {
        $team_id = (int) $team_id;
        if ($team_id) {
            $t = get_term_meta($team_id, self::OPT_SOURCE, true);
            if ($t) {
                return self::valid($t);
            }
        }
        return self::valid(get_option(self::OPT_SOURCE, self::MANUAL));
    }

    public static function get_zotero_group($team_id = 0) {
        $team_id = (int) $team_id;
        if ($team_id) {
            $g = (int) get_term_meta($team_id, self::OPT_ZOTERO, true);
            if ($g) {
                return $g;
            }
        }
        return (int) get_option(self::OPT_ZOTERO, 0);
    }

    /* ------------------------------------------------------------------ */
    /* Fetch rows for a source (read-only)                                 */
    /* ------------------------------------------------------------------ */

    /** @return array|WP_Error normalized rows (each with a source_id). */
    public static function fetch_rows($source, $team_id) {
        if ($source === self::SCHOLAR) {
            $uid  = function_exists('rtm_get_scholar_id_for_team')
                ? rtm_get_scholar_id_for_team($team_id)
                : get_option('rtm_google_scholar_user_id', '');
            $rows = apply_filters('rtm_fetch_scholar_publications', array(), $uid, $team_id);
            $out  = array();
            foreach ((array) $rows as $p) {
                $p = (array) $p;
                if (empty($p['title'])) {
                    continue;
                }
                $p['source_id'] = isset($p['google_scholar_id']) ? $p['google_scholar_id'] : '';
                $out[] = $p;
            }
            return $out;
        }

        if ($source === self::ZOTERO) {
            $group = self::get_zotero_group($team_id);
            $res   = (new RTM_Zotero())->provide($group);
            if (is_wp_error($res)) {
                return $res;
            }
            return $res['rows'];
        }

        return array(); // manual
    }

    /* ------------------------------------------------------------------ */
    /* Sync (or dry-run preview)                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param int  $team_id
     * @param bool $dry_run  true = compute counts + samples, write nothing.
     * @return array|WP_Error stats { source, fetched, new, updated, pruned, dry_run, samples }
     */
    public static function sync($team_id = 0, $dry_run = false) {
        global $wpdb;
        $team_id = (int) $team_id;
        $source  = self::get_source($team_id);

        if ($source === self::MANUAL) {
            return new WP_Error('rtm_manual', __('Publications source is “Manual” — nothing to sync. Add entries by hand or use Import JSON.', 'research-team-manager'));
        }

        $rows = self::fetch_rows($source, $team_id);
        if (is_wp_error($rows)) {
            return $rows;
        }

        $table   = $wpdb->prefix . 'rtm_publications';
        $allowed = array('title', 'authors', 'journal', 'year', 'citations', 'url', 'google_scholar_id', 'abstract', 'source', 'source_id');

        // Existing rows for this (team, source): source_id => id.
        $existing = $wpdb->get_results(
            $wpdb->prepare("SELECT id, source_id FROM {$table} WHERE team_id = %d AND source = %s", $team_id, $source),
            ARRAY_A
        );
        $by_srcid = array();
        foreach ((array) $existing as $er) {
            if ($er['source_id'] !== '') {
                $by_srcid[$er['source_id']] = (int) $er['id'];
            }
        }

        $seen = array();
        $new = 0; $updated = 0; $samples = array();

        foreach ($rows as $pub) {
            $pub = (array) $pub;
            if (empty($pub['title'])) {
                continue;
            }
            $sid = isset($pub['source_id']) ? (string) $pub['source_id'] : '';
            if ($sid === '') {
                $sid = substr(md5(strtolower($pub['title']) . '|' . (isset($pub['year']) ? $pub['year'] : '')), 0, 32);
            }
            $pub['source_id'] = $sid;
            $pub['source']    = $source;
            $pub['year']      = isset($pub['year']) ? (int) $pub['year'] : 0;
            $pub['citations'] = isset($pub['citations']) ? (int) $pub['citations'] : 0;

            $row            = array_intersect_key($pub, array_flip($allowed));
            $row['team_id'] = $team_id;
            $seen[$sid]     = true;

            $is_update = isset($by_srcid[$sid]);
            if ($is_update) {
                if (!$dry_run) {
                    $wpdb->update($table, $row, array('id' => $by_srcid[$sid]));
                }
                $updated++;
            } else {
                if (!$dry_run) {
                    $wpdb->insert($table, $row);
                }
                $new++;
            }

            if (count($samples) < 20) {
                $samples[] = array(
                    'status'  => $is_update ? 'update' : 'new',
                    'title'   => $pub['title'],
                    'year'    => $pub['year'],
                    'authors' => isset($pub['authors']) ? $pub['authors'] : '',
                    'journal' => isset($pub['journal']) ? $pub['journal'] : '',
                );
            }
        }

        // Prune stale rows of THIS source only — but never on an empty fetch.
        $pruned = 0;
        if (!empty($rows)) {
            foreach ($by_srcid as $sid => $id) {
                if (!isset($seen[$sid])) {
                    if (!$dry_run) {
                        $wpdb->delete($table, array('id' => $id));
                    }
                    $pruned++;
                }
            }
        }

        return array(
            'source'  => $source,
            'fetched' => count($rows),
            'new'     => $new,
            'updated' => $updated,
            'pruned'  => $pruned,
            'dry_run' => (bool) $dry_run,
            'samples' => $samples,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Scheduled auto-sync (WP-Cron)                                       */
    /* ------------------------------------------------------------------ */

    /** Auto-sync frequency options (key => label). Keys are WP-Cron recurrences. */
    public static function schedules() {
        return array(
            'off'    => __('Off — manual sync only', 'research-team-manager'),
            'daily'  => __('Daily', 'research-team-manager'),
            'weekly' => __('Weekly', 'research-team-manager'),
        );
    }

    public static function get_schedule() {
        $s = get_option(self::OPT_SCHEDULE, 'off');
        return array_key_exists($s, self::schedules()) ? $s : 'off';
    }

    /** Wire the cron handler + self-healing scheduler. Called once at load. */
    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'add_weekly'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_scheduled'));
        add_action('init', array(__CLASS__, 'maybe_schedule'));
    }

    /** Guarantee a "weekly" recurrence exists (core has had it since 5.4, but be safe). */
    public static function add_weekly($schedules) {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array('interval' => WEEK_IN_SECONDS, 'display' => __('Once Weekly', 'research-team-manager'));
        }
        return $schedules;
    }

    /** Ensure a scheduled event exists iff a frequency is set. */
    public static function maybe_schedule() {
        $freq = self::get_schedule();
        $next = wp_next_scheduled(self::CRON_HOOK);
        if ($freq === 'off') {
            if ($next) {
                wp_unschedule_event($next, self::CRON_HOOK);
            }
            return;
        }
        if (!$next) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $freq, self::CRON_HOOK);
        }
    }

    /** Persist a new frequency and (re)schedule the cron event immediately. */
    public static function reschedule($freq) {
        $freq = array_key_exists($freq, self::schedules()) ? $freq : 'off';
        update_option(self::OPT_SCHEDULE, $freq);
        self::clear_schedule();
        if ($freq !== 'off') {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $freq, self::CRON_HOOK);
        }
    }

    /** Remove any scheduled event (deactivation / off). */
    public static function clear_schedule() {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Cron callback: sync every team whose source isn't Manual, and record a
     * summary. Silent (no output) — runs in cron context.
     */
    public static function run_scheduled() {
        if (function_exists('rtm_is_multiple_teams') && rtm_is_multiple_teams()) {
            $team_ids = get_terms(array('taxonomy' => 'rtm_research_team', 'hide_empty' => false, 'fields' => 'ids'));
            $team_ids = array_map('intval', (array) $team_ids);
            if (empty($team_ids)) {
                $team_ids = array(0);
            }
        } else {
            $team_ids = array(0);
        }

        $results = array();
        foreach ($team_ids as $tid) {
            if (self::get_source($tid) === self::MANUAL) {
                continue;
            }
            $r = self::sync($tid, false);
            $results[$tid] = is_wp_error($r)
                ? array('error' => $r->get_error_message())
                : array('source' => $r['source'], 'new' => $r['new'], 'updated' => $r['updated'], 'pruned' => $r['pruned']);
        }

        update_option(self::OPT_LAST, array('time' => time(), 'results' => $results));
    }

    /** Status for the settings screen: schedule, next run, last run. */
    public static function status() {
        return array(
            'schedule' => self::get_schedule(),
            'next'     => wp_next_scheduled(self::CRON_HOOK),
            'last'     => get_option(self::OPT_LAST, null),
        );
    }
}
