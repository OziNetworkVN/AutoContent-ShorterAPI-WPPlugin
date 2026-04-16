<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Admin\HistoryPage;
use Ozi\AutoContent\Providers\ProviderManager;
use Ozi\AutoContent\Repositories\PromptPresetRepository;
use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Support\Capabilities;

class DeskPage
{
    private $settings;
    private $providers;
    private $presets;

    public function __construct(SettingsRepository $settings, ProviderManager $providers, PromptPresetRepository $presets)
    {
        $this->settings  = $settings;
        $this->providers = $providers;
        $this->presets   = $presets;
    }

    public function render()
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ozi-acwp'));
        }

        $settings   = $this->settings->all();
        $hostnames  = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $settings['shortener_allowed_hostnames']) ?: []));
        $allPresets = $this->presets->all();

        $generateNonce  = wp_create_nonce('ozi_acwp_generate_draft');
        $shortlinkNonce = wp_create_nonce('ozi_acwp_create_shortlink');
        $ajaxUrl        = admin_url('admin-ajax.php');
        ?>
        <div class="wrap ozi-acwp-wrap">
            <h1>AI Content Desk</h1>

            <div id="ozi-notice-area"></div>

            <form id="ozi-desk-form">
                <input type="hidden" id="ozi-nonce-generate"  value="<?php echo esc_attr($generateNonce); ?>">
                <input type="hidden" id="ozi-nonce-shortlink" value="<?php echo esc_attr($shortlinkNonce); ?>">
                <input type="hidden" id="ozi-ajax-url"        value="<?php echo esc_url($ajaxUrl); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ozi-source-content">Source content</label></th>
                        <td><textarea id="ozi-source-content" name="source_content" class="large-text code" rows="12" placeholder="Paste source content here"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ozi-image-context">Image context</label></th>
                        <td><textarea id="ozi-image-context" name="image_context" class="large-text code" rows="4" placeholder="Image URLs, notes, or attachment references (optional)"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ozi-provider">Provider</label></th>
                        <td>
                            <select id="ozi-provider" name="provider">
                                <?php foreach ($this->providers->supportedProviders() as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_provider'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ozi-model">Model override</label></th>
                        <td><input id="ozi-model" type="text" name="model" class="regular-text" placeholder="Leave blank to use provider default"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ozi-prompt-preset">Prompt preset</label></th>
                        <td>
                            <?php if (empty($allPresets)) : ?>
                                <input type="hidden" id="ozi-prompt-preset" name="prompt_preset_id" value="">
                                <p class="description">
                                    No presets yet — built-in default will be used.
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts', 'add_new' => 1], admin_url('admin.php'))); ?>">Create a preset</a>
                                </p>
                            <?php else : ?>
                                <select id="ozi-prompt-preset" name="prompt_preset_id">
                                    <option value="">— Built-in default —</option>
                                    <?php foreach ($allPresets as $preset) : ?>
                                        <option value="<?php echo esc_attr($preset['id']); ?>"><?php echo esc_html($preset['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><a href="<?php echo esc_url(add_query_arg(['page' => 'ozi-ai-content-prompts'], admin_url('admin.php'))); ?>">Manage presets</a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ozi-hostname">Short-link hostname</label></th>
                        <td>
                            <select id="ozi-hostname" name="hostname">
                                <?php foreach ($hostnames as $hostname) : ?>
                                    <option value="<?php echo esc_attr($hostname); ?>" <?php selected($settings['default_shortener_hostname'], $hostname); ?>><?php echo esc_html($hostname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button id="ozi-generate-btn" class="button button-primary" type="button">
                        Generate Draft
                    </button>
                    <span id="ozi-generate-spinner" class="spinner" style="float:none;margin-top:0;vertical-align:middle;display:none"></span>
                </p>
            </form>

            <!-- Result panel (hidden until generation succeeds) -->
            <div id="ozi-result-panel" style="display:none">
                <hr>
                <h2 id="ozi-result-heading">Draft Generated</h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Title</th>
                        <td><strong id="ozi-result-title"></strong></td>
                    </tr>
                    <tr>
                        <th scope="row">Provider / Model</th>
                        <td><span id="ozi-result-meta" style="color:#50575e"></span></td>
                    </tr>
                    <tr id="ozi-imported-images-row">
                        <th scope="row">Images</th>
                        <td><div id="ozi-imported-images" style="display:none"></div></td>
                    </tr>
                    <tr>
                        <th scope="row">Facebook Caption</th>
                        <td>
                            <textarea id="ozi-result-caption" class="large-text code" rows="10" readonly></textarea>
                            <p>
                                <button type="button" class="button" id="ozi-copy-caption">Copy Caption</button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Image Prompt</th>
                        <td>
                            <textarea id="ozi-result-image-prompt" class="large-text code" rows="5" readonly></textarea>
                            <p>
                                <button type="button" class="button" id="ozi-copy-image-prompt">Copy Image Prompt</button>
                            </p>
                        </td>
                    </tr>
                    <tr id="ozi-image-notes-row" style="display:none">
                        <th scope="row">Image Notes</th>
                        <td><ul id="ozi-result-image-notes"></ul></td>
                    </tr>
                </table>

                <p>
                    <a id="ozi-edit-draft-link" href="#" class="button button-secondary" target="_blank">
                        Open Draft Editor
                    </a>
                </p>

                <hr>
                <h3>Create Short Link</h3>
                <p class="description">
                    Hostname selected above:
                    <strong id="ozi-shortlink-hostname-display"></strong>
                </p>
                <p>
                    <button id="ozi-shortlink-btn" type="button" class="button button-secondary">
                        Create Short Link
                    </button>
                    <span id="ozi-shortlink-spinner" class="spinner" style="float:none;margin-top:0;vertical-align:middle;display:none"></span>
                </p>
                <div id="ozi-shortlink-result-area" style="display:none">
                    <p><strong>Short URL:</strong> <a id="ozi-shortlink-url" href="#" target="_blank"></a></p>
                    <h4>Facebook Bundle (Caption + Link)</h4>
                    <textarea id="ozi-facebook-bundle" class="large-text code" rows="12" readonly></textarea>
                    <p>
                        <button type="button" class="button button-primary" id="ozi-copy-bundle">Copy Full Bundle</button>
                    </p>
                </div>
            </div>

            <hr style="margin:32px 0 24px">
            <?php $this->renderRecentPosts(); ?>
        </div>

        <script>
        (function () {
            var generateBtn      = document.getElementById('ozi-generate-btn');
            var generateSpinner  = document.getElementById('ozi-generate-spinner');
            var shortlinkBtn     = document.getElementById('ozi-shortlink-btn');
            var shortlinkSpinner = document.getElementById('ozi-shortlink-spinner');
            var noticeArea       = document.getElementById('ozi-notice-area');
            var resultPanel      = document.getElementById('ozi-result-panel');
            var ajaxUrl          = document.getElementById('ozi-ajax-url').value;
            var currentPostId    = 0;
            var isPostPublished  = false;

            function showNotice(type, msg) {
                noticeArea.innerHTML = '<div class="notice notice-' + type + ' is-dismissible"><p>' + msg + '</p></div>';
                noticeArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function clearNotice() {
                noticeArea.innerHTML = '';
            }

            function escHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function copyToClipboard(text) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
            }

            // Generate Draft
            generateBtn.addEventListener('click', async function () {
                var sourceContent = document.getElementById('ozi-source-content').value.trim();
                if (!sourceContent) {
                    showNotice('error', 'Source content is required.');
                    return;
                }

                clearNotice();
                generateBtn.disabled = true;
                generateSpinner.style.display = 'inline-block';
                resultPanel.style.display = 'none';

                var body = new URLSearchParams({
                    action:           'ozi_generate_draft',
                    nonce:            document.getElementById('ozi-nonce-generate').value,
                    source_content:   sourceContent,
                    image_context:    document.getElementById('ozi-image-context').value,
                    provider:         document.getElementById('ozi-provider').value,
                    model:            document.getElementById('ozi-model').value,
                    prompt_preset_id: document.getElementById('ozi-prompt-preset').value,
                    post_id:          currentPostId,
                });

                try {
                    var res  = await fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString(),
                    });
                    var data = await res.json();

                    if (!data.success) {
                        showNotice('error', data.data.message || 'Generation failed.');
                        return;
                    }

                    var d = data.data;
                    currentPostId   = d.post_id;
                    isPostPublished = false; // always a draft after generation

                    document.getElementById('ozi-result-title').textContent    = d.title;
                    document.getElementById('ozi-result-meta').textContent      = d.provider + ' / ' + d.model;
                    document.getElementById('ozi-result-caption').value         = d.facebook_caption || '';
                    document.getElementById('ozi-result-image-prompt').value    = d.image_prompt || '';
                    document.getElementById('ozi-edit-draft-link').href         = d.edit_url;
                    document.getElementById('ozi-shortlink-hostname-display').textContent =
                        document.getElementById('ozi-hostname').value;

                    // Show imported images info
                    var imgArea = document.getElementById('ozi-imported-images');
                    if (d.imported_images && d.imported_images.length) {
                        var thumbs = d.imported_images.map(function (src) {
                            return '<img src="' + src + '" style="width:60px;height:60px;object-fit:cover;border-radius:3px;margin:2px">';
                        }).join('');
                        imgArea.innerHTML = '<p><strong>✅ ' + d.imported_images.length + ' image(s) imported & inserted:</strong><br>' + thumbs + '</p>';
                        imgArea.style.display = '';
                    } else if (document.getElementById('ozi-image-context').value.trim()) {
                        imgArea.innerHTML = '<p style="color:#996800">⚠️ Image context provided but no image URLs were detected or downloaded.</p>';
                        imgArea.style.display = '';
                    } else {
                        imgArea.style.display = 'none';
                    }

                    // Image notes
                    var notesRow  = document.getElementById('ozi-image-notes-row');
                    var notesList = document.getElementById('ozi-result-image-notes');
                    if (d.image_notes && d.image_notes.length) {
                        notesList.innerHTML = d.image_notes.map(function (n) {
                            return '<li>' + escHtml(n) + '</li>';
                        }).join('');
                        notesRow.style.display = '';
                    } else {
                        notesRow.style.display = 'none';
                    }

                    document.getElementById('ozi-shortlink-result-area').style.display = 'none';
                    resultPanel.style.display = '';
                    showNotice('success', 'Draft created. <a href="' + escHtml(d.edit_url) + '" target="_blank">Open editor →</a>');

                } catch (e) {
                    showNotice('error', e.message);
                } finally {
                    generateBtn.disabled = false;
                    generateSpinner.style.display = 'none';
                }
            });

            // Create Short Link
            shortlinkBtn.addEventListener('click', async function () {
                if (!currentPostId) {
                    showNotice('error', 'Generate a draft first.');
                    return;
                }
                if (!isPostPublished) {
                    showNotice('warning', '⚠️ Short links can only be created for <strong>published</strong> posts. Open the draft editor, publish the post, then return here to create the short link.');
                    return;
                }

                shortlinkBtn.disabled = true;
                shortlinkSpinner.style.display = 'inline-block';

                var body = new URLSearchParams({
                    action:   'ozi_create_shortlink',
                    nonce:    document.getElementById('ozi-nonce-shortlink').value,
                    post_id:  currentPostId,
                    hostname: document.getElementById('ozi-hostname').value,
                });

                try {
                    var res  = await fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString(),
                    });
                    var data = await res.json();

                    if (!data.success) {
                        showNotice('error', data.data.message || 'Short link creation failed.');
                        return;
                    }

                    var shortUrl = data.data.short_url;
                    var caption  = document.getElementById('ozi-result-caption').value;
                    var bundle   = caption + '\n\n' + shortUrl;

                    document.getElementById('ozi-shortlink-url').href        = shortUrl;
                    document.getElementById('ozi-shortlink-url').textContent = shortUrl;
                    document.getElementById('ozi-facebook-bundle').value     = bundle;
                    document.getElementById('ozi-shortlink-result-area').style.display = '';
                    showNotice('success', 'Short link created: ' + shortUrl);

                } catch (e) {
                    showNotice('error', e.message);
                } finally {
                    shortlinkBtn.disabled = false;
                    shortlinkSpinner.style.display = 'none';
                }
            });

            // Copy buttons
            document.getElementById('ozi-copy-caption').addEventListener('click', function () {
                copyToClipboard(document.getElementById('ozi-result-caption').value);
                this.textContent = 'Copied!';
                setTimeout(function () {
                    document.getElementById('ozi-copy-caption').textContent = 'Copy Caption';
                }, 1500);
            });

            document.getElementById('ozi-copy-image-prompt').addEventListener('click', function () {
                copyToClipboard(document.getElementById('ozi-result-image-prompt').value);
                this.textContent = 'Copied!';
                setTimeout(function () {
                    document.getElementById('ozi-copy-image-prompt').textContent = 'Copy Image Prompt';
                }, 1500);
            });

            document.getElementById('ozi-copy-bundle').addEventListener('click', function () {
                copyToClipboard(document.getElementById('ozi-facebook-bundle').value);
                this.textContent = 'Copied!';
                setTimeout(function () {
                    document.getElementById('ozi-copy-bundle').textContent = 'Copy Full Bundle';
                }, 1500);
            });
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------

    private function renderRecentPosts(): void
    {
        $posts = HistoryPage::recentPosts(10);
        ?>
        <h2 style="margin-bottom:12px">
            Recent Generations
            <a href="<?php echo esc_url(admin_url('admin.php?page=ozi-ai-content-history')); ?>" style="font-size:13px;font-weight:normal;margin-left:12px">View all →</a>
        </h2>

        <?php if (empty($posts)) : ?>
            <p style="color:#50575e">No AI-generated posts yet.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="max-width:100%">
                <thead>
                    <tr>
                        <th style="width:44px"></th>
                        <th>Title</th>
                        <th style="width:80px">Status</th>
                        <th style="width:110px">Provider</th>
                        <th style="width:120px">Generated</th>
                        <th style="width:150px">Short URL</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) :
                        $provider = get_post_meta($post->ID, '_ozi_ai_provider', true);
                        $model    = get_post_meta($post->ID, '_ozi_ai_model', true);
                        $shortUrl = get_post_meta($post->ID, '_ozi_short_url', true);
                        $genAt    = get_post_meta($post->ID, '_ozi_last_generation_at', true);
                        $thumb    = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                        $editUrl  = get_edit_post_link($post->ID, 'raw');

                        $statusColor = [
                            'publish' => '#0a7f37',
                            'draft'   => '#b32d2e',
                            'pending' => '#996800',
                            'private' => '#50575e',
                        ][$post->post_status] ?? '#50575e';
                        $statusLabel = ucfirst($post->post_status);
                    ?>
                    <tr>
                        <td>
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" style="width:36px;height:36px;object-fit:cover;border-radius:3px;vertical-align:middle">
                            <?php else : ?>
                                <span style="display:inline-block;width:36px;height:36px;background:#f0f0f1;border-radius:3px;line-height:36px;text-align:center;color:#aaa">🖼</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($editUrl); ?>" style="font-weight:600">
                                <?php echo esc_html(mb_substr($post->post_title ?: '(no title)', 0, 70)); ?>
                            </a>
                        </td>
                        <td><span style="color:<?php echo esc_attr($statusColor); ?>;font-size:12px;font-weight:600"><?php echo esc_html($statusLabel); ?></span></td>
                        <td style="font-size:12px;color:#50575e"><?php echo esc_html($provider ?: '—'); ?></td>
                        <td style="font-size:12px;color:#50575e"><?php echo $genAt ? esc_html(wp_date('d/m H:i', strtotime($genAt))) : '—'; ?></td>
                        <td style="font-size:12px">
                            <?php if ($shortUrl) : ?>
                                <a href="<?php echo esc_url($shortUrl); ?>" target="_blank"><?php echo esc_html($shortUrl); ?></a>
                            <?php else : ?>
                                <span style="color:#aaa">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }
}
