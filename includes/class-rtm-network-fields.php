<?php
/**
 * Configurable researcher directory fields.
 *
 * Administrators define the directory's fields under Research Labs →
 * Directory Fields instead of a fixed, institution-specific list:
 *   - choice: a list of values (a taxonomy `rtmn_{key}`), usable as a
 *             directory filter and a list-table column;
 *   - text:   free text per member (post meta `_rtmn_field_{key}`).
 * Research topic (the Research Areas taxonomy) is a built-in choice field.
 *
 * The same screen holds the wording and clean-up rules for records imported
 * from an external directory (source names, institution aliases, …).
 */

if (!defined('ABSPATH')) {
    exit;
}

class RTM_Network_Fields {

    const OPTION          = 'rtm_network_fields';
    const SOURCES_OPTION  = 'rtm_network_sources';
    const PAGE            = 'rtm-directory-fields';
    const TOPIC_TAXONOMY  = 'rtm_research_area';

    /** Keys that would clash with built-in fields or query arguments. */
    const RESERVED = array('topic', 'topics', 'q', 'search', 'affiliations', 'portrait', 'position', 'institution_name', 'labs', 'source', 'links', 'contact', 'biography');

    /* ------------------------------------------------------------------
     * Configuration
     * ---------------------------------------------------------------- */

    /** Generic starter fields for a new directory. */
    private static function default_fields() {
        return array(
            self::field('faculty', 'Faculty', 'Faculties'),
            self::field('department', 'Department', 'Departments'),
            self::topic_field('Research topic'),
            self::field('theme', 'Research theme', 'Research Themes'),
            self::field('institution', 'Institution / affiliation', 'Institutions / Affiliations'),
        );
    }

    /** The fields the University Research Network add-on shipped with. */
    private static function legacy_fields() {
        return array(
            self::field('faculty', 'Faculty', 'Faculties'),
            self::field('department', 'Department', 'Departments'),
            self::field('appointment', 'Appointment type', 'Appointment Types'),
            self::field('supervision', 'Graduate supervision', 'Graduate Supervision'),
            self::topic_field('Research topic'),
            self::field('theme', 'Research theme', 'Research Themes'),
            self::field('membership', 'HRI membership', 'HRI Membership Categories'),
            self::field('institution', 'Institution / affiliation', 'Institutions / Affiliations'),
        );
    }

    private static function field($key, $label, $plural, $type = 'choice') {
        return array('key' => $key, 'label' => $label, 'plural' => $plural, 'type' => $type, 'filter' => $type === 'choice', 'column' => false);
    }

    private static function topic_field($label) {
        return array('key' => 'topic', 'label' => $label, 'plural' => 'Research Areas', 'type' => 'topic', 'filter' => true, 'column' => true);
    }

    /** Wording and clean-up for imported records, as the add-on had them. */
    private static function legacy_sources() {
        return array(
            'directory'     => 'UNBC',
            'network'       => 'HRI',
            'institution'   => 'University of Northern British Columbia',
            'portrait_base' => 'https://www.unbc.ca/sites/',
            'aliases'       => "UNBC = University of Northern British Columbia\nUniveristy of Northern British Columbia = University of Northern British Columbia\nUBC = University of British Columbia\nNorthern Health Authority = Northern Health",
            'ignore'        => "Associate Professor\nSTAT 240",
        );
    }

    private static function default_sources() {
        return array('directory' => '', 'network' => '', 'institution' => '', 'portrait_base' => '', 'aliases' => '', 'ignore' => '');
    }

    /**
     * Seed the configuration once: sites that already hold directory data
     * keep the add-on's fields and wording; new sites get generic fields.
     */
    public static function seed($legacy) {
        if (get_option(self::OPTION, null) === null) {
            update_option(self::OPTION, $legacy ? self::legacy_fields() : self::default_fields());
        }
        if (get_option(self::SOURCES_OPTION, null) === null) {
            update_option(self::SOURCES_OPTION, $legacy ? self::legacy_sources() : self::default_sources());
        }
    }

    /** All configured fields, in display order. */
    public static function all() {
        $fields = get_option(self::OPTION, null);
        if (!is_array($fields)) {
            $fields = self::default_fields();
        }
        $out = array();
        foreach ($fields as $field) {
            $field = self::normalize($field);
            if ($field) {
                $out[$field['key']] = $field;
            }
        }
        return apply_filters('rtm_network_fields', $out);
    }

