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
    private $fields = array(
        'rtm_team_pi'            => 'sanitize_text_field',
        'rtm_team_logo'          => 'absint',
        'rtm_team_intro'         => 'wp_kses_post',
        'rtm_team_scholar_id'    => 'sanitize_text_field',
        'rtm_team_contact_email' => 'sanitize_email',
        'rtm_team_website'       => 'esc_url_raw',
        'rtm_team_location'      => 'sanitize_text_field',
    );

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
            <label for="rtm_team_pi"><?php esc_html_e('Principal Investigator', 'research-team-manager'); ?></label>
            <input type="text" name="rtm_team_pi" id="rtm_team_pi" value="" />
            <p><?php esc_html_e('Lab lead / PI name shown on the team page.', 'research-team-manager'); ?></p>
        </div>
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
            <label for="rtm_team_website"><?php esc_html_e('Website', 'research-team-manager'); ?></label>
            <input type="url" name="rtm_team_website" id="rtm_team_website" value="" />
        </div>
        <div class="form-field">
            <label for="rtm_team_location"><?php esc_html_e('Location', 'research-team-manager'); ?></label>
            <input type="text" name="rtm_team_location" id="rtm_team_location" value="" />
        </div>
        <?php
    }

    /* ----------------------------------------------------------------- *
     * Edit term form (fields wrapped in .form-field table rows)
     * ----------------------------------------------------------------- */
    public function edit_form_fields($term, $taxonomy) {
        $pi       = get_term_meta($term->term_id, 'rtm_team_pi', true);
        $intro    = get_term_meta($term->term_id, 'rtm_team_intro', true);
        $scholar  = get_term_meta($term->term_id, 'rtm_team_scholar_id', true);
        $email    = get_term_meta($term->term_id, 'rtm_team_contact_email', true);
        $website  = get_term_meta($term->term_id, 'rtm_team_website', true);
        $location = get_term_meta($term->term_id, 'rtm_team_location', true);
        $logo_id  = (int) get_term_meta($term->term_id, 'rtm_team_logo', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_pi"><?php esc_html_e('Principal Investigator', 'research-team-manager'); ?></label></th>
            <td>
                <input type="text" name="rtm_team_pi" id="rtm_team_pi" value="<?php echo esc_attr($pi); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Lab lead / PI name shown on the team page.', 'research-team-manager'); ?></p>
            </td>
        </tr>
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
            <th scope="row"><label for="rtm_team_website"><?php esc_html_e('Website', 'research-team-manager'); ?></label></th>
            <td><input type="url" name="rtm_team_website" id="rtm_team_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="rtm_team_location"><?php esc_html_e('Location', 'research-team-manager'); ?></label></th>
            <td><input type="text" name="rtm_team_location" id="rtm_team_location" value="<?php echo esc_attr($location); ?>" class="regular-text" /></td>
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
    }
}
