<?php
/**
 * Per-lab settings for the `research_team` taxonomy.
 *
 * Each team term carries its own details — Principal Investigator, logo, intro,
 * Google Scholar ID and contact info — stored as term meta and surfaced on the
 * team's add/edit screens and in the REST API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Teams {

    const TAXONOMY = 'rtm_research_team';

    /**
     * Term meta keys => sanitize callback. The order here drives the admin form.
     */
    // Scalar fields saved generically. The lead (type / member IDs / org name)
    // is handled separately in save_term_meta() because it's structured.
    private $fields = array(
        'rtm_team_type'          => 'sanitize_text_field',
        'rtm_team_theme'         => 'sanitize_text_field',
        'rtm_team_logo'          => 'absint',
        'rtm_team_intro'         => 'wp_kses_post',
        'rtm_team_scholar_id'    => 'sanitize_text_field',
        'rtm_team_contact_email' => 'sanitize_email',
        'rtm_team_website'       => 'esc_url_raw',
        'rtm_team_link_type'     => 'sanitize_text_field',
        'rtm_team_location'      => 'sanitize_text_field',
        'rtm_team_roster_group'  => 'sanitize_text_field',
    );

    /** How a team's roster can be grouped on its page. */
    private function roster_group_options() {
        return array(
            'none'              => __('No grouping (one list)', 'research-team-manager'),
            'rtm_member_status' => __('By member status (Current, Alumni, …)', 'research-team-manager'),
            'rtm_team_role'     => __('By team role (Faculty, PhD, …)', 'research-team-manager'),
        );
    }

    /** Option lists, resolved via the shared plugin helpers. */
    private function types() {
        return function_exists('rtm_team_types') ? rtm_team_types() : array();
    }
    private function themes() {
        return function_exists('rtm_team_themes') ? rtm_team_themes() : array();
    }

    /**
     * Searchable click-to-add member picker for the team lead(s). Renders
     * current picks as chips (hidden rtm_team_lead_ids[] inputs) plus a search
     * box; the supporting JS/CSS is printed once.
     */
    private function lead_picker($selected_ids) {
        $selected_ids = array_values(array_filter(array_map('intval', (array) $selected_ids)));
        $member_ids = get_posts(array(
            'post_type'   => 'rtm_team_member',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
            'fields'      => 'ids',
        ));
        $data = array();
        foreach ($member_ids as $mid) {
            $data[] = array('id' => (int) $mid, 'name' => get_the_title($mid));
        }
        ?>
        <div class="rtm-lead-picker" data-members="<?php echo esc_attr(wp_json_encode($data)); ?>">
            <div class="rtm-lead-chips">
                <?php foreach ($selected_ids as $id):
                    $name = get_the_title($id);
                    if (!$name) { continue; }
                    ?>
                    <span class="rtm-lead-chip">
                        <input type="hidden" name="rtm_team_lead_ids[]" value="<?php echo (int) $id; ?>" />
                        <?php echo esc_html($name); ?>
                        <button type="button" class="rtm-lead-remove" aria-label="<?php esc_attr_e('Remove', 'research-team-manager'); ?>">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="rtm-lead-search-wrap">
                <input type="search" class="rtm-lead-search" placeholder="<?php esc_attr_e('Search members to add…', 'research-team-manager'); ?>" autocomplete="off" />
                <ul class="rtm-lead-results" hidden></ul>
            </div>
        </div>
        <?php
        $this->print_lead_picker_script();
    }

    /** JS + minimal styles for the lead picker (printed once per request). */
    private function print_lead_picker_script() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <style>
        .rtm-lead-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
        .rtm-lead-chip{display:inline-flex;align-items:center;gap:6px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:4px;padding:3px 6px 3px 10px;font-size:13px}
        .rtm-lead-chip .rtm-lead-remove{border:0;background:none;cursor:pointer;font-size:16px;line-height:1;color:#646970;padding:0 2px}
        .rtm-lead-chip .rtm-lead-remove:hover{color:#d63638}
        .rtm-lead-search-wrap{position:relative;max-width:360px}
        .rtm-lead-search{width:100%}
        .rtm-lead-results{position:absolute;z-index:20;left:0;right:0;margin:2px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-height:240px;overflow:auto;box-shadow:0 2px 8px rgba(0,0,0,.12)}
        .rtm-lead-results li{margin:0}
        .rtm-lead-results .rtm-lead-add{display:flex;justify-content:space-between;align-items:center;width:100%;text-align:left;border:0;background:none;padding:8px 12px;cursor:pointer;font-size:13px}
        .rtm-lead-results .rtm-lead-add:hover{background:#f6f7f7}
        .rtm-lead-results .rtm-lead-add span{color:#2271b1;font-weight:600;white-space:nowrap}
        .rtm-lead-empty{padding:8px 12px;color:#646970;font-size:13px}
        </style>
        <script>
        (function () {
            function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
            function init(picker) {
                if (picker.dataset.rtmInit) { return; }
                picker.dataset.rtmInit = '1';
                var members = []; try { members = JSON.parse(picker.getAttribute('data-members') || '[]'); } catch (e) {}
                var chips   = picker.querySelector('.rtm-lead-chips');
                var search  = picker.querySelector('.rtm-lead-search');
                var results = picker.querySelector('.rtm-lead-results');

                function selectedIds() {
                    return Array.prototype.map.call(
                        chips.querySelectorAll('input[name="rtm_team_lead_ids[]"]'),
                        function (i) { return parseInt(i.value, 10); }
                    );
                }
                function addChip(m) {
                    if (selectedIds().indexOf(m.id) > -1) { return; }
                    var span = document.createElement('span');
                    span.className = 'rtm-lead-chip';
                    span.innerHTML = '<input type="hidden" name="rtm_team_lead_ids[]" value="' + m.id + '"> ' + esc(m.name) +
                        ' <button type="button" class="rtm-lead-remove" aria-label="Remove">×</button>';
                    chips.appendChild(span);
                }
                function render(q) {
                    q = (q || '').trim().toLowerCase();
                    results.innerHTML = '';
                    if (!q) { results.hidden = true; return; }
                    var sel = selectedIds();
                    var matches = members.filter(function (m) { return sel.indexOf(m.id) === -1 && m.name.toLowerCase().indexOf(q) > -1; }).slice(0, 10);
                    if (!matches.length) { results.innerHTML = '<li class="rtm-lead-empty">No matches</li>'; results.hidden = false; return; }
                    matches.forEach(function (m) {
                        var li = document.createElement('li');
                        li.innerHTML = '<button type="button" class="rtm-lead-add">' + esc(m.name) + ' <span>+ Add</span></button>';
                        li.querySelector('button').addEventListener('click', function () { addChip(m); search.value = ''; render(''); search.focus(); });
                        results.appendChild(li);
                    });
                    results.hidden = false;
                }
                search.addEventListener('input', function () { render(search.value); });
                search.addEventListener('focus', function () { render(search.value); });
                chips.addEventListener('click', function (e) {
                    var btn = e.target.closest('.rtm-lead-remove');
                    if (btn) { btn.closest('.rtm-lead-chip').remove(); }
                });
                document.addEventListener('click', function (e) { if (!picker.contains(e.target)) { results.hidden = true; } });
            }
            document.querySelectorAll('.rtm-lead-picker').forEach(init);
        })();
        </script>
        <?php
    }

    /** Show/hide the person vs organization lead fields based on the Lead type. */
    private function print_lead_script() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <script>
        (function () {
            function sync(sel) {
                var scope = sel.closest('form') || document;
                var isOrg = sel.value === 'org';
                scope.querySelectorAll('.rtm-lead-person').forEach(function (e) { e.style.display = isOrg ? 'none' : ''; });
                scope.querySelectorAll('.rtm-lead-org').forEach(function (e) { e.style.display = isOrg ? '' : 'none'; });
            }
            document.addEventListener('change', function (e) { if (e.target && e.target.id === 'rtm_team_lead_type') { sync(e.target); } });
            document.querySelectorAll('#rtm_team_lead_type').forEach(sync);
        })();
        </script>
        <?php
    }

    public function __construct() {
        add_action('init', array($this, 'register_term_meta'));

        add_action(self::TAXONOMY . '_add_form_fields', array($this, 'add_form_fields'));
        add_action(self::TAXONOMY . '_edit_form_fields', array($this, 'edit_form_fields'), 10, 2);

        add_action('created_' . self::TAXONOMY, array($this, 'save_term_meta'));
        add_action('edited_' . self::TAXONOMY, array($this, 'save_term_meta'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_media'));
    }

    /**
     * Expose the per-lab fields to the REST API / block editor.
     */
    public function register_term_meta() {
        $rest_types = array(
            'rtm_team_logo' => 'integer',
        );
        foreach (array_keys($this->fields) as $key) {
            register_term_meta(self::TAXONOMY, $key, array(
                'single'       => true,
                'show_in_rest' => true,
                'type'         => isset($rest_types[$key]) ? $rest_types[$key] : 'string',
            ));
        }
        // Lead meta (string values for REST; lead IDs are stored as an array).
        register_term_meta(self::TAXONOMY, 'rtm_team_lead_type', array('single' => true, 'show_in_rest' => true, 'type' => 'string'));
        register_term_meta(self::TAXONOMY, 'rtm_team_pi', array('single' => true, 'show_in_rest' => true, 'type' => 'string'));
        register_term_meta(self::TAXONOMY, 'rtm_team_lead_ids', array('single' => true, 'show_in_rest' => false));
    }

    /**
     * Load the media library on the team add/edit screens (for the logo picker).
     */
    public function enqueue_media($hook) {
        if (($hook === 'edit-tags.php' || $hook === 'term.php')
            && isset($_REQUEST['taxonomy']) && $_REQUEST['taxonomy'] === self::TAXONOMY) {
            wp_enqueue_media();
        }
    }

    /* ----------------------------------------------------------------- *
     * Add-new term form (fields wrapped in .form-field divs)
     * ----------------------------------------------------------------- */
    public function add_form_fields($taxonomy) {
        ?>
        <div class="form-field">
            <label for="rtm_team_type"><?php esc_html_e('Type', 'research-team-manager'); ?></label>
            <select name="rtm_team_type" id="rtm_team_type">
                <option value=""><?php esc_html_e('— Select type —', 'research-team-manager'); ?></option>
                <?php foreach ($this->types() as $type): ?>
                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                <?php endforeach; ?>
            </select>
            <p><?php esc_html_e('What kind of unit this is (Lab, Centre, Institute, …).', 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_theme"><?php esc_html_e('Research Theme', 'research-team-manager'); ?></label>
            <select name="rtm_team_theme" id="rtm_team_theme">
                <option value=""><?php esc_html_e('— Select theme —', 'research-team-manager'); ?></option>
                <?php foreach ($this->themes() as $slug => $label): ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p><?php esc_html_e('Discipline grouping used on the labs directory.', 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_lead_type"><?php esc_html_e('Lead type', 'research-team-manager'); ?></label>
            <select name="rtm_team_lead_type" id="rtm_team_lead_type">
                <option value="person"><?php esc_html_e('Individual / people (team members)', 'research-team-manager'); ?></option>
                <option value="org"><?php esc_html_e('Organization', 'research-team-manager'); ?></option>
            </select>
            <p><?php esc_html_e('Is the lead one or more people (team members), or an organization?', 'research-team-manager'); ?></p>
        </div>
        <div class="form-field rtm-lead-person">
            <label><?php esc_html_e('Principal Investigator(s)', 'research-team-manager'); ?></label>
            <?php $this->lead_picker(array()); ?>
            <p>
                <?php esc_html_e('Search and click to add the member(s) who lead this team.', 'research-team-manager'); ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=rtm_team_member')); ?>" target="_blank"><?php esc_html_e('Add a new member', 'research-team-manager'); ?></a>
            </p>
        </div>
        <div class="form-field rtm-lead-org">
            <label for="rtm_team_pi"><?php esc_html_e('Organization name', 'research-team-manager'); ?></label>
            <input type="text" name="rtm_team_pi" id="rtm_team_pi" value="" />
            <p><?php esc_html_e('The organization that leads this team.', 'research-team-manager'); ?></p>
        </div>
        <?php $this->print_lead_script(); ?>
        <div class="form-field">
            <label><?php esc_html_e('Team Logo', 'research-team-manager'); ?></label>
            <?php $this->logo_field(0); ?>
        </div>
        <div class="form-field">
            <label for="rtm_team_intro"><?php esc_html_e('Intro', 'research-team-manager'); ?></label>
            <textarea name="rtm_team_intro" id="rtm_team_intro" rows="5" cols="50"></textarea>
            <p><?php esc_html_e('Short introduction shown at the top of the team page. Basic HTML allowed.', 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_scholar_id"><?php esc_html_e('Google Scholar ID', 'research-team-manager'); ?></label>
            <input type="text" name="rtm_team_scholar_id" id="rtm_team_scholar_id" value="" />
            <p><?php esc_html_e("This lab's Google Scholar user ID (e.g. m0_aWlQAAAAJ). Used to scope this team's publications.", 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_contact_email"><?php esc_html_e('Contact Email', 'research-team-manager'); ?></label>
            <input type="email" name="rtm_team_contact_email" id="rtm_team_contact_email" value="" />
        </div>
        <div class="form-field">
            <label for="rtm_team_website"><?php esc_html_e('Website / info link', 'research-team-manager'); ?></label>
            <input type="url" name="rtm_team_website" id="rtm_team_website" value="" />
            <p><?php esc_html_e("The lab's page (shown on the team page hero and the labs directory). May be a deep link with an #anchor.", 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_link_type"><?php esc_html_e('Link label', 'research-team-manager'); ?></label>
            <select name="rtm_team_link_type" id="rtm_team_link_type">
                <option value="website"><?php esc_html_e('Official lab website', 'research-team-manager'); ?></option>
                <option value="info"><?php esc_html_e('More information', 'research-team-manager'); ?></option>
            </select>
            <p><?php esc_html_e('How to label that link — a dedicated lab site, or a general info page.', 'research-team-manager'); ?></p>
        </div>
        <div class="form-field">
            <label for="rtm_team_location"><?php esc_html_e('Location', 'research-team-manager'); ?></label>
            <input type="text" name="rtm_team_location" id="rtm_team_location" value="" />
        </div>
        <div class="form-field">
            <label for="rtm_team_roster_group"><?php esc_html_e('Group members by', 'research-team-manager'); ?></label>
            <select name="rtm_team_roster_group" id="rtm_team_roster_group">
                <?php foreach ($this->roster_group_options() as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected('rtm_member_status', $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p><?php esc_html_e('How the Lab Team list is split into sections on the team page.', 'research-team-manager'); ?></p>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------- *
     * Edit term form (fields wrapped in .form-field table rows)
     * ----------------------------------------------------------------- */
    public function edit_form_fields($term, $taxonomy) {
        $type      = get_term_meta($term->term_id, 'rtm_team_type', true);
        $theme     = get_term_meta($term->term_id, 'rtm_team_theme', true);
        $pi        = get_term_meta($term->term_id, 'rtm_team_pi', true);
        $lead_type = get_term_meta($term->term_id, 'rtm_team_lead_type', true);
        $lead_ids  = get_term_meta($term->term_id, 'rtm_team_lead_ids', true);
        $lead_ids  = is_array($lead_ids) ? $lead_ids : array();
        $intro    = get_term_meta($term->term_id, 'rtm_team_intro', true);
        $scholar  = get_term_meta($term->term_id, 'rtm_team_scholar_id', true);
        $email     = get_term_meta($term->term_id, 'rtm_team_contact_email', true);
        $website   = get_term_meta($term->term_id, 'rtm_team_website', true);
        $link_type = get_term_meta($term->term_id, 'rtm_team_link_type', true);
        $location  = get_term_meta($term->term_id, 'rtm_team_location', true);
        $roster_group = get_term_meta($term->term_id, 'rtm_team_roster_group', true);
        $logo_id  = (int) get_term_meta($term->term_id, 'rtm_team_logo', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_type"><?php esc_html_e('Type', 'research-team-manager'); ?></label></th>
            <td>
                <select name="rtm_team_type" id="rtm_team_type">
                    <option value=""><?php esc_html_e('— Select type —', 'research-team-manager'); ?></option>
                    <?php foreach ($this->types() as $opt): ?>
                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($type, $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('What kind of unit this is (Lab, Centre, Institute, …).', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_theme"><?php esc_html_e('Research Theme', 'research-team-manager'); ?></label></th>
            <td>
                <select name="rtm_team_theme" id="rtm_team_theme">
                    <option value=""><?php esc_html_e('— Select theme —', 'research-team-manager'); ?></option>
                    <?php foreach ($this->themes() as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($theme, $slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Discipline grouping used on the labs directory.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_lead_type"><?php esc_html_e('Lead type', 'research-team-manager'); ?></label></th>
            <td>
                <select name="rtm_team_lead_type" id="rtm_team_lead_type">
                    <option value="person" <?php selected($lead_type ? $lead_type : 'person', 'person'); ?>><?php esc_html_e('Individual / people (team members)', 'research-team-manager'); ?></option>
                    <option value="org" <?php selected($lead_type, 'org'); ?>><?php esc_html_e('Organization', 'research-team-manager'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Is the lead one or more people (team members), or an organization?', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field rtm-lead-person">
            <th scope="row"><label><?php esc_html_e('Principal Investigator(s)', 'research-team-manager'); ?></label></th>
            <td>
                <?php $this->lead_picker($lead_ids); ?>
                <p class="description">
                    <?php esc_html_e('Search and click to add the member(s) who lead this team. Selecting a member also adds them to this team.', 'research-team-manager'); ?>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=rtm_team_member&rtm_team=' . (int) $term->term_id)); ?>" target="_blank"><?php esc_html_e('Add a new member', 'research-team-manager'); ?></a>
                </p>
            </td>
        </tr>
        <tr class="form-field rtm-lead-org">
            <th scope="row"><label for="rtm_team_pi"><?php esc_html_e('Organization name', 'research-team-manager'); ?></label></th>
            <td>
                <input type="text" name="rtm_team_pi" id="rtm_team_pi" value="<?php echo esc_attr($pi); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('The organization that leads this team.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <?php $this->print_lead_script(); ?>
        <tr class="form-field">
            <th scope="row"><label><?php esc_html_e('Team Logo', 'research-team-manager'); ?></label></th>
            <td><?php $this->logo_field($logo_id); ?></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_intro"><?php esc_html_e('Intro', 'research-team-manager'); ?></label></th>
            <td>
                <?php
                wp_editor($intro, 'rtm_team_intro', array(
                    'textarea_name' => 'rtm_team_intro',
                    'textarea_rows' => 6,
                    'media_buttons' => false,
                    'teeny'         => true,
                ));
                ?>
                <p class="description"><?php esc_html_e('Short introduction shown at the top of the team page.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_scholar_id"><?php esc_html_e('Google Scholar ID', 'research-team-manager'); ?></label></th>
            <td>
                <input type="text" name="rtm_team_scholar_id" id="rtm_team_scholar_id" value="<?php echo esc_attr($scholar); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e("This lab's Google Scholar user ID (e.g. m0_aWlQAAAAJ). Used to scope this team's publications.", 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_contact_email"><?php esc_html_e('Contact Email', 'research-team-manager'); ?></label></th>
            <td><input type="email" name="rtm_team_contact_email" id="rtm_team_contact_email" value="<?php echo esc_attr($email); ?>" class="regular-text" /></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_website"><?php esc_html_e('Website / info link', 'research-team-manager'); ?></label></th>
            <td>
                <input type="url" name="rtm_team_website" id="rtm_team_website" value="<?php echo esc_attr($website); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e("The lab's page (shown on the team page hero and the labs directory). May be a deep link with an #anchor.", 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_link_type"><?php esc_html_e('Link label', 'research-team-manager'); ?></label></th>
            <td>
                <select name="rtm_team_link_type" id="rtm_team_link_type">
                    <option value="website" <?php selected($link_type ? $link_type : 'website', 'website'); ?>><?php esc_html_e('Official lab website', 'research-team-manager'); ?></option>
                    <option value="info" <?php selected($link_type, 'info'); ?>><?php esc_html_e('More information', 'research-team-manager'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('How the link is labelled on the team page and directory.', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_location"><?php esc_html_e('Location', 'research-team-manager'); ?></label></th>
            <td><input type="text" name="rtm_team_location" id="rtm_team_location" value="<?php echo esc_attr($location); ?>" class="regular-text" /></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_roster_group"><?php esc_html_e('Group members by', 'research-team-manager'); ?></label></th>
            <td>
                <select name="rtm_team_roster_group" id="rtm_team_roster_group">
                    <?php foreach ($this->roster_group_options() as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($roster_group, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('How the Lab Team list is split into sections on the team page (e.g. Current vs Alumni).', 'research-team-manager'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Logo picker: a hidden attachment-ID input, a live preview and two buttons.
     * The supporting JS is printed once per request.
     */
    private function logo_field($logo_id) {
        $logo_id  = (int) $logo_id;
        $img       = $logo_id ? wp_get_attachment_image($logo_id, 'thumbnail') : '';
        ?>
        <div class="rtm-logo-field">
            <input type="hidden" name="rtm_team_logo" class="rtm-logo-id" value="<?php echo esc_attr($logo_id); ?>" />
            <div class="rtm-logo-preview" style="margin-bottom:8px;"><?php echo $img; ?></div>
            <button type="button" class="button rtm-logo-select"><?php esc_html_e('Select logo', 'research-team-manager'); ?></button>
            <button type="button" class="button rtm-logo-remove" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'research-team-manager'); ?></button>
        </div>
        <?php
        $this->print_logo_script();
    }

    private function print_logo_script() {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <script>
        (function($){
            $(document).on('click', '.rtm-logo-select', function(e){
                e.preventDefault();
                var $wrap = $(this).closest('.rtm-logo-field');
                var frame = wp.media({ title: 'Select team logo', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    $wrap.find('.rtm-logo-id').val(att.id);
                    var src = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                    $wrap.find('.rtm-logo-preview').html('<img src="' + src + '" style="max-width:120px;height:auto;" />');
                    $wrap.find('.rtm-logo-remove').show();
                });
                frame.open();
            });
            $(document).on('click', '.rtm-logo-remove', function(e){
                e.preventDefault();
                var $wrap = $(this).closest('.rtm-logo-field');
                $wrap.find('.rtm-logo-id').val('');
                $wrap.find('.rtm-logo-preview').empty();
                $(this).hide();
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Persist the term meta on create/edit.
     */
    public function save_term_meta($term_id) {
        if (!current_user_can('manage_categories')) {
            return;
        }

        foreach ($this->fields as $key => $sanitize) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $raw   = wp_unslash($_POST[$key]);
            $value = call_user_func($sanitize, $raw);

            if ($value === '' || $value === 0 || $value === null) {
                delete_term_meta($term_id, $key);
            } else {
                update_term_meta($term_id, $key, $value);
            }
        }

        $this->save_lead($term_id);
    }

    /**
     * Save the structured lead: an organization name, or one/more member posts
     * designated as the team's PI(s). Selected members are also added to the
     * team and flagged as leads so they appear (as PI) in the roster.
     */
    private function save_lead($term_id) {
        if (!isset($_POST['rtm_team_lead_type'])) {
            return;
        }
        $lead_type = ($_POST['rtm_team_lead_type'] === 'org') ? 'org' : 'person';
        update_term_meta($term_id, 'rtm_team_lead_type', $lead_type);

        if ($lead_type === 'org') {
            $org = isset($_POST['rtm_team_pi']) ? sanitize_text_field(wp_unslash($_POST['rtm_team_pi'])) : '';
            if ($org === '') {
                delete_term_meta($term_id, 'rtm_team_pi');
            } else {
                update_term_meta($term_id, 'rtm_team_pi', $org);
            }
            delete_term_meta($term_id, 'rtm_team_lead_ids');
            return;
        }

        // Individual / people: store selected member IDs.
        $ids = isset($_POST['rtm_team_lead_ids']) ? array_map('intval', (array) $_POST['rtm_team_lead_ids']) : array();
        $ids = array_values(array_filter(array_unique($ids)));

        if ($ids) {
            update_term_meta($term_id, 'rtm_team_lead_ids', $ids);
            foreach ($ids as $mid) {
                if (get_post_type($mid) !== 'rtm_team_member') {
                    continue;
                }
                wp_set_object_terms($mid, array((int) $term_id), self::TAXONOMY, true); // ensure membership
                update_post_meta($mid, '_rtm_is_lead', '1');
                if (!get_post_meta($mid, '_rtm_position', true)) {
                    update_post_meta($mid, '_rtm_position', 'Principal Investigator');
                }
            }
        } else {
            delete_term_meta($term_id, 'rtm_team_lead_ids');
        }
        delete_term_meta($term_id, 'rtm_team_pi'); // names now come from the linked members
    }
}
