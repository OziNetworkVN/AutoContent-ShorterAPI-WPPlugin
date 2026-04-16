<?php

namespace Ozi\AutoContent\Services;

class ResponseValidator
{
    public static function schema()
    {
        return [
            'title'             => 'string',
            'wordpress_content' => 'string',
            'facebook_caption'  => 'string',
            'cta_text'          => 'string',
            'suggested_slug'    => 'string',
            'image_prompt'      => 'string',
            'image_notes'       => ['string'],
        ];
    }

    public function validate(array $payload)
    {
        foreach (['title', 'wordpress_content', 'facebook_caption'] as $field) {
            if (empty($payload[$field]) || !is_string($payload[$field])) {
                return [
                    'valid' => false,
                    'error' => sprintf('Missing or invalid field: %s', $field),
                ];
            }
        }

        return ['valid' => true];
    }
}
