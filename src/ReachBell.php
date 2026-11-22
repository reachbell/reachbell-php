<?php

declare(strict_types=1);

namespace ReachBell;

use ReachBell\Constants\Defaults;
use ReachBell\Helpers\CurlTransport;
use ReachBell\Helpers\HttpClient;
use ReachBell\Resources\Campaigns;
use ReachBell\Resources\Contacts;
use ReachBell\Resources\Events;
use ReachBell\Resources\Subscribers;
use ReachBell\Resources\Transactional;
use ReachBell\Types\Transport;

final class ReachBell
{
    public readonly Transactional $transactional;
    public readonly Events $events;
    public readonly Subscribers $subscribers;
    public readonly Contacts $contacts;
    public readonly Campaigns $campaigns;
    private readonly HttpClient $http;

    /**
     * @param array{baseUrl?: string, timeout?: int|float, transport?: Transport, headers?: array<string, string>} $options
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, array $options = [])
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new \InvalidArgumentException('ReachBell: an API key is required');
        }
        if (str_starts_with($apiKey, Defaults::PUBLIC_KEY_PREFIX)) {
            trigger_error(
                'ReachBell: you passed a public key (rb_live_). Sending, events, contacts and campaigns need the secret key (rb_secret_).',
                E_USER_NOTICE,
            );
        }
        if (isset($options['baseUrl']) && !is_string($options['baseUrl'])) {
            throw new \TypeError('ReachBell: the baseUrl option must be a string');
        }
        // A blank baseUrl option or REACHBELL_API_URL counts as unset.
        $baseUrl = self::nonBlank($options['baseUrl'] ?? null)
            ?? self::nonBlank(getenv('REACHBELL_API_URL'))
            ?? Defaults::BASE_URL;
        $timeout = $options['timeout'] ?? Defaults::TIMEOUT;
        if (!is_int($timeout) && !is_float($timeout)) {
            throw new \TypeError('ReachBell: the timeout option must be an int or float number of seconds');
        }
        if (!($timeout > 0) || is_infinite($timeout)) {
            throw new \InvalidArgumentException('ReachBell: the timeout option must be a positive number of seconds');
        }

        $this->http = new HttpClient(
            $apiKey,
            rtrim($baseUrl, '/'),
            $timeout,
            $options['transport'] ?? new CurlTransport(),
            $options['headers'] ?? [],
        );
        $this->transactional = new Transactional($this->http);
        $this->events = new Events($this->http);
        $this->subscribers = new Subscribers($this->http);
        $this->contacts = new Contacts($this->http);
        $this->campaigns = new Campaigns($this->http);
    }

    /**
     * Keeps the API key out of var_dump()/print_r() output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['http' => $this->http->__debugInfo()];
    }

    /**
     * @param array<string, mixed>|list<mixed>|\stdClass|null $body
     * @param array<string, scalar|null> $query
     */
    public function request(string $method, string $path, array|\stdClass|null $body = null, array $query = []): mixed
    {
        return $this->http->request($method, $path, $body, $query);
    }

    private static function nonBlank(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
