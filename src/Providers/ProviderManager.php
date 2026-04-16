<?php

namespace Ozi\AutoContent\Providers;

use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\PromptManager;
use Ozi\AutoContent\Support\Logger;

class ProviderManager
{
    private $providers = [];
    private $settings;

    public function __construct(SettingsRepository $settings, PromptManager $prompts, Logger $logger)
    {
        $this->settings = $settings;
        $this->providers = [
            'grok' => new GrokProvider($settings, $prompts, $logger),
            'gemini' => new GeminiProvider($settings, $prompts, $logger),
            'openai' => new OpenAIProvider($settings, $prompts, $logger),
        ];
    }

    public function supportedProviders()
    {
        $result = [];
        foreach ($this->providers as $key => $provider) {
            $result[$key] = $provider->label();
        }
        return $result;
    }

    public function resolve($providerKey = '')
    {
        $providerKey = $providerKey ?: $this->settings->get('default_provider', 'gemini');
        $default     = $this->settings->get('default_provider', 'gemini');
        return $this->providers[$providerKey] ?? $this->providers[$default] ?? reset($this->providers);
    }

    public function testConnection($providerKey = '')
    {
        return $this->resolve($providerKey)->testConnection();
    }
}
