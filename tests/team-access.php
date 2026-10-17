<?php
// Standalone checks for RTM_Team_Access permission rules, with minimal
// WordPress stubs (no database).
define('ABSPATH', __DIR__ . '/');
$options = array(); $user_caps = array(); $user_meta = array(); $posts = array(); $post_terms = array(); $set_calls = array();
function add_action() {} function add_filter() {} function __($s) { return $s; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['options']) ? $GLOBALS['options'][$k] : $d; }
function rtm_is_multiple_teams() { return get_option('rtm_team_mode', 'multiple') === 'multiple'; }
function get_current_user_id() { return $GLOBALS['current_user']; }
function user_can($user, $cap) { return in_array($cap, $GLOBALS['user_caps'][$user] ?? array(), true); }
function get_user_meta($user, $key, $single) { return $GLOBALS['user_meta'][$user][$key] ?? ''; }
function get_terms($args) { return array(7, 8, 9); }
function is_wp_error($v) { return false; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_type($id) { return isset($GLOBALS['posts'][$id]) ? 'rtm_team_member' : false; }
function wp_get_object_terms($id, $tax, $args) { return $GLOBALS['post_terms'][$id] ?? array(); }
function get_term($id) { return (object) array('term_id' => (int) $id, 'taxonomy' => 'rtm_research_team'); }
function get_term_by($field, $value, $tax) { return (object) array('term_id' => (int) $value - 100); }
function wp_set_object_terms($id, $terms, $tax) { $GLOBALS['set_calls'][] = array($id, $terms); $GLOBALS['post_terms'][$id] = $terms; }
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require __DIR__ . '/../includes/class-rtm-team-access.php';

$member_caps = array_keys(RTM_Team_Access::MEMBER_CAPS);
$user_caps = array(1 => array_merge($member_caps, array('rtm_manage_all_teams')), 2 => $member_caps, 3 => array('edit_rtm_members'));
$user_meta[2][RTM_Team_Access::USER_META] = array(7);
$posts = array(
    10 => (object) array('ID' => 10, 'post_type' => 'rtm_team_member', 'post_author' => 1),
    11 => (object) array('ID' => 11, 'post_type' => 'rtm_team_member', 'post_author' => 1),
    12 => (object) array('ID' => 12, 'post_type' => 'rtm_team_member', 'post_author' => 2),
    13 => (object) array('ID' => 13, 'post_type' => 'rtm_team_member', 'post_author' => 1),
);
$post_terms = array(10 => array(7), 11 => array(8), 12 => array(), 13 => array(7, 8));

// Taxonomies on members are assigned with member capabilities.
$args = RTM_Team_Access::taxonomy_caps(array(), 'rtm_research_area', array('rtm_team_member'));
check($args['capabilities']['assign_terms'] === 'edit_rtm_members', 'Member taxonomy assign cap not mapped.');
$args = RTM_Team_Access::taxonomy_caps(array('capabilities' => array('assign_terms' => 'edit_posts', 'edit_terms' => 'manage_categories')), 'rtm_research_team', array('rtm_team_member'));
check($args['capabilities']['manage_terms'] === 'edit_others_rtm_members' && $args['capabilities']['edit_terms'] === 'manage_categories', 'Team taxonomy caps wrong.');
check(RTM_Team_Access::taxonomy_caps(array('x' => 1), 'category', array('post')) === array('x' => 1), 'Unrelated taxonomy changed.');

$edit = function ($user, $post) { return RTM_Team_Access::map_meta_cap(array('edit_others_rtm_members'), 'edit_post', $user, array($post)); };
$denied = function ($caps) { return in_array('do_not_allow', $caps, true); };

// Open access: nobody is scoped.
$current_user = 2;
check(!RTM_Team_Access::is_scoped(2) && RTM_Team_Access::manageable_team_ids(2) === array(7, 8, 9), 'Open mode scoped a team manager.');
check(!$denied($edit(2, 11)), 'Open mode blocked another team.');

// Restricted access.
$options['rtm_team_access'] = 'restricted';
check(!RTM_Team_Access::is_scoped(1) && RTM_Team_Access::is_scoped(2), 'Scoping by capability wrong.');
check(RTM_Team_Access::manageable_team_ids(2) === array(7), 'Assigned teams wrong.');
check(RTM_Team_Access::manageable_team_ids(3) === array(), 'Author-level user may manage teams.');
check(!$denied($edit(2, 10)), 'Own team member blocked.');
check($denied($edit(2, 11)), 'Other team member allowed.');
check(!$denied($edit(2, 12)), 'Own new unassigned draft blocked.');
check(!$denied($edit(2, 13)), 'Shared member blocked.');
check(!$denied($edit(1, 11)), 'Administrator blocked.');
check(RTM_Team_Access::map_meta_cap(array('manage_categories'), 'edit_term', 2, array(7)) === array('edit_others_rtm_members'), 'Own team settings blocked.');
check(RTM_Team_Access::map_meta_cap(array('manage_categories'), 'edit_term', 2, array(8)) === array('manage_categories'), 'Other team settings opened.');

// Saving keeps assignments to teams the user cannot manage.
// Fake term_taxonomy IDs are term ID + 100.
RTM_Team_Access::keep_team_assignments(13, array(), array(107), 'rtm_research_team', false, array(107, 108));
check(end($set_calls) === array(13, array(7, 8)), 'Other team assignment dropped.');
$set_calls = array();
RTM_Team_Access::keep_team_assignments(10, array(), array(107, 109), 'rtm_research_team', false, array(107));
check(end($set_calls) === array(10, array(7)), 'Unmanaged team added.');
$set_calls = array();
RTM_Team_Access::keep_team_assignments(10, array(), array(107), 'rtm_research_team', false, array(107));
check($set_calls === array(), 'Valid save corrected.');

// Single-team mode never scopes.
$options['rtm_team_mode'] = 'single';
check(!RTM_Team_Access::is_scoped(2), 'Single mode scoped a user.');

echo "PASS team access: capability mapping, open vs restricted scoping, kept assignments\n";
