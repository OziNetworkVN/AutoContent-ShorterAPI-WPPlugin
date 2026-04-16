<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Repositories\PostMetaRepository;
use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\ShortenerClient;

class MetaBox
{
    private $postMeta;
    private $shortener;
    private $settings;

    public function __construct(PostMetaRepository $postMeta, ShortenerClient $shortener, SettingsRepository $settings)
    {
        $this->postMeta  = $postMeta;
        $this->shortener = $shortener;
        $this->settings  = $settings;
    }

    public function register()
    {
        add_meta_box(
            'ozi-acwp-meta-box',
            'AI Content Assets',
            [$this, 'render'],
            'post',
            'side',
            'high'
        );
    }

    public function render($post)
    {
        $shortUrl  = get_post_meta($post->ID, '_ozi_short_url', true);
        $hostname  = get_post_meta($post->ID, '_ozi_short_hostname', true);
        $provider  = get_post_meta($post->ID, '_ozi_ai_provider', true);
        $caption   = get_post_meta($post->ID, '_ozi_ai_facebook_caption', true);

        $settings  = $this->settings->all();
        $hostnames = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $settings['shortener_allowed_hostnames']) ?: []));
        $postUrl   = get_permalink($post->ID) ?: '';
        ?>
        <p><strong>Provider:</strong> <?php echo esc_html($provider ?: 'N/A'); ?></p>

        <?php if ($shortUrl) : ?>
            <p><strong>Short URL:</strong><br>
                <a href="<?php echo esc_url($shortUrl); ?>" target="_blank"><?php echo esc_html($shortUrl); ?></a>
            </p>
        <?php else : ?>
            <p><strong>Short URL:</strong> Not generated</p>
        <?php endif; ?>

        <?php if ($caption) : ?>
            <p><strong>Facebook caption:</strong></p>
            <textarea class="widefat" rows="6" readonly><?php echo esc_textarea($caption); ?></textarea>
        <?php endif; ?>

        <hr style="margin:12px 0">
        <p><strong>Create short link</strong></p>
        <table class="form-table" style="margin:0">
            <tr>
                <td style="padding:2px 0">
                    <label style="font-size:12px">Hostname</label><br>
                    <select id="ozi-mb-hostname" style="width:100%">
                        <?php foreach ($hostnames as $h) : ?>
                            <option value="<?php echo esc_attr($h); ?>" <?php selected($settings['default_shortener_hostname'], $h); ?>><?php echo esc_html($h); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td style="padding:4px 0">
                    <label style="font-size:12px">Target URL</label><br>
                    <input type="url" id="ozi-mb-target-url" class="widefat" value="<?php echo esc_attr($postUrl); ?>" placeholder="https://…">
                </td>
            </tr>
            <tr>
                <td style="padding:4px 0">
                    <label style="font-size:12px">Custom slug <small>(optional)</small></label><br>
                    <input type="text" id="ozi-mb-slug" class="widefat" placeholder="my-slug">
                </td>
            </tr>
        </table>
        <p style="margin-top:8px">
            <button type="button" id="ozi-mb-create-shortlink" class="button button-primary" style="width:100%">
                Create Short Link
            </button>
            <span id="ozi-mb-shortlink-result" style="display:block;margin-top:6px;font-size:12px"></span>
        </p>

        <script>
        (function () {
            const btn    = document.getElementById('ozi-mb-create-shortlink');
            const result = document.getElementById('ozi-mb-shortlink-result');
            if (!btn) return;

            btn.addEventListener('click', async function () {
                const hostname  = document.getElementById('ozi-mb-hostname').value;
                const targetUrl = document.getElementById('ozi-mb-target-url').value.trim();
                const slug      = document.getElementById('ozi-mb-slug').value.trim();

                if (!targetUrl) {
                    result.style.color = '#b32d2e';
                    result.textContent = 'Target URL is required.';
                    return;
                }

                btn.disabled = true;
                result.style.color = '';
                result.textContent = 'Creating…';

                const body = new URLSearchParams({
                    action:   'ozi_metabox_create_shortlink',
                    nonce:    '<?php echo esc_js(wp_create_nonce('ozi_acwp_metabox_shortlink')); ?>',
                    post_id:  '<?php echo esc_js($post->ID); ?>',
                    hostname: hostname,
                    target_url: targetUrl,
                    custom_slug: slug,
                });

                try {
                    const res  = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    if (data.success) {
                        result.style.color = '#0a7f37';
                        result.innerHTML   = 'Created: <a href="' + data.data.short_url + '" target="_blank">' + data.data.short_url + '</a>';
                    } else {
                        result.style.color = '#b32d2e';
                        result.textContent = data.data?.message || 'Failed.';
                    }
                } catch (e) {
                    result.style.color = '#b32d2e';
                    result.textContent = e.message;
                } finally {
                    btn.disabled = false;
                }
            });
        })();
        </script>
        <?php
    }
}
