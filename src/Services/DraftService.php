<?php

namespace Ozi\AutoContent\Services;

use Ozi\AutoContent\Repositories\PostMetaRepository;

class DraftService
{
    private $postMeta;

    public function __construct(PostMetaRepository $postMeta)
    {
        $this->postMeta = $postMeta;
    }

    public function createOrUpdateDraft(array $payload, $postId = 0)
    {
        $postarr = [
            'ID'           => $postId,
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $payload['title'],
            'post_content' => wp_kses_post($payload['wordpress_content']),
        ];

        if (!$postId && !empty($payload['suggested_slug'])) {
            $postarr['post_name'] = sanitize_title($payload['suggested_slug']);
        }

        $result = $postId ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
        if (is_wp_error($result)) {
            return $result;
        }

        $this->postMeta->saveGeneration((int) $result, $payload);

        return (int) $result;
    }
}
