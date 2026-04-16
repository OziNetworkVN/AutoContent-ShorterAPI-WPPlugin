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

        $args = [
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
        ];

        $query = new \WP_Query($args);
        $posts = $query->posts;
        $total = $query->found_posts;
        $pages = ceil($total / $perPage);
        ?>
        <div class="wrap">
            <h1>Generation History <span class="title-count"><?php echo esc_html($total); ?></span></h1>

            <?php if (empty($posts)) : ?>
                <div class="notice notice-info"><p>No AI-generated posts yet. <a href="<?php echo esc_url(admin_url('admin.php?page=ozi-ai-content-desk')); ?>">Go to Desk →</a></p></div>
            <?php else : ?>

            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th style="width:60px">Image</th>
                        <th>Title</th>
                        <th style="width:80px">Status</th>
                        <th style="width:120px">Provider / Model</th>
                        <th style="width:130px">Generated</th>
                        <th style="width:160px">Short URL</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) :
                        $provider  = get_post_meta($post->ID, '_ozi_ai_provider', true);
                        $model     = get_post_meta($post->ID, '_ozi_ai_model', true);
                        $shortUrl  = get_post_meta($post->ID, '_ozi_short_url', true);
                        $caption   = get_post_meta($post->ID, '_ozi_ai_facebook_caption', true);
                        $genAt     = get_post_meta($post->ID, '_ozi_last_generation_at', true);
                        $thumb     = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                        $editUrl   = get_edit_post_link($post->ID, 'raw');
                        $viewUrl   = get_permalink($post->ID);
                        $status    = $post->post_status;

                        $statusLabel = [
                            'publish' => ['Published', '#0a7f37'],
                            'draft'   => ['Draft', '#b32d2e'],
                            'pending' => ['Pending', '#996800'],
                            'private' => ['Private', '#50575e'],
                        ][$status] ?? [$status, '#50575e'];
                    ?>
                    <tr>
                        <td>
                            <?php if ($thumb) : ?>
                                <img src="<?php echo esc_url($thumb); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:3px">
                            <?php else : ?>
                                <span style="display:block;width:50px;height:50px;background:#f0f0f1;border-radius:3px;line-height:50px;text-align:center;color:#aaa;font-size:18px">🖼</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><a href="<?php echo esc_url($editUrl); ?>"><?php echo esc_html($post->post_title ?: '(no title)'); ?></a></strong>
                            <?php if ($caption) : ?>
                                <p style="margin:4px 0 0;font-size:11px;color:#50575e;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:400px">
                                    <?php echo esc_html(mb_substr($caption, 0, 120) . (mb_strlen($caption) > 120 ? '…' : '')); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td><span style="color:<?php echo esc_attr($statusLabel[1]); ?>;font-weight:600;font-size:12px"><?php echo esc_html($statusLabel[0]); ?></span></td>
                        <td style="font-size:12px">
                            <?php echo esc_html($provider ?: '—'); ?><br>
                            <span style="color:#50575e"><?php echo esc_html($model ? mb_substr($model, 0, 20) : ''); ?></span>
                        </td>
                        <td style="font-size:12px;color:#50575e">
                            <?php echo $genAt ? esc_html(wp_date('d/m/Y H:i', strtotime($genAt))) : '—'; ?>
                        </td>
                        <td style="font-size:12px">
                            <?php if ($shortUrl) : ?>
                                <a href="<?php echo esc_url($shortUrl); ?>" target="_blank" style="word-break:break-all"><?php echo esc_html($shortUrl); ?></a>
                            <?php else : ?>
                                <span style="color:#aaa">Not created</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">Edit</a>
                            <?php if ($status === 'publish') : ?>
                                <a href="<?php echo esc_url($viewUrl); ?>" class="button button-small" target="_blank" style="margin-top:4px;display:inline-block">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pageLinks = paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $pages,
                            'current'   => $page,
                        ]);
                        echo wp_kses_post($pageLinks);
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Lightweight query for the last N posts — used by DeskPage.
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
