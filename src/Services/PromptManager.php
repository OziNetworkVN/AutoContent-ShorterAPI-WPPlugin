<?php

namespace Ozi\AutoContent\Services;

use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Support\Seeder;

class PromptManager
{
    private $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function defaultSystemPrompt(): string
    {
        return Seeder::defaultSystemPrompt();
    }

    public function defaultUserPromptTemplate(): string
    {
        return Seeder::defaultUserTemplate();
    }

    public function systemPrompt(): string
    {
        return $this->defaultSystemPrompt();
    }

    public function userPromptTemplate(): string
    {
        return $this->defaultUserPromptTemplate();
    }

    /**
     * @param array      $context  Runtime values (source_content, image_context, …)
     * @param array|null $preset   Optional preset ['system_prompt' => '…', 'user_template' => '…']
     */
    public function render(array $context, ?array $preset = null): array
    {
        $replacements = [
            '{{source_content}}' => $context['source_content'] ?? '',
            '{{image_context}}'  => $context['image_context'] ?? '',
            '{{language}}'       => $context['language'] ?? $this->settings->get('default_language', 'vi'),
            '{{site_context}}'   => $context['site_context'] ?? $this->settings->get('default_site_context', ''),
            '{{output_schema}}'  => wp_json_encode(ResponseValidator::schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];

        $systemPrompt = ($preset !== null && ($preset['system_prompt'] ?? '') !== '')
            ? $preset['system_prompt']
            : $this->systemPrompt();

        $userTemplate = ($preset !== null && ($preset['user_template'] ?? '') !== '')
            ? $preset['user_template']
            : $this->userPromptTemplate();

        return [
            'system_prompt' => strtr($systemPrompt, $replacements),
            'user_prompt'   => strtr($userTemplate, $replacements),
        ];
    }
}
