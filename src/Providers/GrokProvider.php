<?php

namespace Ozi\AutoContent\Providers;

class GrokProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'grok';
    }

    public function label(): string
    {
        return 'Grok';
    }

    public function defaultModel(): string
    {
        return $this->settings->get('provider_grok_model', 'grok-3-mini');
    }

    public function generate(array $payload, ?array $preset = null): array
    {
        $apiBase = rtrim($this->settings->get('provider_grok_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_grok_api_key', '');
        $model   = ($payload['model'] ?? '') ?: $this->defaultModel();

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Grok API base URL or API key is not configured.'];
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
            return ['success' => false, 'message' => $response['error'] ?? 'Grok request failed.'];
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errBody = json_decode($response['body'], true);
            $msg = $errBody['error']['message'] ?? ('Grok returned HTTP ' . $response['status']);
            $this->logger->error('Grok generate failed', ['status' => $response['status'], 'body' => $response['body']]);
            return ['success' => false, 'message' => $msg];
        }

        $parsed = json_decode($response['body'], true);
        $text   = $parsed['choices'][0]['message']['content'] ?? '';

        if ($text === '') {
            $this->logger->error('Grok returned empty content', ['body' => $response['body']]);
            return ['success' => false, 'message' => 'Grok returned an empty response.'];
        }

        return $this->parseJsonPayload($text, $model);
    }

    public function testConnection(): array
    {
        $apiBase = rtrim($this->settings->get('provider_grok_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_grok_api_key', '');

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Missing Grok base URL or API key.'];
        }

        $response = $this->request($apiBase . '/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => $this->defaultModel(),
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ]),
        ]);

        if (!$response['success']) {
            return ['success' => false, 'message' => $response['error'] ?? 'Connection failed.'];
        }

        $body = json_decode($response['body'], true);
        $extra = '';
        if (is_array($body) && isset($body['error']['message'])) {
            $extra = ': ' . $body['error']['message'];
        }

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'message' => 'Grok responded with HTTP ' . $response['status'] . $extra,
        ];
    }
}
