# Changelog

## Unreleased

- The API key is marked `#[\SensitiveParameter]` (and the request headers that carry it), so on PHP 8.2+ it no longer appears in exception stack traces or error-tracker frame arguments.

## 1.0.1 (2026-11-21)

No code changes. Republished from the current repository history.

## 1.0.0 (2026-11-20)

First release: transactional push, email, SMS and WhatsApp, events with automatic batching, subscribers, email contacts, server campaigns, and `request()` for any other endpoint.
