# AutoContent Shorter API WP Plugin

Internal WordPress plugin for a semi-automatic editorial workflow:
- receive source content and source images
- send the source package to a configurable AI provider
- generate a WordPress draft
- request a short link from `oziShortener`
- output a Facebook-ready caption bundle for manual posting

This plugin is designed around three AI providers from day one:
- Grok
- Gemini
- OpenAI

It also supports custom AI API settings and prompt overrides from the WordPress admin settings screen, while keeping safe defaults in code.

## Product goal

Keep publishing under editorial control while automating repetitive work:
- source intake
- AI rewrite
- draft creation
- short-link generation
- Facebook caption preparation

Human steps stay manual:
- choose the source
- review/edit the draft
- publish the post
- post to Facebook manually

## MVP outcome

An editor can:
1. Open an admin screen named `AI Content Desk`.
2. Paste source content and attach one or more images.
3. Choose provider, model, and short-link domain.
4. Click `Generate Draft`.
5. Review generated article content and Facebook caption.
6. Click `Create Short Link`.
7. Copy the Facebook output bundle and publish manually.

## Current implementation scope

This repository now contains:
- implementation docs
- plugin bootstrap
- admin menu and settings registration
- multi-provider service abstraction
- default prompt manager with settings overrides
- shortener API client skeleton
- AI Content Desk admin page scaffold

## Main docs

- [`AGENTS.md`](D:/laragon/www/AutoContent-ShorterAPI-WPPlugin/AGENTS.md)
- [`docs/IMPLEMENTATION-PLAN.md`](D:/laragon/www/AutoContent-ShorterAPI-WPPlugin/docs/IMPLEMENTATION-PLAN.md)
- [`docs/API-CONTRACT.md`](D:/laragon/www/AutoContent-ShorterAPI-WPPlugin/docs/API-CONTRACT.md)
- [`docs/PROMPT-SPEC.md`](D:/laragon/www/AutoContent-ShorterAPI-WPPlugin/docs/PROMPT-SPEC.md)
