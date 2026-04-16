<?php

namespace Ozi\AutoContent\Services;

use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Support\Logger;

class ShortenerClient
{
    private $settings;
    private $logger;

    public function __construct(SettingsRepository $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * @param string $targetUrl
     * @param string $hostname
     * @param string $customSlug
     * @param array  $ogMeta  Optional Open Graph preview: ['og_title', 'og_description', 'og_image']
     */
    public function createLink(string $targetUrl, string $hostname, string $customSlug = '', array $ogMeta = []): array
    {
        $apiBase = rtrim($this->settings->get('shortener_api_base', ''), '/');
        $apiKey  = $this->settings->get('shortener_api_key', '');

        $body = [
            'hostname'   => $hostname,
            'target_url' => $targetUrl,
        ];

        if ($customSlug !== '') {
            $body['custom_slug'] = $customSlug;
        }

        // Attach Open Graph preview metadata when available
        foreach (['og_title', 'og_description', 'og_image'] as $field) {
            if (!empty($ogMeta[$field])) {
                $body[$field] = $ogMeta[$field];
            }
        }

        $response = wp_remote_post($apiBase . '/api/links', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Shortener request failed', ['error' => $response->get_error_message()]);
            return $response;
        }

        return [
            'status' => wp_remote_retrieve_response_code($response),
            'body' => json_decode(wp_remote_retrieve_body($response), true),
        ];
    }

    public function testConnection()
    {
        $apiBase = rtrim($this->settings->get('shortener_api_base', ''), '/');
        $apiKey = $this->settings->get('shortener_api_key', '');

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Missing shortener base URL or API key.'];
        }

        $response = wp_remote_get($apiBase . '/api/links?limit=1', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Shortener health check failed', ['error' => $response->get_error_message()]);
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $status = wp_remote_retrieve_response_code($response);

        return [
            'success' => $status >= 200 && $status < 300,
            'message' => 'Shortener responded with HTTP ' . $status,
        ];
    }
}
