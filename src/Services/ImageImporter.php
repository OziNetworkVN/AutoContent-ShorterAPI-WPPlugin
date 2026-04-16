<?php

namespace Ozi\AutoContent\Services;

use Ozi\AutoContent\Support\Logger;

class ImageImporter
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Main entry point called after draft is created.
     *
     * 1. Extract image URLs from $imageContext
     * 2. Sideload each URL into the WP media library attached to $postId
     * 3. Set the first successful import as featured image
     * 4. Insert all images as a block after the 2nd <p> in post content
     *
     * @return int[] attachment IDs that were successfully imported
     */
    public function processForPost(int $postId, string $imageContext): array
    {
        $urls = $this->extractImageUrls($imageContext);
        if (empty($urls)) {
            return [];
        }

        $this->requireWordPressMediaLibs();

        $attachmentIds = [];
        foreach ($urls as $url) {
            $id = $this->sideloadUrl($url, $postId);
            if ($id) {
                $attachmentIds[] = $id;
            }
        }

        if (empty($attachmentIds)) {
            return [];
        }

        // Set first image as featured image
        if (!has_post_thumbnail($postId)) {
            set_post_thumbnail($postId, $attachmentIds[0]);
        }

        // Insert images into post content after paragraph 2
        $this->insertImagesIntoContent($postId, $attachmentIds);

        return $attachmentIds;
    }

    // -------------------------------------------------------------------------

    /**
     * Extract all image-like URLs from free-text (Facebook CDN, any direct image URL).
     * Handles:
     *   - Direct image URLs ending in .jpg/.jpeg/.png/.gif/.webp
     *   - Facebook CDN URLs (fbcdn.net) which may have query strings
     *   - Generic URLs — we attempt download and let WP validate the MIME
     */
    public function extractImageUrls(string $text): array
    {
        // Match all HTTP(S) URLs
        preg_match_all('/https?:\/\/[^\s\'"<>]+/i', $text, $matches);
        $urls = $matches[0] ?? [];

        $filtered = [];
        foreach ($urls as $url) {
            // Strip trailing punctuation that may have been included
            $url = rtrim($url, '.,;:)]}"\'>');

            // Prioritise: known image extensions or Facebook CDN domains
            if ($this->looksLikeImageUrl($url)) {
                $filtered[] = $url;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function looksLikeImageUrl(string $url): bool
    {
        // Facebook CDN
        if (strpos($url, 'fbcdn.net') !== false) {
            return true;
        }
        // Instagram CDN
        if (strpos($url, 'cdninstagram.com') !== false || strpos($url, 'instagram.com') !== false) {
            return true;
        }
        // Direct image extension (before query string)
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
            return true;
        }
        // WordPress media URL patterns
        if (preg_match('/\/wp-content\/uploads\//i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Sideload a single URL into WP media library.
     * Returns attachment ID on success, 0 on failure.
     */
    private function sideloadUrl(string $url, int $postId): int
    {
        // media_sideload_image with 'id' returns int|WP_Error
        $result = media_sideload_image($url, $postId, '', 'id');

        if (is_wp_error($result)) {
            $this->logger->error('Image sideload failed', [
                'url'   => $url,
                'error' => $result->get_error_message(),
            ]);
            return 0;
        }

        return (int) $result;
    }

    /**
     * Insert images as HTML after the 2nd closing </p> tag in post content.
     */
    private function insertImagesIntoContent(int $postId, array $attachmentIds): void
    {
        $post = get_post($postId);
        if (!$post || empty($post->post_content)) {
            return;
        }

        // Build image HTML block
        $imgBlock = $this->buildImageBlock($attachmentIds);
        if (!$imgBlock) {
            return;
        }

        $content    = $post->post_content;
        $insertAfter = 2; // after 2nd </p>
        $count      = 0;
        $inserted   = false;

        $newContent = preg_replace_callback('/<\/p>/i', static function ($match) use (&$count, &$inserted, $imgBlock, $insertAfter) {
            $count++;
            if (!$inserted && $count === $insertAfter) {
                $inserted = true;
                return '</p>' . "\n\n" . $imgBlock;
            }
            return $match[0];
        }, $content);

        // Fallback: append at end if content had fewer than 2 paragraphs
        if (!$inserted) {
            $newContent = $content . "\n\n" . $imgBlock;
        }

        wp_update_post([
            'ID'           => $postId,
            'post_content' => $newContent,
        ]);
    }

    private function buildImageBlock(array $attachmentIds): string
    {
        $html = '';
        foreach ($attachmentIds as $id) {
            $imgTag = wp_get_attachment_image($id, 'large', false, [
                'class'   => 'aligncenter wp-post-image',
                'loading' => 'lazy',
            ]);
            if ($imgTag) {
                // Wrap in Gutenberg-friendly figure block comment so classic + block editor both render it
                $src     = wp_get_attachment_image_url($id, 'large');
                $caption = get_post_field('post_excerpt', $id);
                $html .= sprintf(
                    "\n<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\">%s%s</figure>\n<!-- /wp:image -->\n",
                    $id,
                    $imgTag,
                    $caption ? '<figcaption>' . esc_html($caption) . '</figcaption>' : ''
                );
            }
        }
        return $html;
    }

    private function requireWordPressMediaLibs(): void
    {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_read_image_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }
}
