<?php

namespace Ozi\AutoContent\Repositories;

class SettingsRepository
{
    public const OPTION_KEY = 'ozi_acwp_settings';

    public function all()
    {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->defaults());
    }

    public function get($key, $default = null)
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function defaults()
    {
        return [
            'default_provider' => 'gemini',
            'provider_grok_api_base' => 'https://api.x.ai/v1',
            'provider_grok_api_key' => '',
            'provider_grok_model' => 'grok-3',
            'provider_gemini_api_base' => 'https://generativelanguage.googleapis.com/v1beta',
            'provider_gemini_api_key' => '',
            'provider_gemini_model' => 'gemini-3-flash-preview',
            'provider_openai_api_base' => 'https://api.openai.com/v1',
            'provider_openai_api_key' => '',
            'provider_openai_model' => 'gpt-4o-mini',
            'shortener_api_base' => 'https://oziwe.com',
            'shortener_api_key' => '',
            'shortener_allowed_hostnames' => "oziwe.com\nnews.oziwe.com",
            'default_shortener_hostname' => 'oziwe.com',
            'default_language' => 'vi',
            'default_site_context' => 'Vietnamese social content to website editorial workflow.',
        ];
    }

    public function sanitize($input)
    {
        $defaults = $this->defaults();
        $output = [];

        foreach ($defaults as $key => $defaultValue) {
            $value = is_array($input) && array_key_exists($key, $input) ? $input[$key] : $defaultValue;
            $output[$key] = is_string($value) ? trim(wp_unslash($value)) : $defaultValue;
        }

        return $output;
    }
}
