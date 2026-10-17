<?php
// Standalone checks for RTM_Updater with minimal WordPress stubs.
define('ABSPATH', __DIR__ . '/');
class WP_Error { public $code; public $message; public function __construct($code, $message) { $this->code = $code; $this->message = $message; } }
$filters = array(); $responses = array(); $requests = array(); $downloadContent = ''; $downloaded = array();
function add_filter($hook, $callback, $priority = 10, $arguments = 1) { $GLOBALS['filters'][$hook] = array($callback, $priority, $arguments); }
function wp_safe_remote_get($url, $options) { $GLOBALS['requests'][] = array($url, $options); return $GLOBALS['responses'][$url] ?? new WP_Error('missing', 'No fixture'); }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function download_url($url, $timeout) { $path = tempnam(sys_get_temp_dir(), 'rtm-update-'); file_put_contents($path, $GLOBALS['downloadContent']); $GLOBALS['downloaded'][] = $path; return $path; }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function untrailingslashit($s) { return rtrim($s, '/\\'); }
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require __DIR__ . '/../includes/class-rtm-updater.php';

$plugin  = 'research-team-manager/research-team-manager.php';
$repo    = 'https://github.com/ahzs645/research-team-plugin';
$latest  = $repo . '/releases/latest/download/update.json';
$version = '1.4.0';
$package = $repo . '/releases/download/v' . $version . '/research-team-manager-' . $version . '.zip';
$versioned = $repo . '/releases/download/v' . $version . '/update.json';
$content = 'release ZIP fixture';
$manifest = array('version' => $version, 'sha256' => hash('sha256', $content));
$responses[$latest] = array('code' => 200, 'body' => json_encode($manifest));
$responses[$versioned] = array('code' => 200, 'body' => json_encode($manifest));
$downloadContent = $content;

RTM_Updater::boot($plugin);
check(isset($filters['update_plugins_github.com'], $filters['upgrader_pre_download'], $filters['upgrader_source_selection']), 'Update hooks missing.');

$update = RTM_Updater::check(false, array('Version' => '1.3.2'), $plugin, array());
check(is_array($update) && $update['version'] === $version && $update['package'] === $package, 'Newer release not offered.');
check($update['id'] === $repo && $update['slug'] === 'research-team-manager' && $update['autoupdate'] === false, 'Update identity or policy changed.');
check($requests[0][0] === $latest && $requests[0][1]['redirection'] === 3 && $requests[0][1]['limit_response_size'] === 4097, 'Latest manifest request not bounded.');
check(RTM_Updater::check(false, array('Version' => $version), $plugin, array()) === false, 'Current version offered again.');
check(RTM_Updater::check('untouched', array('Version' => '0.1.0'), 'another/plugin.php', array()) === 'untouched', 'Another plugin changed.');

// A plugin installed under another folder name is still recognised.
RTM_Updater::boot('research-team-plugin-main/research-team-manager.php');
check(is_array(RTM_Updater::check(false, array('Version' => '1.3.2'), 'research-team-plugin-main/research-team-manager.php', array())), 'Renamed install not offered update.');
RTM_Updater::boot($plugin);

$file = RTM_Updater::download(false, $package, null, array('plugin' => $plugin));
check(is_string($file) && file_get_contents($file) === $content, 'Valid release not verified.');
unlink($file);

$downloadContent = 'altered ZIP fixture';
$file = RTM_Updater::download(false, $package, null, array('plugin' => $plugin));
check($file instanceof WP_Error && $file->code === 'rtm_update_checksum', 'Altered ZIP accepted.');
check(!file_exists(end($downloaded)), 'Rejected ZIP retained.');
check(RTM_Updater::download(false, $package, null, array('plugin' => 'another/plugin.php')) === false, 'Another plugin download changed.');
check(RTM_Updater::download('already handled', $package, null, array()) === 'already handled', 'Previous handler overridden.');
check(RTM_Updater::download(false, 'https://example.com/research-team-manager-1.4.0.zip', null, array()) === false, 'Foreign package intercepted.');

// Versioned metadata, never the moving latest asset, authenticates downloads.
$downloadContent = $content;
$responses[$versioned] = array('code' => 200, 'body' => json_encode(array('version' => '1.4.1', 'sha256' => hash('sha256', $content))));
$before = count($downloaded);
$file = RTM_Updater::download(false, $package, null, array('plugin' => $plugin));
check($file instanceof WP_Error && $file->code === 'rtm_update_manifest' && count($downloaded) === $before, 'Mismatched version downloaded.');
unset($responses[$versioned]);
check(RTM_Updater::download(false, $package, null, array()) instanceof WP_Error, 'Missing versioned manifest accepted.');

foreach (array('1.4.0-rc.1', '1.4.0/other', '1.4', null) as $bad) {
    $responses[$latest] = array('code' => 200, 'body' => json_encode(array('version' => $bad, 'sha256' => hash('sha256', $content))));
    check(RTM_Updater::check(false, array('Version' => '1.3.2'), $plugin, array()) === false, 'Non-stable version accepted.');
}
foreach (array(array('code' => 404, 'body' => json_encode($manifest)), array('code' => 200, 'body' => str_repeat(' ', 4097)), array('code' => 200, 'body' => '{invalid'), array('code' => 200, 'body' => '{"version":"1.4.0","sha256":"bad"}')) as $bad) {
    $responses[$latest] = $bad;
    check(RTM_Updater::check(false, array('Version' => '1.3.2'), $plugin, array()) === false, 'Bad manifest response accepted.');
}

// Folder normalisation only touches this plugin's upgrades.
class FakeFilesystem { public $moves = array(); public function move($from, $to, $overwrite) { $this->moves[] = array($from, $to); return true; } }
$GLOBALS['wp_filesystem'] = new FakeFilesystem();
check(RTM_Updater::source_selection('/tmp/x/research-team-manager/', '/tmp/x', null, array('plugin' => $plugin)) === '/tmp/x/research-team-manager/', 'Matching folder changed.');
check(RTM_Updater::source_selection('/tmp/x/other/', '/tmp/x', null, array('plugin' => 'another/plugin.php')) === '/tmp/x/other/', 'Another plugin folder changed.');
RTM_Updater::boot('research-team-plugin-main/research-team-manager.php');
check(RTM_Updater::source_selection('/tmp/x/research-team-manager/', '/tmp/x', null, array('plugin' => 'research-team-plugin-main/research-team-manager.php')) === '/tmp/x/research-team-plugin-main/', 'Renamed install folder not preserved.');
check($GLOBALS['wp_filesystem']->moves === array(array('/tmp/x/research-team-manager/', '/tmp/x/research-team-plugin-main/')), 'Unexpected filesystem moves.');

echo "PASS updater: discovery, versioned checksums, malformed metadata, folder handling\n";
