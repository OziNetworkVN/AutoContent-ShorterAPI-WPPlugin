<?php

namespace Ozi\AutoContent\Repositories;

class PostMetaRepository
{
    public function saveGeneration($postId, array $payload)
    {
        $map = [
            '_ozi_source_content' => $payload['source_content'] ?? '',
            '_ozi_source_image_ids' => $payload['source_image_ids'] ?? [],
            '_ozi_ai_provider' => $payload['provider'] ?? '',
            '_ozi_ai_model' => $payload['model'] ?? '',
            '_ozi_ai_title' => $payload['title'] ?? '',
            '_ozi_ai_content' => $payload['wordpress_content'] ?? '',
            '_ozi_ai_facebook_caption' => $payload['facebook_caption'] ?? '',
            '_ozi_ai_cta_text' => $payload['cta_text'] ?? '',
            '_ozi_ai_image_prompt' => $payload['image_prompt'] ?? '',
            '_ozi_ai_payload_raw' => $payload['raw_payload'] ?? '',
            '_ozi_last_generation_at' => current_time('mysql'),
        ];

        foreach ($map as $key => $value) {
            update_post_meta($postId, $key, $value);
        }
    }

    public function saveShortlink($postId, array $payload)
    {
        update_post_meta($postId, '_ozi_short_hostname', $payload['hostname'] ?? '');
        update_post_meta($postId, '_ozi_short_slug', $payload['slug'] ?? '');
        update_post_meta($postId, '_ozi_short_url', $payload['short_url'] ?? '');
        update_post_meta($postId, '_ozi_last_shortlink_at', current_time('mysql'));
    }
}
