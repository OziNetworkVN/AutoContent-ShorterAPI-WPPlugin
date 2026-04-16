# Agent Instructions

This repo is for a WordPress plugin that supports a semi-automatic editorial workflow. Agents implementing this plugin should optimize for:
- simple admin UX
- deterministic server-side flows
- low operational complexity
- easy rollback and debugging

## Non-goals

Do not implement:
- automatic publishing
- automatic posting to Facebook
- browser extensions
- public frontend rendering features unrelated to the editorial workflow
- provider-specific UI branches unless clearly needed

## MVP scope

Build one plugin with:
- one admin page: `AI Content Desk`
- one settings page for AI providers, prompts, and shortener settings
- one post meta box for generated assets
- server-side requests to AI providers and shortener API
- storage in post meta and plugin options

## Required workflow

1. Editor submits source content and source images.
2. Plugin selects the configured provider and model.
3. Plugin builds prompts using code defaults plus optional settings overrides.
4. Plugin sends the source package to the AI provider.
5. Plugin validates the AI response against a strict JSON contract.
6. Plugin creates or updates a WordPress draft.
7. Plugin shows generated article content and generated Facebook caption.
8. Editor triggers short-link creation manually.
9. Plugin stores short-link data in post meta.
10. Plugin offers copy actions for Facebook output.

## Architecture rules

1. Keep all privileged API calls server-side in WordPress PHP.
2. Never expose AI credentials or shortener API keys to browser JavaScript.
3. Use a provider adapter layer for Grok, Gemini, and OpenAI.
4. Treat AI output as untrusted input and validate before saving.
5. Save each significant step result for audit/debugging.
6. Use code-defined default prompts, with admin settings only overriding them.
7. Prefer classic WordPress admin pages, AJAX actions, post meta, and options over heavy frameworks.

## Suggested plugin structure

```text
auto-content-shorterapi-wpplugin/
  auto-content-shorterapi-wpplugin.php
  src/
    Plugin.php
    Admin/
      Menu.php
      DeskPage.php
      SettingsPage.php
      MetaBox.php
    Http/
      AjaxController.php
    Providers/
      ProviderInterface.php
      ProviderManager.php
      AbstractProvider.php
      GrokProvider.php
      GeminiProvider.php
      OpenAIProvider.php
    Services/
      PromptManager.php
      ResponseValidator.php
      DraftService.php
      ShortenerClient.php
    Repositories/
      SettingsRepository.php
      PostMetaRepository.php
    Support/
      Logger.php
      Capabilities.php
```

## Required post meta keys

- `_ozi_source_content`
- `_ozi_source_image_ids`
- `_ozi_ai_provider`
- `_ozi_ai_model`
- `_ozi_ai_title`
- `_ozi_ai_content`
- `_ozi_ai_facebook_caption`
- `_ozi_ai_cta_text`
- `_ozi_ai_payload_raw`
- `_ozi_short_hostname`
- `_ozi_short_slug`
- `_ozi_short_url`
- `_ozi_last_generation_at`
- `_ozi_last_shortlink_at`

## Required options

- `ozi_default_provider`
- `ozi_provider_grok_api_base`
- `ozi_provider_grok_api_key`
- `ozi_provider_grok_model`
- `ozi_provider_gemini_api_base`
- `ozi_provider_gemini_api_key`
- `ozi_provider_gemini_model`
- `ozi_provider_openai_api_base`
- `ozi_provider_openai_api_key`
- `ozi_provider_openai_model`
- `ozi_prompt_system_override`
- `ozi_prompt_user_override`
- `ozi_shortener_api_base`
- `ozi_shortener_api_key`
- `ozi_shortener_allowed_hostnames`
- `ozi_default_shortener_hostname`

## Delivery rule

Ship the plugin in thin vertical slices:
1. bootstrap and settings
2. provider abstraction and prompt manager
3. desk page form
4. AI generation action
5. draft persistence
6. short-link generation
7. copy/export actions
