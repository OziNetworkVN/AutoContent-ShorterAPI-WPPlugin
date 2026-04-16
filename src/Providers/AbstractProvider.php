<?php

namespace Ozi\AutoContent\Providers;

use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\PromptManager;
use Ozi\AutoContent\Support\Logger;

abstract class AbstractProvider implements ProviderInterface
{
    protected $settings;
    protected $prompts;
    protected $logger;

    public function __construct(SettingsRepository $settings, PromptManager $prompts, Logger $logger)
    {
        $this->settings = $settings;
        $this->prompts  = $prompts;
        $this->logger   = $logger;
    }

    protected function request(string $url, array $args): array
    {
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        return [
            'success' => true,
            'status'  => wp_remote_retrieve_response_code($response),
            'body'    => wp_remote_retrieve_body($response),
        ];
    }

    protected function get(string $url, array $args = []): array
    {
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        return [
            'success' => true,
            'status'  => wp_remote_retrieve_response_code($response),
            'body'    => wp_remote_retrieve_body($response),
        ];
    }

    /**
     * Strip markdown fences and parse JSON returned by AI.
     * Returns ['success' => true, 'provider' => …, 'model' => …, …fields]
     * or      ['success' => false, 'message' => '…']
     */
    protected function parseJsonPayload(string $text, string $model): array
    {
        // Strip optional markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```\s*$/i', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!is_array($data)) {
            $this->logger->error('AI returned invalid JSON', [
                'provider' => $this->key(),
                'preview'  => mb_substr($text, 0, 500),
            ]);
            return ['success' => false, 'message' => 'AI returned invalid JSON. Check error log for details.'];
        }

        return array_merge(['success' => true, 'provider' => $this->key(), 'model' => $model], $data);
    }
}
