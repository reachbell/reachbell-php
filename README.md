# reachbell/reachbell

Server SDK for [ReachBell](https://reachbell.com). Send transactional push, email, SMS and WhatsApp, track events, manage subscribers and email contacts, and send campaigns from PHP.

Requires PHP 8.1 or later with the `curl` and `json` extensions. No other dependencies.

```bash
composer require reachbell/reachbell
```

```php
use ReachBell\ReachBell;
use ReachBell\ReachBellException;

$rb = new ReachBell(getenv('REACHBELL_SECRET_KEY'));

try {
    $result = $rb->transactional->send([
        'externalId' => 'user_42',
        'title'      => 'Order shipped',
        'body'       => 'Your order arrives tomorrow.',
        'url'        => 'https://shop.example/orders/4821',
    ]);
} catch (ReachBellException $e) {
    error_log($e->statusCode . ' ' . $e->getMessage());
}
```

Use the secret key (`rb_secret_…`) from **Settings → API keys** and keep it on your server. Option arrays use the API's camelCase keys, and `null` values are dropped, including inside nested arrays (empty arrays, and `null` entries inside lists, are sent as given).

| Method | Endpoint |
|---|---|
| `transactional->send($params)` | `POST /transactional/send` |
| `transactional->email($params)` | `POST /transactional/email` |
| `transactional->sms($params)` | `POST /transactional/sms` |
| `transactional->whatsapp($params)` | `POST /transactional/whatsapp` |
| `events->track($event)` | `POST /events` |
| `events->trackBatch($events)` | `POST /events` (sent in chunks of 25) |
| `subscribers->identify($token, $externalId)` | `POST /subscribers/identify` |
| `subscribers->tag($token, $tags)` | `POST /subscribers/tag` |
| `subscribers->untag($token, $tags)` | `POST /subscribers/untag` |
| `subscribers->delete($token)` | `DELETE /subscribers/{token}` |
| `contacts->upsertEmail($contact)` | `POST /contacts/email` |
| `contacts->unsubscribeEmail($email)` | `POST /contacts/email/unsubscribe` |
| `campaigns->send($campaign)` | `POST /campaigns/server` |
| `request($method, $path, $body, $query)` | any endpoint |

Failed requests throw `ReachBell\ReachBellException` with `statusCode` (`0` for network errors and timeouts) and the decoded response `body`. A non-JSON error page is shortened to a one-line snippet in the message (the full text stays in `body`). The resource methods always return an array: an empty response becomes `[]`, and a 2xx response that is not a JSON object or array (plain text, a bare `true`) throws a `ReachBellException` with that status. `request()` returns the decoded body as-is.

Options (second constructor argument): `baseUrl` (defaults to `REACHBELL_API_URL` or `https://api.reachbell.com`), `timeout` in seconds (default 15; must be positive, fractions round up to whole seconds), `headers`, and `transport` (any `ReachBell\Types\Transport`).

A custom transport receives the `x-api-key` header in `$headers`. Mark that parameter `#[\SensitiveParameter]`, as `CurlTransport` does, so the key stays out of stack traces on PHP 8.2+:

```php
public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, int $timeout): array
```

Full documentation: https://docs.reachbell.com/server/php

## License

MIT
