# API Contract

This plugin depends on two integration layers:
- AI provider adapters
- `oziShortener` integration API

## 1. Shortener API

Base URL example:

```text
https://oziwe.com
```

Auth:

```http
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

Create link request:

```http
POST /api/links
```

Request body:

```json
{
  "hostname": "news.oziwe.com",
  "target_url": "https://example.com/my-post",
  "custom_slug": "optional-custom-slug"
}
```

Success response:

```json
{
  "success": true,
  "data": {
    "hostname": "news.oziwe.com",
    "slug": "optional-custom-slug",
    "target_url": "https://example.com/my-post",
    "preview_title": "Example title",
    "preview_description": "Example description",
    "preview_image": "https://example.com/cover.jpg",
    "short_url": "https://news.oziwe.com/optional-custom-slug",
    "created_at": "2026-04-10T02:00:00.000Z"
  }
}
```

Failure handling:
- `401`: invalid credentials
- `400`: bad payload or inactive hostname
- `409`: custom slug collision
- `500`: upstream error, allow retry

## 2. Provider adapter contract

All AI providers must be normalized behind one interface.

Required normalized input:

```json
{
  "provider": "grok",
  "model": "grok-3",
  "system_prompt": "string",
  "user_prompt": "string",
  "source_content": "string",
  "image_urls": ["https://example.com/image.jpg"]
}
```

Required normalized output:

```json
{
  "title": "Generated article title",
  "wordpress_content": "<p>Generated HTML content for WordPress</p>",
  "facebook_caption": "Facebook-ready caption",
  "cta_text": "Short CTA line",
  "suggested_slug": "optional-suggested-slug",
  "image_notes": ["Optional image note"],
  "raw_text": "Optional raw provider text"
}
```

Validation rules:
- `title` required, non-empty string
- `wordpress_content` required, non-empty string
- `facebook_caption` required, non-empty string
- `cta_text` optional string
- `suggested_slug` optional slug-like string
- `image_notes` optional string array

## 3. Prompt resolution contract

The plugin must resolve prompts as:
1. code default prompt
2. settings override if non-empty
3. placeholder replacement with runtime content

Supported placeholders:
- `{{source_content}}`
- `{{image_context}}`
- `{{language}}`
- `{{site_context}}`
- `{{output_schema}}`

## 4. Internal plugin handlers

Recommended server-side handlers:
- `admin-ajax.php?action=ozi_generate_draft`
- `admin-ajax.php?action=ozi_create_shortlink`
- `admin-ajax.php?action=ozi_copy_bundle`

All handlers must:
- require capability check
- require nonce validation
- return WordPress JSON responses
