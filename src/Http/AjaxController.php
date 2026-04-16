<?php

namespace Ozi\AutoContent\Http;

use Ozi\AutoContent\Providers\ProviderManager;
use Ozi\AutoContent\Repositories\PostMetaRepository;
use Ozi\AutoContent\Repositories\PromptPresetRepository;
use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\DraftService;
use Ozi\AutoContent\Services\ResponseValidator;
use Ozi\AutoContent\Services\ShortenerClient;
use Ozi\AutoContent\Support\Capabilities;
use Ozi\AutoContent\Support\Logger;

class AjaxController
{
    private $providers;
    private $validator;
    private $drafts;
    private $shortener;
    private $postMeta;
    private $settings;
    private $logger;
    private $promptPresets;

    public function __construct(
        ProviderManager $providers,
        ResponseValidator $validator,
        DraftService $drafts,
        ShortenerClient $shortener,
        PostMetaRepository $postMeta,
        SettingsRepository $settings,
        Logger $logger,
        PromptPresetRepository $promptPresets
    ) {
        $this->providers     = $providers;
        $this->validator     = $validator;
        $this->drafts        = $drafts;
        $this->shortener     = $shortener;
        $this->postMeta      = $postMeta;
        $this->settings      = $settings;
        $this->logger        = $logger;
        $this->promptPresets = $promptPresets;
    }

    public function generateDraft()
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('ozi_acwp_generate_draft', 'nonce');

        $sourceContent = sanitize_textarea_field(wp_unslash($_POST['source_content'] ?? ''));
        $imageContext  = sanitize_textarea_field(wp_unslash($_POST['image_context'] ?? ''));
        $providerKey   = sanitize_key($_POST['provider'] ?? '');
        $model         = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $presetId      = sanitize_text_field(wp_unslash($_POST['prompt_preset_id'] ?? ''));
        $postId        = absint($_POST['post_id'] ?? 0);

        if (empty($sourceContent)) {
            wp_send_json_error(['message' => 'Source content is required.'], 400);
        }

        $preset   = $presetId !== '' ? $this->promptPresets->find($presetId) : null;
        $provider = $this->providers->resolve($providerKey);

        $context = [
            'source_content' => $sourceContent,
            'image_context'  => $imageContext,
            'model'          => $model,
        ];

        $result = $provider->generate($context, $preset);

        if (empty($result['success'])) {
            $this->logger->error('Generate failed', $result);
            wp_send_json_error(['message' => $result['message'] ?? 'Generation failed.'], 500);
        }

        $validation = $this->validator->validate($result);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => 'AI response invalid: ' . $validation['error']], 422);
        }

        $newPostId = $this->drafts->createOrUpdateDraft($result, $postId);
        if (is_wp_error($newPostId)) {
            wp_send_json_error(['message' => $newPostId->get_error_message()], 500);
        }

        wp_send_json_success([
            'post_id'          => $newPostId,
            'edit_url'         => get_edit_post_link($newPostId, 'raw'),
            'title'            => $result['title'] ?? '',
            'facebook_caption' => $result['facebook_caption'] ?? '',
            'image_prompt'     => $result['image_prompt'] ?? '',
            'cta_text'         => $result['cta_text'] ?? '',
            'suggested_slug'   => $result['suggested_slug'] ?? '',
            'image_notes'      => $result['image_notes'] ?? [],
            'provider'         => $result['provider'],
            'model'            => $result['model'],
        ]);
    }

    public function createShortlink()
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('ozi_acwp_create_shortlink', 'nonce');

        $postId   = absint($_POST['post_id'] ?? 0);
        $hostname = sanitize_text_field(wp_unslash($_POST['hostname'] ?? ''));
        $slug     = sanitize_text_field(wp_unslash($_POST['custom_slug'] ?? ''));

        if (!$postId || !$hostname) {
            wp_send_json_error(['message' => 'Missing post_id or hostname.'], 400);
        }

        // Use a server-side permalink; falls back to preview URL for drafts
        $targetUrl = get_permalink($postId);
        if (!$targetUrl) {
            wp_send_json_error(['message' => 'Could not determine post URL. Save the draft first.'], 400);
        }

        $result = $this->shortener->createLink($targetUrl, $hostname, $slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $status = $result['status'] ?? 0;
        $body   = $result['body'] ?? [];

        if ($status < 200 || $status >= 300) {
            $msg = $body['message'] ?? ('Shortener returned HTTP ' . $status);
            wp_send_json_error(['message' => $msg], 400);
        }

        $shortUrl = $body['short_url'] ?? ($body['data']['short_url'] ?? '');

        $this->postMeta->saveShortlink($postId, [
            'hostname'  => $hostname,
            'slug'      => $body['slug'] ?? $slug,
            'short_url' => $shortUrl,
        ]);

        wp_send_json_success(['short_url' => $shortUrl, 'post_id' => $postId]);
    }

    public function metaboxCreateShortlink()
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('ozi_acwp_metabox_shortlink', 'nonce');

        $postId    = absint($_POST['post_id'] ?? 0);
        $hostname  = sanitize_text_field(wp_unslash($_POST['hostname'] ?? ''));
        $targetUrl = esc_url_raw(wp_unslash($_POST['target_url'] ?? ''));
        $slug      = sanitize_text_field(wp_unslash($_POST['custom_slug'] ?? ''));

        if (!$postId || !$hostname || !$targetUrl) {
            wp_send_json_error(['message' => 'Missing required fields (post_id, hostname, target_url).'], 400);
        }

        $result = $this->shortener->createLink($targetUrl, $hostname, $slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $status = $result['status'] ?? 0;
        $body   = $result['body'] ?? [];

        if ($status < 200 || $status >= 300) {
            $msg = $body['message'] ?? ('Shortener returned HTTP ' . $status);
            wp_send_json_error(['message' => $msg], 400);
        }

        $shortUrl = $body['short_url'] ?? ($body['data']['short_url'] ?? '');

        $this->postMeta->saveShortlink($postId, [
            'hostname'  => $hostname,
            'slug'      => $body['slug'] ?? $slug,
            'short_url' => $shortUrl,
        ]);

        wp_send_json_success(['short_url' => $shortUrl]);
    }

    public function checkConnection()
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('ozi_acwp_check_connection', 'nonce');

        $target = sanitize_text_field(wp_unslash($_POST['target'] ?? ''));

        $result = $target === 'shortener'
            ? $this->shortener->testConnection()
            : $this->providers->testConnection($target);

        if (!empty($result['success'])) {
            wp_send_json_success(['message' => $result['message']]);
        }

        wp_send_json_error(['message' => $result['message'] ?? 'Connection failed.'], 400);
    }
}
