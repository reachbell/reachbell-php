# Changelog

## 1.1.0

### Breaking change

- **`serialize()` of the client now throws `LogicException`.** In 1.0.x it worked and wrote the API key in plain text into whatever stored the result (cache, session, queue payload). If you put a `ReachBell` instance on a queued job, pass only the job's data and build the client when the job runs (or inject it from your container into the job's handler). See "Security notes" in the README.

### Other changes

- The API key is marked `#[\SensitiveParameter]` (and the request headers that carry it), so on PHP 8.2+ it no longer appears in exception stack traces or error-tracker frame arguments.
- `var_dump()` / `print_r()` of the client (and its resources) shows `apiKey => [redacted]` instead of the key. `var_export()` cannot be intercepted and still prints the key, so don't `var_export()` the client.
- The `Transport` interface marks `$headers` `#[\SensitiveParameter]`. Custom transports should do the same, since `$headers` carries the API key.
- The `timeout` option must be a positive number of at most `2147483` seconds (about 24.8 days, cURL's limit): `0`, negative, `NAN`, `INF` and larger values now throw `InvalidArgumentException` (cURL treated `0` as "no timeout", and a larger value was silently ignored, leaving no timeout at all). Floats are accepted and rounded up to whole seconds. `CurlTransport` falls back to the default for a non-positive timeout and throws `ReachBellException` instead of sending when cURL rejects one of its options.
- Resource methods always return an array. An empty response is `[]`; a 2xx response that is plain text or a bare JSON scalar now throws `ReachBellException` (with the HTTP status) instead of coming back as `['OK']` or `[true]`.
- `null` values are now dropped recursively from nested arrays and `\stdClass` objects, matching the README (1.0.x only dropped top-level `null`s, so a nested `['data' => ['a' => null]]` used to send `"a":null` and now omits it). Empty arrays are kept. The rule for arrays: one whose keys are all integers in ascending order (a list, or a gapped list such as `array_unique(['a', null, 'a'])`) is a list, so its `null` entries are kept and it is re-indexed and sent as a JSON array (`["a",null]`); any other array is a map, so `null` values are dropped and the keys kept. A map that ends up empty (e.g. `'segment' => ['tags' => null]`) is sent as `{}` rather than `[]`, and one that ends up list-shaped (`[0 => 'a', 'b' => null]`) as `{"0":"a"}`. A `\stdClass` value has its `null` properties dropped and is sent as an object.
- `request()` with a path that already has a query string appends the `$query` parameters with `&` rather than a second `?`.
- A non-JSON error response (e.g. an HTML 502 page) produces a short message like `ReachBell returned 502: Bad Gateway…` instead of the whole page; the raw body stays in `$e->body`.
- Validation messages that contain arrays or objects are JSON-encoded in the exception message instead of raising an "Array to string conversion" warning.
- POST, PUT and PATCH requests without a body send `{}` with `Content-Type: application/json`, so they always carry a `Content-Length`.
- `REACHBELL_API_URL` or the `baseUrl` option set to an empty or whitespace string is treated as unset (the default URL is used). A non-string `baseUrl` throws `TypeError`.
- Custom `headers` are merged case-insensitively, so a header is never sent twice: `content-type`, `accept` or `user-agent` in any casing overrides the SDK default, while `x-api-key` always comes from the client's API key. A custom `Content-Type` is only sent on requests with a body; GET and DELETE requests carry none.
- A non-UTF-8 error page (e.g. Latin-1 HTML) is still shortened in the exception message.
- `request()` also accepts a `\stdClass` body.

## 1.0.1 (2026-11-21)

No code changes. Republished from the current repository history.

## 1.0.0 (2026-11-20)

First release: transactional push, email, SMS and WhatsApp, events with automatic batching, subscribers, email contacts, server campaigns, and `request()` for any other endpoint.
