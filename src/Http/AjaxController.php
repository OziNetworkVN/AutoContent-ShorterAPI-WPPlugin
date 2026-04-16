<?php

namespace Ozi\AutoContent\Http;

use Ozi\AutoContent\Providers\ProviderManager;
use Ozi\AutoContent\Repositories\PostMetaRepository;
use Ozi\AutoContent\Repositories\PromptPresetRepository;
use Ozi\AutoContent\Repositories\SettingsRepository;
use Ozi\AutoContent\Services\DraftService;
use Ozi\AutoContent\Services\ImageImporter;
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
    private $imageImporter;

    public function __construct(
        ProviderManager $providers,
        ResponseValidator $validator,
        DraftService $drafts,
        ShortenerClient $shortener,
        PostMetaRepository $postMeta,
        SettingsRepository $settings,
        Logger $logger,
        PromptPresetRepository $promptPresets,
        ImageImporter $imageImporter
    ) {
        $this->providers     = $providers;
        $this->validator     = $validator;
        $this->drafts        = $drafts;
        $this->shortener     = $shortener;
        $this->postMeta      = $postMeta;
        $this->settings      = $settings;
        $this->logger        = $logger;
        $this->promptPresets = $promptPresets;
        $this->imageImporter = $imageImporter;
    }

    // -------------------------------------------------------------------------

    public function generateDraft()
    {
        if (!current_user_can(Capabilities::EDIT)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('ozi_acwp_generate_draft', 'nonce');

        $sourceContent = sanitize_textarea_field(wp_unslash($_POST['source_content'] ?? ''));
        $imageContext  = wp_unslash($_POST['image_context'] ?? ''); // keep URLs intact; sanitised per-use
        $providerKey   = sanitize_key($_POST['provider'] ?? '');
        $model         = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $presetId      = sanitize_text_field(wp_unslash($_POST['prompt_preset_id'] ?? ''));
        $postId        = absint($_POST['post_id'] ?? 0);

        if (empty($sourceContent)) {
            wp_send_json_error(['message' => 'Source content is required.'], 400);
        }

        $preset   = $presetId !== '' ? $this->promptPresets->find($presetId) : null;
        $provider = $this->providers->resolve($providerKey);

        $result = $provider->generate([
            'source_content' => $sourceContent,
            'image_context'  => sanitize_textarea_field($imageContext),
            'model'          => $model,
        ], $preset);

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

        // Import images from image_context: sideload → insert after para 2 → set featured
        $importedImages = [];
        if (trim($imageContext) !== '') {
            $attachmentIds = $this->imageImporter->processForPost($newPostId, $imageContext);
            foreach ($attachmentIds as $id) {
                $src = wp_get_attachment_image_url($id, 'large');
                if ($src) {
                    $importedImages[] = $src;
                }
            }
        }

        $featuredImageUrl = get_the_post_thumbnail_url($newPostId, 'large') ?: '';

        wp_send_json_success([
            'post_id'            => $newPostId,
            'edit_url'           => get_edit_post_link($newPostId, 'raw'),
            'title'              => $result['title'] ?? '',
            'facebook_caption'   => $result['facebook_caption'] ?? '',
            'image_prompt'       => $result['image_prompt'] ?? '',
            'cta_text'           => $result['cta_text'] ?? '',
            'suggested_slug'     => $result['suggested_slug'] ?? '',
            'image_notes'        => $result['image_notes'] ?? [],
            'provider'           => $result['provider'],
            'model'              => $result['model'],
            'imported_images'    => $importedImages,
            'featured_image_url' => $featuredImageUrl,
        ]);
    }

    // -------------------------------------------------------------------------

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

        $post = get_post($postId);
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found.'], 404);
        }

        // Only allow shortlinks for published posts to avoid ?p=ID redirect chains
        if ($post->post_status !== 'publish') {
            wp_send_json_error([
                'message' => 'Post must be published before creating a short link. Publish the draft first, then create the short link.',
                'code'    => 'not_published',
            ], 400);
        }

        $targetUrl = get_permalink($postId);
        if (!$targetUrl) {
            wp_send_json_error(['message' => 'Could not determine post permalink.'], 400);
        }

        // Build Open Graph metadata for shortener link preview
        $ogMeta = $this->buildOgMeta($post);

        $result = $this->shortener->createLink($targetUrl, $hostname, $slug, $ogMeta);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $status = $result['status'] ?? 0;
        $body   = $result['body'] ?? [];

        if ($status < 200 || $status >= 300) {
            wp_send_json_error(['message' => $body['message'] ?? ('Shortener returned HTTP ' . $status)], 400);
        }

        $shortUrl = $body['short_url'] ?? ($body['data']['short_url'] ?? '');

        $this->postMeta->saveShortlink($postId, [
            'hostname'  => $hostname,
            'slug'      => $body['slug'] ?? $slug,
            'short_url' => $shortUrl,
        ]);

        wp_send_json_success(['short_url' => $shortUrl, 'post_id' => $postId]);
    }

    // -------------------------------------------------------------------------

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

        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error([
                'message' => 'Post must be published before creating a short link.',
                'code'    => 'not_published',
            ], 400);
        }

        // Build OG meta from post data
        $ogMeta = $this->buildOgMeta($post);

        $result = $this->shortener->createLink($targetUrl, $hostname, $slug, $ogMeta);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $status = $result['status'] ?? 0;
        $body   = $result['body'] ?? [];

        if ($status < 200 || $status >= 300) {
            wp_send_json_error(['message' => $body['message'] ?? ('Shortener returned HTTP ' . $status)], 400);
        }

        $shortUrl = $body['short_url'] ?? ($body['data']['short_url'] ?? '');

        $this->postMeta->saveShortlink($postId, [
            'hostname'  => $hostname,
            'slug'      => $body['slug'] ?? $slug,
            'short_url' => $shortUrl,
        ]);

        wp_send_json_success(['short_url' => $shortUrl]);
    }

    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------

    /**
     * Build Open Graph meta array from a WP_Post for shortener link preview.
     */
    private function buildOgMeta(\WP_Post $post): array
    {
        // og_title: post title
        $ogTitle = get_the_title($post->ID);

        // og_description: manually set excerpt → first 160 chars of content
        $ogDescription = $post->post_excerpt;
        if (empty($ogDescription) && $post->post_content) {
            $stripped      = wp_strip_all_tags($post->post_content);
            $ogDescription = mb_substr($stripped, 0, 160);
        }

        // og_image: featured image → first attached image
        $ogImage = get_the_post_thumbnail_url($post->ID, 'large');
        if (!$ogImage) {
            $attachments = get_attached_media('image', $post->ID);
            if ($attachments) {
                $first   = reset($attachments);
                $ogImage = wp_get_attachment_image_url($first->ID, 'large') ?: '';
            }
        }

        return array_filter([
            'og_title'       => $ogTitle,
            'og_description' => $ogDescription,
            'og_image'       => $ogImage ?: '',
        ]);
    }
}
