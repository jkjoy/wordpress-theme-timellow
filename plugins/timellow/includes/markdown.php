<?php

if (!defined('ABSPATH')) {
    exit;
}

function timellow_md_enabled() {
    return !empty(timellow_plugin_options()['markdown_editor']);
}

function timellow_md_post_types() {
    return apply_filters('timellow_markdown_post_types', ['post', 'page', 'timellow_moment']);
}

function timellow_md_supports($post_type) {
    return in_array($post_type, timellow_md_post_types(), true);
}

function timellow_md_setup_screen($screen) {
    if (timellow_md_enabled() && $screen && $screen->base === 'post' && timellow_md_supports($screen->post_type)) {
        remove_post_type_support($screen->post_type, 'editor');
    }
}
add_action('current_screen', 'timellow_md_setup_screen');

function timellow_md_mode($post) {
    $post = get_post($post);
    if (!$post) return 'html';
    $mode = get_post_meta($post->ID, '_timellow_markdown_mode', true);
    if (in_array($mode, ['markdown', 'html'], true)) return $mode;
    return $post->post_status === 'auto-draft' && trim($post->post_content) === '' ? 'markdown' : 'html';
}

function timellow_md_render($source) {
    require_once TIMELLOW_PLUGIN_DIR . '/vendor/Parsedown.php';
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    return wp_kses_post($parser->text((string) $source));
}

function timellow_md_filter_content($content) {
    global $post;
    if ($post instanceof WP_Post && timellow_md_mode($post) === 'markdown') {
        return timellow_md_render($content);
    }
    return $content;
}
add_filter('the_content', 'timellow_md_filter_content', 8);

function timellow_md_filter_excerpt($excerpt, $post) {
    if ($post instanceof WP_Post && timellow_md_mode($post) === 'markdown' && $post->post_excerpt === '') {
        return wp_trim_words(wp_strip_all_tags(timellow_md_render($post->post_content)), 55, '…');
    }
    return $excerpt;
}
add_filter('get_the_excerpt', 'timellow_md_filter_excerpt', 20, 2);

