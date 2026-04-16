<?php

namespace Ozi\AutoContent\Providers;

class OpenAIProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function defaultModel(): string
    {
        return $this->settings->get('provider_openai_model', 'gpt-4o-mini');
    }

    public function generate(array $payload, ?array $preset = null): array
    {
        $apiBase = rtrim($this->settings->get('provider_openai_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_openai_api_key', '');
        $model   = ($payload['model'] ?? '') ?: $this->defaultModel();

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'OpenAI API base URL or API key is not configured.'];
        }

        $prompts = $this->prompts->render($payload, $preset);

        $body = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $prompts['system_prompt']],
                ['role' => 'user',   'content' => $prompts['user_prompt']],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->request($apiBase . '/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (!$response['success']) {
            return ['success' => false, 'message' => $response['error'] ?? 'OpenAI request failed.'];
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errBody = json_decode($response['body'], true);
            $msg = $errBody['error']['message'] ?? ('OpenAI returned HTTP ' . $response['status']);
            $this->logger->error('OpenAI generate failed', ['status' => $response['status'], 'body' => $response['body']]);
            return ['success' => false, 'message' => $msg];
        }

        $parsed = json_decode($response['body'], true);
        $text   = $parsed['choices'][0]['message']['content'] ?? '';

        if ($text === '') {
            $this->logger->error('OpenAI returned empty content', ['body' => $response['body']]);
            return ['success' => false, 'message' => 'OpenAI returned an empty response.'];
        }

        return $this->parseJsonPayload($text, $model);
    }

    public function testConnection(): array
    {
        $apiBase = rtrim($this->settings->get('provider_openai_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_openai_api_key', '');

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Missing OpenAI base URL or API key.'];
        }

        $response = $this->get($apiBase . '/models', [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        ]);

        if (!$response['success']) {
            return ['success' => false, 'message' => $response['error'] ?? 'Connection failed.'];
        }

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'message' => 'OpenAI responded with HTTP ' . $response['status'],
        ];
    }
}
