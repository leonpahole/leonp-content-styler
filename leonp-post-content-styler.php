<?php
/*
Plugin Name: Post content styler
Description: This plugin adds checkboxes to post edit, which allows admin to bold, italic or uppercase whole text in a post. It also allows whole post to get certain CSS style and it provides an admin ui with shortcode to add a style to certain text in shortcode.
Author: Leon Pahole
*/

if (!class_exists('LeonP_ContentStyler')) {

    class LeonP_ContentStyler
    {
        private $mbox_slug = 'leonp_styler_mbox';

        private $information = array(
            'bold' => array(
                'is_on' => false,
                'slug' => 'leonp_styler_bold',
                'text' => 'Make text bold.',
                'meta_field' => 'leonp_is_bold',
                'class_name' => 'bold-text',
                'shortcode_class' => 'bold'
            ),
            'italic' => array(
                'is_on' => false,
                'slug' => 'leonp_styler_italic',
                'text' => 'Make text italic.',
                'meta_field' => 'leonp_is_italic',
                'class_name' => 'italic-text',
                'shortcode_class' => 'italic'
            ),
            'uppercase' => array(
                'is_on' => false,
                'slug' => 'leonp_styler_uppercase',
                'text' => 'Make text uppercase.',
                'meta_field' => 'leonp_is_uppercase',
                'class_name' => 'uppercase-text',
                'shortcode_class' => 'uppercase'
            )
        );

        private $default_custom_styles_count = 1;
        private $custom_styles_count = 1;

        function __construct()
        {
            // on end of rendering meta boxes, add ours
            add_action('add_meta_boxes', array($this, 'add_styler_meta_boxes'));
            add_action('save_post', array($this, 'save_styler_attributes'));

            add_filter('get_the_excerpt', array($this, 'apply_selected_styles'));
            add_filter('the_content', array($this, 'apply_selected_styles'));

            add_action('wp_enqueue_scripts', array($this, 'load_style'));

            add_action('admin_menu', array($this, 'add_styler_submenu'));

            add_shortcode('leonp_styler', array($this, 'styler_shortcode'));
        }

        public function set_custom_styles_count()
        {
            $parsed_count = intval(get_option('leonp_styler_custom_style_count'));

            if ($parsed_count) {
                $this->custom_styles_count = $parsed_count;
            } else {
                $this->custom_styles_count = $this->default_custom_styles_count;
            }
        }

        // load css
        public function load_style()
        {
            wp_enqueue_style('leonp_reverse_styles', plugins_url('leonp-post-content-styler.css', __FILE__));
        }

        // add meta box after all other meta boxes
        public function add_styler_meta_boxes()
        {
            add_meta_box($this->mbox_slug, 'Content styler', array($this, 'render_styler_meta_box'), 'post',
                'side');
        }

        // render meta box html
        public function render_styler_meta_box($post)
        {
            $this->set_custom_styles_count();

            foreach ($this->information as $info_key => $info) {
                $this->information[$info_key]['is_on'] = (bool)get_post_meta($post->ID, $info['meta_field'], true);
            }

            $custom_styles_checked = array();

            for ($i = 1; $i <= $this->custom_styles_count; $i++) {
                $custom_styles_checked[$i] = (bool)get_post_meta($post->ID, "leonp_custom_{$i}", true);
            }

            $checked_text = 'checked=checked';

            ?>
            <?php
            foreach ($this->information as $info) {
                ?>
                <label>
                    <input type="checkbox" name="<?php echo $info['slug']; ?>"
                        <?php echo $info['is_on'] ? $checked_text : ''; ?>
                           value="on"/>
                    <?php echo $info['text'] ?>
                </label>
                <br>
                <br>
                <?php
            }

            for ($i = 1; $i <= count($custom_styles_checked); $i++) {
                ?>
                <label>
                    <input type="checkbox" name="leonp_custom_<?php echo $i ?>"
                        <?php echo $custom_styles_checked[$i] ? $checked_text : ''; ?>
                           value="on"/>
                    Apply custom style <?php echo $i ?>.
                </label>
                <br>
                <br>
                <?php
            }
        }

        // save checked checkboxes
        public function save_styler_attributes($post_id)
        {
            $this->set_custom_styles_count();

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return;

            if (defined('DOING_AJAX') && DOING_AJAX)
                return;

            if (!current_user_can('edit_post', $post_id))
                return;

            foreach ($this->information as $info) {

                if (isset($_POST[$info['slug']]) && $_POST[$info['slug']] == 'on') {

                    update_post_meta($post_id, $info['meta_field'], true);
                } else {
                    update_post_meta($post_id, $info['meta_field'], false);
                }
            }

            for ($i = 1; $i <= $this->custom_styles_count; $i++) {

                if (isset($_POST["leonp_custom_{$i}"]) && $_POST["leonp_custom_{$i}"] == 'on') {

                    update_post_meta($post_id, "leonp_custom_{$i}", true);
                } else {
                    update_post_meta($post_id, "leonp_custom_{$i}", false);
                }
            }
        }

        // apply checked styles (bold, italic, uppercase, custom) on text (content, excerpt)
        public function apply_selected_styles($text)
        {
            global $post;

            $this->set_custom_styles_count();

            $classes_applied = '';
            $inline_styles_applied = '';

            foreach ($this->information as $info) {

                if ((bool)get_post_meta($post->ID, $info['meta_field'], true)) {

                    $classes_applied .= $info['class_name'] . ' ';
                }
            }

            for ($i = 1; $i <= $this->custom_styles_count; $i++) {

                if ((bool)get_post_meta($post->ID, "leonp_custom_{$i}", true)) {

                    $style = get_option("leonp_styler_custom_style_{$i}");

                    if (!empty($style)) {
                        $inline_styles_applied .= $style;
                    }
                }
            }

            if (!empty($classes_applied) || !empty($inline_styles_applied)) {

                foreach ($this->information as $info) {

                    $text = '<span style="' . $inline_styles_applied . '" class="' . $classes_applied . '">' . $text . '</span>';
                }
            }

            return $text;
        }

        public function add_styler_submenu()
        {
            $this->set_custom_styles_count();

            add_settings_field(
                "leonp_styler_custom_style_count",
                "Styler custom style count",
                array($this, 'render_custom_style_count_field'),
                'reading',
                'default'
            );

            register_setting('reading', "leonp_styler_custom_style_count");


            for ($i = 1; $i <= $this->custom_styles_count; $i++) {

                add_settings_field(
                    "leonp_styler_custom_style_{$i}",
                    "Styler custom style {$i}",
                    array($this, 'render_custom_style_field'),
                    'reading',
                    'default',
                    $i
                );

                register_setting('reading', "leonp_styler_custom_style_{$i}");
            }
        }

        public function render_custom_style_count_field()
        {
            ?>
            <input id="leonp_styler_custom_style_count" type="number" min="1"
                   name="leonp_styler_custom_style_count"
                   value="<?php echo $this->custom_styles_count; ?>">
            <?php
        }

        public function render_custom_style_field($index)
        {
            ?>
            <textarea
                    name="leonp_styler_custom_style_<?php echo $index; ?>"><?php echo get_option("leonp_styler_custom_style_{$index}"); ?></textarea>
            <?php
        }

        // shortcode for styles
        // atts: styles -> comma separated list of style names
        // example: [leonp_styler styles=uppercase,custom1,custom3,bold]Hellooooooooo[/leonp_styler]
        public function styler_shortcode($atts, $content, $tag)
        {

            $attr = shortcode_atts(array(
                'styles' => null
            ), $atts, $tag);

            if ($attr['styles']) {

                $styles = explode(',', $attr['styles']);

                $inline_styles = '';
                $classes = '';

                foreach ($styles as $style) {

                    if (substr($style, 0, strlen('custom')) == 'custom') {

                        $replaced = str_replace('custom', '', $style);
                        $style_number = intval($replaced);

                        if ($style_number) {
                            $inline_styles .= get_option("leonp_styler_custom_style_{$style_number}");
                        }
                    } else {

                        foreach ($this->information as $info) {
                            if ($style == $info['shortcode_class']) {
                                $classes .= $info['class_name'] . ' ';
                                break;
                            }
                        }
                    }
                }

                $content = "<span style=\"{$inline_styles}\" class=\"{$classes}\">{$content}</span>";
            }

            return $content;
        }
    }

    $LeonP_ContentStyler = new LeonP_ContentStyler();
}