    public static function normalize($field) {
        if (!is_array($field) || empty($field['key'])) {
            return null;
        }
        $key  = sanitize_key($field['key']);
        $type = in_array($field['type'] ?? '', array('choice', 'text', 'topic'), true) ? $field['type'] : 'choice';
        if ($key === 'topic') {
            $type = 'topic';
        }
        $label = trim((string) ($field['label'] ?? ''));
        return array(
            'key'      => $key,
            'label'    => $label !== '' ? $label : $key,
            'plural'   => trim((string) ($field['plural'] ?? '')) !== '' ? trim($field['plural']) : ($label !== '' ? $label : $key),
            'type'     => $type,
            'filter'   => $type !== 'text' && !empty($field['filter']),
            'column'   => !empty($field['column']),
            'taxonomy' => $type === 'topic' ? self::TOPIC_TAXONOMY : ($type === 'choice' ? 'rtmn_' . $key : ''),
            'meta_key' => $type === 'text' ? '_rtmn_field_' . $key : '',
            'query'    => 'rn_' . $key,
        );
    }

    /** Choice fields (including Research topic): taxonomy => field. */
    public static function choice_fields() {
        $out = array();
        foreach (self::all() as $field) {
            if ($field['taxonomy']) {
                $out[$field['taxonomy']] = $field;
            }
        }
        return $out;
    }

    /** Text fields: key => field. */
    public static function text_fields() {
        return array_filter(self::all(), function ($field) {
            return $field['type'] === 'text';
        });
    }

    /** Directory filters: query argument => taxonomy. */
    public static function filters() {
        $out = array();
        foreach (self::all() as $field) {
            if ($field['filter'] && $field['taxonomy']) {
                $out[$field['query']] = $field['taxonomy'];
            }
        }
        return $out;
    }

    /** Taxonomy of a configured choice field, or '' when the field is not configured. */
    public static function taxonomy_for($key) {
        $fields = self::all();
        return isset($fields[$key]) ? $fields[$key]['taxonomy'] : '';
    }

    /** Import wording and clean-up settings. */
    public static function sources() {
        $value = get_option(self::SOURCES_OPTION, array());
        return array_merge(self::default_sources(), is_array($value) ? $value : array());
    }

