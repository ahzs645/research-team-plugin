<?php
/**
 * WordPress updates from published GitHub releases.
 *
 * The plugin header's `Update URI` routes update checks for this plugin to the
 * `update_plugins_github.com` filter. Each stable GitHub release carries
 * `research-team-manager-{version}.zip` and an `update.json` asset holding the
 * version and the ZIP's SHA-256. Downloads are verified against the versioned
 * release's own update.json, never against the moving "latest" asset.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RTM_Updater {

    const UPDATE_URI = 'https://github.com/ahzs645/research-team-plugin';
    const SLUG       = 'research-team-manager';

    /** Plugin basename, e.g. research-team-manager/research-team-manager.php. */
    private static $plugin = '';

    public static function boot($plugin = null) {
        self::$plugin = $plugin !== null ? $plugin : RTM_PLUGIN_BASENAME;
        add_filter('update_plugins_github.com', array(__CLASS__, 'check'), 10, 4);
        add_filter('upgrader_pre_download', array(__CLASS__, 'download'), 10, 4);
        add_filter('upgrader_source_selection', array(__CLASS__, 'source_selection'), 10, 4);
    }

    /** Release metadata for $version, or for the latest stable release when ''. */
    private static function manifest($version = '') {
        $url = self::UPDATE_URI . '/releases/' . ($version === '' ? 'latest/download' : 'download/v' . $version) . '/update.json';
        // Release assets redirect to GitHub's download CDN. "latest" resolves
        // only to a published, non-prerelease release.
        $response = wp_safe_remote_get($url, array('timeout' => 10, 'redirection' => 3, 'limit_response_size' => 4097));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || strlen($body) > 4096) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || !is_string($data['version'] ?? null) ||
            !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $data['version']) ||
            !is_string($data['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $data['sha256'])) {
            return null;
        }
        if ($version !== '' && $version !== $data['version']) {
            return null;
        }
        $data['package'] = self::package_url($data['version']);
        return $data;
    }

    private static function package_url($version) {
        return self::UPDATE_URI . '/releases/download/v' . $version . '/' . self::SLUG . '-' . $version . '.zip';
    }

    public static function check($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== self::$plugin) {
            return $update;
        }
        $latest = self::manifest();
        if (!$latest || version_compare($latest['version'], $plugin_data['Version'] ?? '0', '<=')) {
            return false;
        }
        return array(
            'id'           => self::UPDATE_URI,
            'slug'         => self::SLUG,
            'version'      => $latest['version'],
            'url'          => self::UPDATE_URI,
            'package'      => $latest['package'],
            'requires'     => '6.5',
            'requires_php' => '7.4',
            'autoupdate'   => false,
        );
    }

    public static function download($reply, $package, $upgrader, $hook_extra) {
        if ($reply !== false) {
            return $reply;
        }
        if (($hook_extra['plugin'] ?? self::$plugin) !== self::$plugin) {
            return false;
        }
        $pattern = '~^' . preg_quote(self::UPDATE_URI, '~') . '/releases/download/v([0-9]+\.[0-9]+\.[0-9]+)/' . self::SLUG . '-\1\.zip$~D';
        if (!preg_match($pattern, $package, $matches)) {
            return false;
        }
        $release = self::manifest($matches[1]);
        if (!$release || $package !== $release['package']) {
            return new WP_Error('rtm_update_manifest', 'The Research Team Manager release manifest could not be verified.');
        }
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $file = download_url($package, 300);
        if (is_wp_error($file)) {
            return $file;
        }
        if (!is_string($file) || !is_file($file) || !hash_equals($release['sha256'], hash_file('sha256', $file))) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
            }
            return new WP_Error('rtm_update_checksum', 'The Research Team Manager download did not match its release checksum.');
        }
        return $file;
    }

    /**
     * Release ZIPs unpack to research-team-manager/. Keep a site that installed
     * the plugin under another folder name (for example from a branch ZIP)
     * updating in place instead of gaining a second copy.
     */
    public static function source_selection($source, $remote_source, $upgrader, $hook_extra = array()) {
        if (!is_string($source) || ($hook_extra['plugin'] ?? '') !== self::$plugin) {
            return $source;
        }
        $folder = dirname(self::$plugin);
        if ($folder === '.' || $folder === '' || basename(untrailingslashit($source)) === $folder) {
            return $source;
        }
        global $wp_filesystem;
        $target = trailingslashit($remote_source) . $folder . '/';
        if (!$wp_filesystem || !$wp_filesystem->move($source, $target, true)) {
            return new WP_Error('rtm_update_folder', 'The Research Team Manager update could not be prepared for its plugin folder.');
        }
        return $target;
    }
}
