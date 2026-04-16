# Prompt Spec

## Goal

Convert source material into:
- one WordPress-ready article
- one Facebook-ready caption
- one short CTA

## Prompt model

The plugin must keep prompt defaults in code and allow overrides in settings.

Two prompt layers are required:
- `system prompt`
- `user prompt template`

If a settings override is empty, fallback to the code default.

## Default system prompt intent

The model should behave like a social-content editor who rewrites source material into:
- a clean website article
- a concise and engaging Facebook caption
- content suitable for manual editorial review
- strict JSON output only

## Default user prompt template inputs

The plugin should support placeholders for:
- `{{source_content}}`
- `{{image_context}}`
- `{{language}}`
- `{{site_context}}`
- `{{output_schema}}`

## Output contract

Require strict JSON only:

```json
{
  "title": "string",
  "wordpress_content": "string",
  "facebook_caption": "string",
  "cta_text": "string",
  "suggested_slug": "string",
  "image_notes": ["string"]
}
```

## Constraints

- avoid markdown in `wordpress_content`
- return sanitized HTML blocks suitable for WordPress post content
- do not fabricate unverifiable facts
- avoid excessive emojis
- avoid hashtag stuffing
- keep Facebook caption readable and manually postable
- do not include the short link in AI output; the plugin appends it later

## Recommended post-processing

1. Validate JSON shape.
2. Reject empty `title`, `wordpress_content`, or `facebook_caption`.
3. Sanitize HTML before saving to post content.
4. Store the raw AI payload in post meta for debugging.
