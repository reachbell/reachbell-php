# Changelog

## 1.1.0

- The API key is marked `#[\SensitiveParameter]` (and the request headers that carry it), so on PHP 8.2+ it no longer appears in exception stack traces or error-tracker frame arguments.
- `var_dump()` / `print_r()` of the client (and its resources) shows `apiKey => [redacted]` instead of the key, and `serialize()` of the client throws `LogicException` instead of writing the key out.
- The `Transport` interface marks `$headers` `#[\SensitiveParameter]`. Custom transports should do the same, since `$headers` carries the API key.
- The `timeout` option must be a positive number: `0`, negative, `NAN` and `INF` now throw `InvalidArgumentException` (cURL treated `0` as "no timeout"). Floats are accepted and rounded up to whole seconds. `CurlTransport` also falls back to the default for a non-positive timeout.
- Resource methods always return an array. An empty response is `[]`; a 2xx response that is plain text or a bare JSON scalar now throws `ReachBellException` (with the HTTP status) instead of coming back as `['OK']` or `[true]`.
- `null` values are now dropped recursively from nested arrays, matching the README (1.0.x only dropped top-level `null`s, so a nested `['data' => ['a' => null]]` used to send `"a":null` and now omits it). Empty arrays and `null` entries inside lists are kept. A nested map whose values were all `null` (e.g. `'segment' => ['tags' => null]`) is sent as `{}` rather than `[]`, and maps with integer keys keep their keys instead of turning into a JSON list.
- `request()` with a path that already has a query string appends the `$query` parameters with `&` rather than a second `?`.
- A non-JSON error response (e.g. an HTML 502 page) produces a short message like `ReachBell returned 502: Bad Gateway…` instead of the whole page; the raw body stays in `$e->body`.
- Validation messages that contain arrays or objects are JSON-encoded in the exception message instead of raising an "Array to string conversion" warning.
- POST, PUT and PATCH requests without a body send `{}` with `Content-Type: application/json`, so they always carry a `Content-Length`.
- `REACHBELL_API_URL` or the `baseUrl` option set to an empty or whitespace string is treated as unset (the default URL is used). A non-string `baseUrl` throws `TypeError`.
- Custom `headers` are merged case-insensitively, so a header is never sent twice: `content-type`, `accept` or `user-agent` in any casing overrides the SDK default, while `x-api-key` always comes from the client's API key.
- A non-UTF-8 error page (e.g. Latin-1 HTML) is still shortened in the exception message.
- `request()` also accepts a `\stdClass` body.

## 1.0.1 (2026-11-21)

No code changes. Republished from the current repository history.

## 1.0.0 (2026-11-20)

First release: transactional push, email, SMS and WhatsApp, events with automatic batching, subscribers, email contacts, server campaigns, and `request()` for any other endpoint.
