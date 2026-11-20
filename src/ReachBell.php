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
     * @param array{baseUrl?: string, timeout?: int, transport?: Transport, headers?: array<string, string>} $options
     */
    public function __construct(string $apiKey, array $options = [])
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
        $env = getenv('REACHBELL_API_URL');
        $baseUrl = $options['baseUrl'] ?? ($env !== false && $env !== '' ? $env : Defaults::BASE_URL);

        $this->http = new HttpClient(
            $apiKey,
            rtrim($baseUrl, '/'),
            $options['timeout'] ?? Defaults::TIMEOUT,
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
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, scalar|null> $query
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        return $this->http->request($method, $path, $body, $query);
    }
}
