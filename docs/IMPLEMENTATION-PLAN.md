# Implementation Plan

## Objective

Build a lightweight WordPress plugin that automates source-to-draft and short-link preparation, while keeping publishing and Facebook posting manual.

## User journey

1. Open `AI Content Desk` in wp-admin.
2. Paste source content.
3. Select or upload one or more source images.
4. Choose AI provider and model.
5. Choose the short-link domain.
6. Click `Generate Draft`.
7. Review the generated title, article body, and Facebook caption.
8. Save or update the draft post.
9. Click `Create Short Link`.
10. Copy the Facebook-ready output bundle.
11. Publish manually.

## MVP screens

### 1. Settings

Fields:
- default provider
- Grok API base URL, API key, model
- Gemini API base URL, API key, model
- OpenAI API base URL, API key, model
- shortener API base URL
- shortener API key
- allowed shortener hostnames
- default shortener hostname
- system prompt override
- user prompt template override

### 2. AI Content Desk

Sections:
- source input
- source image picker
- provider selector
- model override field
- generation action
- generated output preview
- short-link action
- Facebook export box

### 3. Post editor meta box

Show:
- provider used
- generated short link
- selected hostname
- generated Facebook caption
- quick copy buttons

## Technical flow

### A. Generate draft

1. Validate nonce and capability.
2. Save source input temporarily.
3. Resolve provider and model.
4. Build system and user prompts from defaults plus settings overrides.
5. Send request to selected AI provider.
6. Validate returned JSON schema.
7. Create or update a draft post.
8. Save generated assets and raw payload to post meta.
9. Return the post edit link and generated preview.

### B. Create short link

1. Validate nonce and capability.
2. Load the target post permalink.
3. Load selected hostname.
4. Call `oziShortener` `POST /api/links`.
5. Save short-link response into post meta.
6. Return `short_url`, `slug`, and `hostname`.

### C. Export Facebook bundle

1. Read generated Facebook caption.
2. Append the short link if present.
3. Provide a one-click copy output.

## Data ownership

- WordPress post content remains the publishing source of truth.
- Plugin post meta stores generation artifacts and short-link metadata.
- `oziShortener` remains the source of truth for short-link routing and analytics.

## Suggested milestones

### Milestone 1

- plugin bootstrap
- settings page
- secure option storage

### Milestone 2

- provider abstraction
- prompt manager
- source input UI

### Milestone 3

- AI request/response handling
- draft creation/update
- generated preview panel

### Milestone 4

- short-link generation
- hostname selector
- export/copy actions

### Milestone 5

- logging
- error states
- polish and docs

## Acceptance criteria

- An editor can choose Grok, Gemini, or OpenAI.
- Default prompts exist in code and can be overridden in settings.
- The generated AI payload is stored for debugging.
- An editor can manually create a short link for the generated post.
- The editor can copy a Facebook-ready caption that includes the short link.
- No external secret is exposed client-side.