function timellow_md_save_mode($post_id, $post) {
    if (!timellow_md_enabled() || !timellow_md_supports($post->post_type) || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    if (!isset($_POST['timellow_md_nonce'], $_POST['timellow_md_mode'])) return;
    $nonce = sanitize_text_field(wp_unslash($_POST['timellow_md_nonce']));
    if (!wp_verify_nonce($nonce, 'timellow_md_save') || !current_user_can('edit_post', $post_id)) return;
    $mode = sanitize_key(wp_unslash($_POST['timellow_md_mode']));
    if (in_array($mode, ['markdown', 'html'], true)) {
        update_post_meta($post_id, '_timellow_markdown_mode', $mode);
    }
}
add_action('save_post', 'timellow_md_save_mode', 10, 2);

function timellow_md_editor($post) {
    if (!timellow_md_enabled() || !timellow_md_supports($post->post_type)) return;
    $mode = timellow_md_mode($post);
    wp_nonce_field('timellow_md_save', 'timellow_md_nonce');
    ?>
    <section id="timellow-markdown-editor" class="timellow-md" data-view="split" data-format="<?php echo esc_attr($mode); ?>">
        <div class="timellow-md-header">
            <div class="timellow-md-format" role="group" aria-label="内容格式">
                <button type="button" class="timellow-md-format-button" data-format="markdown" aria-pressed="<?php echo $mode === 'markdown' ? 'true' : 'false'; ?>">Markdown</button>
                <button type="button" class="timellow-md-format-button" data-format="html" aria-pressed="<?php echo $mode === 'html' ? 'true' : 'false'; ?>">HTML</button>
                <input type="hidden" name="timellow_md_mode" value="<?php echo esc_attr($mode); ?>">
            </div>
            <div class="timellow-md-views" role="group" aria-label="编辑视图">
                <button type="button" data-view="edit" aria-pressed="false">编辑</button>
                <button type="button" data-view="split" aria-pressed="true">分屏</button>
                <button type="button" data-view="preview" aria-pressed="false">预览</button>
            </div>
            <button type="button" class="timellow-md-fullscreen" data-action="fullscreen" title="全屏编辑" aria-label="全屏编辑" aria-pressed="false"><span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span></button>
        </div>
        <div class="timellow-md-tools" role="toolbar" aria-label="Markdown 格式">
            <?php
            $tools = [
                ['heading', '二级标题', 'dashicons-heading'],
                ['bold', '加粗', 'dashicons-editor-bold'],
                ['italic', '斜体', 'dashicons-editor-italic'],
                ['link', '链接', 'dashicons-admin-links'],
                ['image', '插入图片', 'dashicons-format-image'],
                ['quote', '引用', 'dashicons-format-quote'],
                ['code', '代码', 'dashicons-editor-code'],
                ['ul', '无序列表', 'dashicons-editor-ul'],
                ['ol', '有序列表', 'dashicons-editor-ol'],
            ];
            foreach ($tools as [$action, $label, $icon]) {
                echo '<button type="button" data-action="' . esc_attr($action) . '" title="' . esc_attr($label) . '" aria-label="' . esc_attr($label) . '"><span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span></button>';
            }
            ?>
        </div>
        <div class="timellow-md-workspace">
            <div class="timellow-md-pane timellow-md-source-pane">
                <label class="screen-reader-text" for="content">正文</label>
                <textarea id="content" name="content" spellcheck="true"><?php echo esc_textarea($post->post_content); ?></textarea>
            </div>
            <div class="timellow-md-pane timellow-md-preview-pane" aria-label="正文预览">
                <div class="timellow-md-preview"></div>
            </div>
        </div>
        <div class="timellow-md-status" role="status" aria-live="polite"></div>
    </section>
    <?php
}
add_action('edit_form_after_title', 'timellow_md_editor');

function timellow_md_convert_button($post) {
    if (!timellow_md_enabled() || !$post instanceof WP_Post || $post->post_type !== 'post' || !current_user_can('edit_post', $post->ID)) {
        return;
    }
    echo '<button type="button" id="timellow-convert-markdown" class="button" aria-controls="content"' .
        (timellow_md_mode($post) === 'markdown' ? ' hidden' : '') . '>转换为 Markdown</button>';
}
add_action('post_submitbox_start', 'timellow_md_convert_button');

function timellow_md_admin_assets($hook) {
    if (!timellow_md_enabled()) return;
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    $screen = get_current_screen();
    if (!$screen || !timellow_md_supports($screen->post_type)) return;
    wp_enqueue_media();
    wp_enqueue_style('timellow-markdown-editor', plugins_url('assets/editor.css', TIMELLOW_PLUGIN_DIR . '/timellow.php'), [], TIMELLOW_PLUGIN_VERSION);
    if ($screen->post_type === 'post') {
        wp_enqueue_script('timellow-turndown', plugins_url('vendor/turndown.js', TIMELLOW_PLUGIN_DIR . '/timellow.php'), [], '7.2.4', true);
        wp_enqueue_script('timellow-turndown-gfm', plugins_url('vendor/turndown-plugin-gfm.js', TIMELLOW_PLUGIN_DIR . '/timellow.php'), ['timellow-turndown'], '1.0.2', true);
    }
    wp_enqueue_script('timellow-markdown-editor', plugins_url('assets/editor.js', TIMELLOW_PLUGIN_DIR . '/timellow.php'), $screen->post_type === 'post' ? ['timellow-turndown-gfm'] : [], TIMELLOW_PLUGIN_VERSION, true);
    wp_localize_script('timellow-markdown-editor', 'timellowMarkdown', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('timellow_md_preview'),
        'postId' => (int) ($_GET['post'] ?? 0),
    ]);
}
add_action('admin_enqueue_scripts', 'timellow_md_admin_assets');

function timellow_md_ajax_preview() {
    if (!timellow_md_enabled()) {
        wp_send_json_error(['message' => 'Markdown 编辑器未启用。'], 403);
    }
    check_ajax_referer('timellow_md_preview', 'nonce');
    $post_id = absint($_POST['post_id'] ?? 0);
    if ($post_id ? !current_user_can('edit_post', $post_id) : !(current_user_can('edit_posts') || current_user_can('edit_pages'))) {
        wp_send_json_error(['message' => '没有预览权限。'], 403);
    }
    $source = isset($_POST['source']) ? wp_unslash($_POST['source']) : '';
    if (!is_string($source) || strlen($source) > 2 * MB_IN_BYTES) {
        wp_send_json_error(['message' => '内容超过预览限制。'], 413);
    }
    $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'markdown'));
    $html = $mode === 'html' ? wp_kses_post(wpautop($source)) : timellow_md_render($source);
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_timellow_md_preview', 'timellow_md_ajax_preview');
