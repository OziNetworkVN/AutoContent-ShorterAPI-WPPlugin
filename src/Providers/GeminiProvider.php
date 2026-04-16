<?php

namespace Ozi\AutoContent\Providers;

class GeminiProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Gemini';
    }

    public function defaultModel(): string
    {
        return $this->settings->get('provider_gemini_model', 'gemini-3-flash-preview');
    }

    public function generate(array $payload, ?array $preset = null): array
    {
        $apiBase = rtrim($this->settings->get('provider_gemini_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_gemini_api_key', '');
        $model   = ($payload['model'] ?? '') ?: $this->defaultModel();

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Gemini API base URL or API key is not configured.'];
        }

        $prompts = $this->prompts->render($payload, $preset);

        $body = [
            'system_instruction' => [
                'parts' => [['text' => $prompts['system_prompt']]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $prompts['user_prompt']]],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = $apiBase . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        $response = $this->request($url, [
            'timeout' => 90,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
        ]);

        if (!$response['success']) {
            return ['success' => false, 'message' => $response['error'] ?? 'Gemini request failed.'];
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errBody = json_decode($response['body'], true);
            $msg = $errBody['error']['message'] ?? ('Gemini returned HTTP ' . $response['status']);
            $this->logger->error('Gemini generate failed', ['status' => $response['status'], 'body' => $response['body']]);
            return ['success' => false, 'message' => $msg];
        }

        $parsed = json_decode($response['body'], true);
        $text   = $parsed['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ($text === '') {
            $this->logger->error('Gemini returned empty text', ['body' => $response['body']]);
            return ['success' => false, 'message' => 'Gemini returned an empty response.'];
        }

        return $this->parseJsonPayload($text, $model);
    }

    public function testConnection(): array
    {
        $apiBase = rtrim($this->settings->get('provider_gemini_api_base', ''), '/');
        $apiKey  = $this->settings->get('provider_gemini_api_key', '');

        if ($apiBase === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Missing Gemini base URL or API key.'];
        }

        $response = $this->get($apiBase . '/models?key=' . rawurlencode($apiKey), ['timeout' => 20]);

        if (!$response['success']) {
            return ['success' => false, 'message' => $response['error'] ?? 'Connection failed.'];
        }

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'message' => 'Gemini responded with HTTP ' . $response['status'],
        ];
    }
}