    /**
     * Normalized institution names: alias lines "Alias = Name" match a whole
     * word or phrase, case-insensitively; ignored values are dropped.
     */
    public static function normalize_institution($name) {
        $sources = self::sources();
        foreach (preg_split('/\R/', (string) $sources['ignore']) as $ignore) {
            if (trim($ignore) !== '' && strcasecmp(trim($ignore), $name) === 0) {
                return '';
            }
        }
        foreach (preg_split('/\R/', (string) $sources['aliases']) as $line) {
            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' &&
                preg_match('/(?<![\p{L}\p{N}])' . preg_quote($parts[0], '/') . '(?![\p{L}\p{N}])/iu', $name)) {
                return $parts[1];
            }
        }
        return $name;
    }

    /* ------------------------------------------------------------------
     * Settings screen
     * ---------------------------------------------------------------- */

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'add_page'), 30);
    }

    public static function add_page() {
        add_submenu_page('edit.php?post_type=rtm_team_member', __('Directory Fields', 'research-team-manager'), __('Directory Fields', 'research-team-manager'), 'manage_options', self::PAGE, array(__CLASS__, 'render_page'));
    }

    public static function page_url() {
        return admin_url('edit.php?post_type=rtm_team_member&page=' . self::PAGE);
    }

    private static function handle_post() {
        if (empty($_POST['rtm_directory_fields_nonce']) || !current_user_can('manage_options') || !check_admin_referer('rtm_directory_fields', 'rtm_directory_fields_nonce')) {
            return '';
        }
        $rows   = (array) ($_POST['rtm_fields'] ?? array());
        $fields = array();
        $seen   = array();
        $errors = array();
        foreach ($rows as $row) {
            $row = wp_unslash((array) $row);
            if (!empty($row['delete'])) {
                continue;
            }
            $label = sanitize_text_field($row['label'] ?? '');
            $key   = sanitize_key($row['key'] ?? '');
            if ($key === '' && $label !== '') {
                $key = substr(sanitize_key(str_replace(array(' ', '-'), '_', strtolower(remove_accents($label)))), 0, 20);
            }
            if ($key === '' && $label === '') {
                continue; // empty "add" row
            }
            $type = ($row['type'] ?? '') === 'text' ? 'text' : 'choice';
            if ($key === 'topic' || ($row['type'] ?? '') === 'topic') {
                $key  = 'topic';
                $type = 'topic';
            } elseif (in_array($key, self::RESERVED, true) || !preg_match('/^[a-z][a-z0-9_]{0,19}$/', $key)) {
                $errors[] = sprintf(__('“%s” needs a different key (lowercase letters, numbers and underscores, starting with a letter, up to 20 characters).', 'research-team-manager'), $label !== '' ? $label : $key);
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = sprintf(__('The key “%s” is used twice; only the first is kept.', 'research-team-manager'), $key);
                continue;
            }
            $seen[$key] = true;
            $fields[] = array(
                'key'    => $key,
                'label'  => $label !== '' ? $label : $key,
                'plural' => sanitize_text_field($row['plural'] ?? ''),
                'type'   => $type,
                'filter' => !empty($row['filter']),
                'column' => !empty($row['column']),
                'order'  => (int) ($row['order'] ?? 0),
            );
        }
        if (!isset($seen['topic'])) {
            $fields[] = array_merge(self::topic_field('Research topic'), array('filter' => false, 'order' => 999));
        }
        usort($fields, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        foreach ($fields as &$field) {
            unset($field['order']);
        }
        unset($field);
        update_option(self::OPTION, $fields);

        $s = (array) wp_unslash($_POST['rtm_sources'] ?? array());
        update_option(self::SOURCES_OPTION, array(
            'directory'     => sanitize_text_field($s['directory'] ?? ''),
            'network'       => sanitize_text_field($s['network'] ?? ''),
            'institution'   => sanitize_text_field($s['institution'] ?? ''),
            'portrait_base' => esc_url_raw($s['portrait_base'] ?? '', array('https')),
            'aliases'       => sanitize_textarea_field($s['aliases'] ?? ''),
            'ignore'        => sanitize_textarea_field($s['ignore'] ?? ''),
        ));

        // Removed fields stop matching searches; new text fields start to.
        if (class_exists('RTM_Network') && RTM_Network::is_enabled()) {
            RTM_Network::reindex_all();
        }

        $notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Directory fields saved. New fields appear on member screens and in the Researcher Field and Researcher Filters blocks.', 'research-team-manager') . '</p></div>';
        foreach ($errors as $error) {
            $notice .= '<div class="notice notice-warning"><p>' . esc_html($error) . '</p></div>';
        }
        return $notice;
    }

    public static function render_page() {
        $notice  = self::handle_post();
        $fields  = array_values(self::all());
        $sources = self::sources();
        $enabled = class_exists('RTM_Network') && RTM_Network::is_enabled();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Directory Fields', 'research-team-manager'); ?></h1>
            <?php echo $notice; // Built from escaped strings above. ?>
            <?php if (!$enabled): ?>
                <div class="notice notice-info inline"><p><?php printf(
                    /* translators: %s: Team Settings URL */
                    wp_kses_post(__('The researcher directory is off. Turn it on in <a href="%s">Team Settings</a> to use these fields.', 'research-team-manager')),
                    esc_url(admin_url('edit.php?post_type=rtm_team_member&page=rtm-team-settings'))
                ); ?></p></div>
            <?php endif; ?>
            <p class="description" style="max-width:820px;"><?php esc_html_e('The extra details you record for each researcher. A “List of choices” field holds values you pick from (for example Faculty or Department) and can be offered as a filter on the public directory. A “Text” field holds a short free-text value. Removing a field hides it; its values stay in the database and return if you add a field with the same key again.', 'research-team-manager'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('rtm_directory_fields', 'rtm_directory_fields_nonce'); ?>
                <table class="widefat striped" style="max-width:1100px; margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:80px;"><?php esc_html_e('Order', 'research-team-manager'); ?></th>
                            <th><?php esc_html_e('Name', 'research-team-manager'); ?></th>
                            <th><?php esc_html_e('Plural (admin menus)', 'research-team-manager'); ?></th>
                            <th style="width:140px;"><?php esc_html_e('Key', 'research-team-manager'); ?></th>
                            <th style="width:150px;"><?php esc_html_e('Type', 'research-team-manager'); ?></th>
                            <th style="width:90px;"><?php esc_html_e('Directory filter', 'research-team-manager'); ?></th>
                            <th style="width:90px;"><?php esc_html_e('List column', 'research-team-manager'); ?></th>
                            <th style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ($fields as $field):
                            $is_topic = $field['type'] === 'topic';
                            ?>
                            <tr>
                                <td><input type="number" name="rtm_fields[<?php echo $i; ?>][order]" value="<?php echo ($i + 1) * 10; ?>" style="width:72px;" /></td>
                                <td><input type="text" class="regular-text" style="width:100%;" name="rtm_fields[<?php echo $i; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" /></td>
                                <td><?php if ($is_topic): ?><span class="description"><?php esc_html_e('Research Areas', 'research-team-manager'); ?></span><?php else: ?><input type="text" style="width:100%;" name="rtm_fields[<?php echo $i; ?>][plural]" value="<?php echo esc_attr($field['plural']); ?>" /><?php endif; ?></td>
                                <td><code><?php echo esc_html($field['key']); ?></code><input type="hidden" name="rtm_fields[<?php echo $i; ?>][key]" value="<?php echo esc_attr($field['key']); ?>" /></td>
                                <td>
                                    <?php if ($is_topic): ?>
                                        <?php esc_html_e('Research topic (built in)', 'research-team-manager'); ?><input type="hidden" name="rtm_fields[<?php echo $i; ?>][type]" value="topic" />
                                    <?php else: ?>
                                        <?php echo $field['type'] === 'text' ? esc_html__('Text', 'research-team-manager') : esc_html__('List of choices', 'research-team-manager'); ?>
                                        <input type="hidden" name="rtm_fields[<?php echo $i; ?>][type]" value="<?php echo esc_attr($field['type']); ?>" />
                                    <?php endif; ?>
                                </td>
                                <td><?php if ($field['type'] !== 'text'): ?><input type="checkbox" name="rtm_fields[<?php echo $i; ?>][filter]" value="1" <?php checked($field['filter']); ?> /><?php endif; ?></td>
                                <td><?php if (!$is_topic): ?><input type="checkbox" name="rtm_fields[<?php echo $i; ?>][column]" value="1" <?php checked($field['column']); ?> /><?php else: ?><span class="description">—</span><?php endif; ?></td>
                                <td>
                                    <?php if ($field['taxonomy']): ?><a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $field['taxonomy'] . '&post_type=rtm_team_member')); ?>"><?php esc_html_e('Manage values', 'research-team-manager'); ?></a><br /><?php endif; ?>
                                    <?php if (!$is_topic): ?><label><input type="checkbox" name="rtm_fields[<?php echo $i; ?>][delete]" value="1" /> <?php esc_html_e('Remove', 'research-team-manager'); ?></label><?php endif; ?>
                                </td>
                            </tr>
                            <?php
                            $i++;
                        endforeach;
                        for ($n = 0; $n < 3; $n++, $i++):
                            ?>
                            <tr>
                                <td><input type="number" name="rtm_fields[<?php echo $i; ?>][order]" value="<?php echo ($i + 1) * 10; ?>" style="width:72px;" /></td>
                                <td><input type="text" style="width:100%;" name="rtm_fields[<?php echo $i; ?>][label]" value="" placeholder="<?php esc_attr_e('New field, e.g. Campus', 'research-team-manager'); ?>" /></td>
                                <td><input type="text" style="width:100%;" name="rtm_fields[<?php echo $i; ?>][plural]" value="" placeholder="<?php esc_attr_e('e.g. Campuses', 'research-team-manager'); ?>" /></td>
                                <td><input type="text" style="width:100%;" name="rtm_fields[<?php echo $i; ?>][key]" value="" placeholder="<?php esc_attr_e('auto', 'research-team-manager'); ?>" maxlength="20" /></td>
                                <td><select name="rtm_fields[<?php echo $i; ?>][type]"><option value="choice"><?php esc_html_e('List of choices', 'research-team-manager'); ?></option><option value="text"><?php esc_html_e('Text', 'research-team-manager'); ?></option></select></td>
                                <td><input type="checkbox" name="rtm_fields[<?php echo $i; ?>][filter]" value="1" checked /></td>
                                <td><input type="checkbox" name="rtm_fields[<?php echo $i; ?>][column]" value="1" /></td>
                                <td></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Leave the key blank to create it from the name. Keys cannot be changed later. Save to add more rows.', 'research-team-manager'); ?></p>

                <h2 style="margin-top:2em;"><?php esc_html_e('Imported records', 'research-team-manager'); ?></h2>
                <p class="description" style="max-width:820px;"><?php esc_html_e('Only used for members imported from an external university directory or research network. Leave blank if you add researchers by hand.', 'research-team-manager'); ?></p>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="rtm_src_directory"><?php esc_html_e('Directory source name', 'research-team-manager'); ?></label></th>
                        <td><input type="text" class="regular-text" id="rtm_src_directory" name="rtm_sources[directory]" value="<?php echo esc_attr($sources['directory']); ?>" placeholder="<?php esc_attr_e('e.g. the university’s short name', 'research-team-manager'); ?>" />
                        <p class="description"><?php esc_html_e('Shown as “… profile”, “…-listed email” and “As reported by …” for imported directory records.', 'research-team-manager'); ?></p></td></tr>
                    <tr><th scope="row"><label for="rtm_src_network"><?php esc_html_e('Network source name', 'research-team-manager'); ?></label></th>
                        <td><input type="text" class="regular-text" id="rtm_src_network" name="rtm_sources[network]" value="<?php echo esc_attr($sources['network']); ?>" />
                        <p class="description"><?php esc_html_e('Shown as “…-supplied profile” and “… snapshot” for records imported from a research network.', 'research-team-manager'); ?></p></td></tr>
                    <tr><th scope="row"><label for="rtm_src_institution"><?php esc_html_e('Default institution', 'research-team-manager'); ?></label></th>
                        <td><input type="text" class="regular-text" id="rtm_src_institution" name="rtm_sources[institution]" value="<?php echo esc_attr($sources['institution']); ?>" />
                        <p class="description"><?php esc_html_e('Shown for imported directory records that list no affiliation.', 'research-team-manager'); ?></p></td></tr>
                    <tr><th scope="row"><label for="rtm_src_portrait"><?php esc_html_e('Trusted portrait address', 'research-team-manager'); ?></label></th>
                        <td><input type="url" class="regular-text" id="rtm_src_portrait" name="rtm_sources[portrait_base]" value="<?php echo esc_attr($sources['portrait_base']); ?>" placeholder="https://" />
                        <p class="description"><?php esc_html_e('Imported portrait images are shown only when their address starts with this (https only). Blank: never.', 'research-team-manager'); ?></p></td></tr>
                    <tr><th scope="row"><label for="rtm_src_aliases"><?php esc_html_e('Institution aliases', 'research-team-manager'); ?></label></th>
                        <td><textarea id="rtm_src_aliases" name="rtm_sources[aliases]" rows="5" class="large-text code"><?php echo esc_textarea($sources['aliases']); ?></textarea>
                        <p class="description"><?php esc_html_e('One per line: “Alias = Institution name”. An affiliation containing the alias (as a whole word) is filed under that institution.', 'research-team-manager'); ?></p></td></tr>
                    <tr><th scope="row"><label for="rtm_src_ignore"><?php esc_html_e('Not institutions', 'research-team-manager'); ?></label></th>
                        <td><textarea id="rtm_src_ignore" name="rtm_sources[ignore]" rows="3" class="large-text code"><?php echo esc_textarea($sources['ignore']); ?></textarea>
                        <p class="description"><?php esc_html_e('One per line. Affiliation values that are not institutions (for example a job title entered by mistake) are left out of the Institution field.', 'research-team-manager'); ?></p></td></tr>
                </table>
                <?php submit_button(__('Save directory fields', 'research-team-manager')); ?>
            </form>
        </div>
        <?php
    }
}
