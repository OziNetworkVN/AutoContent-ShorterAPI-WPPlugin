<?php

namespace Ozi\AutoContent\Admin;

use Ozi\AutoContent\Support\Capabilities;

class HistoryPage
{
    public function render(): void
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_die(esc_html__('You do not have permission.', 'ozi-acwp'));
        }

        $perPage = 20;
        $page    = max(1, absint($_GET['paged'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $query = new \WP_Query([
            'post_type'              => 'post',
            'post_status'            => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page'         => $perPage,
            'offset'                 => $offset,
            'orderby'                => 'meta_value',
            'meta_key'               => '_ozi_last_generation_at',
            'order'                  => 'DESC',
            'meta_query'             => [['key' => '_ozi_ai_provider', 'compare' => 'EXISTS']],
            'update_post_term_cache' => false,
            'no_found_rows'          => false,
        ]);

        $posts = $query->posts;
        $total = $query->found_posts;
        $pages = ceil($total / $perPage);
        ?>
        <div class="wrap ozi-acwp-wrap">
            <h1>Generation History <span class="title-count"><?php echo esc_html($total); ?></span></h1>

            <?php if (empty($posts)) : ?>
                <div class="notice notice-info">
                    <p>No AI-generated posts yet. <a href="<?php echo esc_url(admin_url('admin.php?page=ozi-ai-content-desk')); ?>">Go to Desk →</a></p>
                </div>
            <?php else : ?>

            <table class="wp-list-table widefat fixed" id="ozi-history-table">
                <thead>
                    <tr>
                        <th style="width:56px"></th>
                        <th>Title</th>
                        <th style="width:82px">Status</th>
                        <th style="width:110px">Provider</th>
                        <th style="width:130px">Generated</th>
                        <th style="width:170px">Short URL</th>
                        <th style="width:130px">Copy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) :
                        $provider    = get_post_meta($post->ID, '_ozi_ai_provider', true);
                        $model       = get_post_meta($post->ID, '_ozi_ai_model', true);
                        $shortUrl    = get_post_meta($post->ID, '_ozi_short_url', true);
                        $caption     = get_post_meta($post->ID, '_ozi_ai_facebook_caption', true);
                        $imagePrompt = get_post_meta($post->ID, '_ozi_ai_image_prompt', true);
                        $genAt       = get_post_meta($post->ID, '_ozi_last_generation_at', true);
                        $thumb       = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                        $editUrl     = get_edit_post_link($post->ID, 'raw');
                        $viewUrl     = get_permalink($post->ID);
                        $status      = $post->post_status;
                        $rowId       = 'ozi-row-' . $post->ID;

                        $statusMap = [
                            'publish' => ['Published', '#0a7f37'],
                            'draft'   => ['Draft',     '#b32d2e'],
                            'pending' => ['Pending',   '#996800'],
                            'private' => ['Private',   '#50575e'],
                        ];
                        [$statusLabel, $statusColor] = $statusMap[$status] ?? [ucfirst($status), '#50575e'];
                    ?>
                    <!-- Main row -->
                    <tr class="ozi-main-row" data-detail="<?php echo esc_attr($rowId); ?>">
                        <td style="vertical-align:middle">
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:4px;display:block">
                            <?php else : ?>
                                <span style="display:flex;align-items:center;justify-content:center;width:44px;height:44px;background:#f0f0f1;border-radius:4px;font-size:20px">🖼</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle">
                            <strong>
                                <a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($post->post_title ?: '(no title)'); ?></a>
                            </strong><br>
                            <small style="color:#50575e"><?php echo esc_html($model ? mb_substr($model, 0, 30) : ''); ?></small>
                        </td>
                        <td style="vertical-align:middle">
                            <span style="color:<?php echo esc_attr($statusColor); ?>;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px"><?php echo esc_html($statusLabel); ?></span>
                        </td>
                        <td style="vertical-align:middle;font-size:12px;color:#50575e"><?php echo esc_html($provider ?: '—'); ?></td>
                        <td style="vertical-align:middle;font-size:12px;color:#50575e">
                            <?php echo $genAt ? esc_html(wp_date('d/m/Y H:i', strtotime($genAt))) : '—'; ?>
                        </td>
                        <td style="vertical-align:middle;font-size:12px">
                            <?php if ($shortUrl) : ?>
                                <a href="<?php echo esc_url($shortUrl); ?>" target="_blank" style="word-break:break-all"><?php echo esc_html($shortUrl); ?></a>
                            <?php else : ?>
                                <span style="color:#ccc">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle">
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <?php if ($caption) : ?>
                                    <button type="button" class="button button-small ozi-copy-btn"
                                        data-clipboard="<?php echo esc_attr($caption); ?>">
                                        📋 Caption
                                    </button>
                                <?php endif; ?>
                                <?php if ($imagePrompt) : ?>
                                    <button type="button" class="button button-small ozi-copy-btn"
                                        data-clipboard="<?php echo esc_attr($imagePrompt); ?>">
                                        🖼 Img Prompt
                                    </button>
                                <?php endif; ?>
                                <?php if ($caption || $imagePrompt) : ?>
                                    <button type="button" class="button button-small ozi-toggle-detail"
                                        data-target="<?php echo esc_attr($rowId); ?>"
                                        title="Show / hide full text">
                                        ↕ Preview
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Expandable detail row -->
                    <?php if ($caption || $imagePrompt) : ?>
                    <tr id="<?php echo esc_attr($rowId); ?>" class="ozi-detail-row" style="display:none">
                        <td colspan="7" style="background:#f9f9f9;padding:16px 20px;border-top:2px solid #e0e0e0">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                                <?php if ($caption) : ?>
                                <div>
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                        <strong style="font-size:13px">📋 Facebook Caption</strong>
                                        <button type="button" class="button button-small ozi-copy-btn"
                                            data-clipboard="<?php echo esc_attr($caption); ?>">Copy</button>
                                    </div>
                                    <textarea class="large-text code" rows="8" readonly
                                        style="font-size:12px;resize:vertical"><?php echo esc_textarea($caption); ?></textarea>
                                </div>
                                <?php endif; ?>
                                <?php if ($imagePrompt) : ?>
                                <div>
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                        <strong style="font-size:13px">🖼 Image Prompt</strong>
                                        <button type="button" class="button button-small ozi-copy-btn"
                                            data-clipboard="<?php echo esc_attr($imagePrompt); ?>">Copy</button>
                                    </div>
                                    <textarea class="large-text code" rows="8" readonly
                                        style="font-size:12px;resize:vertical"><?php echo esc_textarea($imagePrompt); ?></textarea>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($shortUrl) : ?>
                            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e0e0e0">
                                <strong style="font-size:13px">🔗 Facebook Bundle (Caption + Link)</strong>
                                <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:6px">
                                    <button type="button" class="button button-small ozi-copy-btn"
                                        data-clipboard="<?php echo esc_attr($caption . "\n\n" . $shortUrl); ?>">Copy Bundle</button>
                                </div>
                                <textarea class="large-text code" rows="6" readonly
                                    style="font-size:12px"><?php echo esc_textarea($caption . "\n\n" . $shortUrl); ?></textarea>
                            </div>
                            <?php endif; ?>
                            <div style="margin-top:10px;display:flex;gap:8px">
                                <a href="<?php echo esc_url($editUrl); ?>" class="button button-secondary">✏️ Edit Post</a>
                                <?php if ($status === 'publish') : ?>
                                    <a href="<?php echo esc_url($viewUrl); ?>" class="button" target="_blank">🔗 View Live</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top:12px">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post(paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo; Prev',
                            'next_text' => 'Next &raquo;',
                            'total'     => $pages,
                            'current'   => $page,
                        ])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Toggle expandable detail row
            document.querySelectorAll('.ozi-toggle-detail').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var target = document.getElementById(btn.getAttribute('data-target'));
                    if (!target) return;
                    var isHidden = target.style.display === 'none';
                    target.style.display = isHidden ? 'table-row' : 'none';
                    btn.textContent = isHidden ? '↑ Hide' : '↕ Preview';
                });
            });

            // Copy buttons
            document.querySelectorAll('.ozi-copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var text = btn.getAttribute('data-clipboard');
                    if (!text) return;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text);
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text; document.body.appendChild(ta);
                        ta.select(); document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    var orig = btn.textContent;
                    btn.textContent = '✓ Copied!';
                    btn.style.color = '#0a7f37';
                    setTimeout(function () {
                        btn.textContent = orig;
                        btn.style.color = '';
                    }, 1500);
                });
            });
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------

    /**
     * Lightweight query for recent posts — reused by DeskPage.
     */
    public static function recentPosts(int $limit = 10): array
    {
        return get_posts([
            'post_type'              => 'post',
            'post_status'            => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page'         => $limit,
            'orderby'                => 'meta_value',
            'meta_key'               => '_ozi_last_generation_at',
            'order'                  => 'DESC',
            'meta_query'             => [['key' => '_ozi_ai_provider', 'compare' => 'EXISTS']],
            'update_post_term_cache' => false,
            'no_found_rows'          => true,
        ]);
    }
}
