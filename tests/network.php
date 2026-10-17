<?php
// Standalone checks for RTM_Network helpers and field rendering, with
// minimal WordPress stubs (no database).
define('ABSPATH', __DIR__ . '/');
$meta = array(); $terms = array(); $options = array(); $titles = array(); $thumbs = array();
function apply_filters($hook, $value) { return $value; }
function add_action() {} function add_filter() {}
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_title($s) { return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)); }
function sanitize_html_class($s) { return preg_replace('/[^A-Za-z0-9_-]/', '', $s); }
function wp_unslash($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function wp_kses_post($s) { return $s; }
function wpautop($s) { return '<p>' . $s . '</p>'; }
function is_email($s) { return is_string($s) && filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : false; }
function is_wp_error($v) { return false; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function wp_date($format, $ts) { return date($format, $ts); }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_the_title($id) { return $GLOBALS['titles'][$id] ?? ''; }
function get_the_post_thumbnail($id) { return $GLOBALS['thumbs'][$id] ?? ''; }
function wp_get_object_terms($id, $tax) { return $GLOBALS['terms'][$id][$tax] ?? array(); }
function get_term_by($field, $value, $tax) { return $value === 'nursing' ? (object) array('term_id' => 7) : false; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require __DIR__ . '/../includes/class-rtm-network.php';

// Affiliations are bounded, sanitized and drop empty rows.
$rows = RTM_Network::clean_affiliations(array(array('title' => ' Professor ', 'affiliation' => '<b>UNBC</b>'), array('title' => '', 'affiliation' => ''), 'junk'));
check($rows === array(array('title' => 'Professor', 'affiliation' => 'UNBC')), 'Affiliations not cleaned.');
check(count(RTM_Network::clean_affiliations(array_fill(0, 20, array('title' => 'x')))) === 10, 'Affiliations not capped at 10.');
check(RTM_Network::clean_affiliations('nope') === array(), 'Non-array affiliations accepted.');

// Institution names normalise known variants and drop non-institutions.
$names = RTM_Network::institution_names(array(
    array('title' => '', 'affiliation' => 'UNBC'),
    array('title' => '', 'affiliation' => 'University of Northern British Columbia'),
    array('title' => '', 'affiliation' => 'ubc'),
    array('title' => '', 'affiliation' => 'Northern Health Authority'),
    array('title' => '', 'affiliation' => 'Associate Professor'),
));
check($names === array('University of Northern British Columbia', 'University of British Columbia', 'Northern Health'), 'Institution names not normalised: ' . json_encode($names));

check(RTM_Network::public_position(' 12 - 3 ') === '' && RTM_Network::public_position('Professor') === 'Professor', 'Numeric positions not hidden.');

// Directory filters read the query string into a tax query.
$_GET = array('rn_q' => 'water quality', 'rn_department' => 'Nursing', 'rn_theme' => 'missing', 'rn_faculty' => array('x'));
$args = RTM_Network::query_args(array('post_type' => 'rtm_team_member'));
check($args['rtmn_search'] === 'water quality', 'Search term not passed.');
check($args['tax_query'][0] === array('taxonomy' => 'rtmn_department', 'field' => 'term_id', 'terms' => array(7)), 'Known term filter wrong.');
check($args['tax_query'][1]['terms'] === array(0) && $args['tax_query']['relation'] === 'AND' && count($args['tax_query']) === 3, 'Unknown term must match nothing; array values ignored.');
$_GET = array();
check(!isset(RTM_Network::query_args(array())['tax_query']), 'Empty filters added a tax query.');

// Field rendering.
$titles[1] = 'Dr. Ada Lovelace';
$meta[1] = array(
    '_rtm_position' => 'Professor',
    '_rtm_email' => 'ada@example.org',
    '_rtm_website' => 'https://ada.example.org',
    '_rtm_orcid' => 'javascript:alert(1)',
    '_rtmn_affiliations' => array(array('title' => 'Professor', 'affiliation' => 'UNBC'), array('title' => 'Scientist', 'affiliation' => 'Northern Health Authority')),
    '_rtm_short_description' => 'Short bio',
);
$terms[1] = array('rtm_research_area' => array('Hydrology', 'Ecology'), 'rtmn_department' => array('Nursing'), 'rtmn_faculty' => array('Health'));
check(RTM_Network::field_html('portrait', 1) === '<span class="rtmn-initials" aria-label="No photograph supplied">AL</span>', 'Initials fallback wrong.');
check(RTM_Network::field_html('position', 1) === 'Professor', 'Position wrong.');
check(RTM_Network::field_html('institution', 1) === 'University of Northern British Columbia', 'Primary institution wrong.');
check(RTM_Network::field_html('affiliations', 1) === 'University of Northern British Columbia · Northern Health', 'Affiliations wrong.');
check(RTM_Network::field_html('topics', 1) === '<span class="rtmn-tag">Hydrology</span><span class="rtmn-tag">Ecology</span>', 'Topics wrong.');
check(RTM_Network::field_html('themes', 1) === '', 'Empty themes rendered.');
check(RTM_Network::field_html('academic_units', 1) === '<p>Nursing</p><p class="rtmn-muted">Health</p>', 'Academic units wrong.');
check(RTM_Network::field_html('biography', 1) === '<p>Short bio</p>', 'Biography fallback wrong.');
check(RTM_Network::field_html('contact', 1) === '<a class="rtmn-contact-link" href="mailto:ada@example.org">Email researcher</a>', 'Contact wrong.');
$links = RTM_Network::field_html('links', 1);
check(strpos($links, 'https://ada.example.org') !== false && strpos($links, 'javascript') === false, 'Links must keep http(s) only.');
check(RTM_Network::field_html('supervision', 1) === '<p>Contact the researcher to ask about supervision or collaboration.</p>', 'Supervision fallback wrong.');
check(RTM_Network::field_html('unknown', 1) === '', 'Unknown field rendered.');

// Imported source records add details without overriding manual values.
$titles[2] = 'Grace Hopper';
$meta[2] = array(
    '_rtm_position' => 'Professor',
    '_rtm_email' => 'grace@example.org',
    '_rtmn_unbc_record' => array(
        'positions' => array('Professor', 'Adjunct Professor'),
        'email' => array('GRACE@example.org', 'hopper@unbc.ca'),
        'supervision_status' => array('Accepting graduate students'),
        'retrieved_at' => '2026-09-01',
        'source_url' => 'https://www.unbc.ca/grace',
        'portrait_url' => 'https://www.unbc.ca/sites/default/files/grace.jpg',
        'professional_links' => array(array('url' => 'https://orcid.org/0000-0001', 'field' => '')),
    ),
);
check(RTM_Network::field_html('institution', 2) === 'University of Northern British Columbia', 'Source-record institution fallback missing.');
check(RTM_Network::field_html('appointments', 2) === '<p>UNBC-listed appointments: Adjunct Professor</p>', 'Appointments wrong.');
check(substr_count(RTM_Network::field_html('contact', 2), 'mailto:') === 2, 'Source email de-duplication wrong.');
check(strpos(RTM_Network::field_html('supervision', 2), 'Accepting graduate students') === 3, 'Supervision status missing.');
check(strpos(RTM_Network::field_html('portrait', 2), 'rtmn-source-portrait') !== false, 'Source portrait missing.');
check(strpos(RTM_Network::field_html('links', 2), 'ORCID') !== false, 'Source ORCID link missing.');
check(strpos(RTM_Network::field_html('source', 2), 'https://www.unbc.ca/grace') !== false, 'Source link missing.');

// First run enables the module only when directory data already exists.
class FakeWpdb { public $postmeta = 'wp_postmeta'; public $result = null; public function get_var($sql) { return $this->result; } }
$GLOBALS['wpdb'] = new FakeWpdb();
check(RTM_Network::is_enabled() === false && $options['rtm_network_enabled'] === '0', 'Fresh site enabled the directory.');
unset($options['rtm_network_enabled']);
$GLOBALS['wpdb']->result = '1';
check(RTM_Network::is_enabled() === true && $options['rtm_network_enabled'] === '1', 'Existing directory data not detected.');
$GLOBALS['wpdb']->result = null;
check(RTM_Network::is_enabled() === true, 'Stored setting not respected.');

echo "PASS network: affiliations, institutions, filters, field rendering, first-run detection\n";
