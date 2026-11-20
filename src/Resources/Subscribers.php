<?php

declare(strict_types=1);

namespace ReachBell\Resources;

use ReachBell\Helpers\HttpClient;

final class Subscribers
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @return array<string, mixed> */
    public function identify(string $token, string $externalId): array
    {
        return (array) $this->http->request('POST', '/subscribers/identify', ['token' => $token, 'externalId' => $externalId]);
    }

    /**
     * @param list<string> $tags
     * @return array<string, mixed>
     */
    public function tag(string $token, array $tags): array
    {
        return (array) $this->http->request('POST', '/subscribers/tag', ['token' => $token, 'tags' => array_values($tags)]);
    }

    /**
     * @param list<string> $tags
     * @return array<string, mixed>
     */
    public function untag(string $token, array $tags): array
    {
        return (array) $this->http->request('POST', '/subscribers/untag', ['token' => $token, 'tags' => array_values($tags)]);
    }

    /** @return array<string, mixed> */
    public function delete(string $token): array
    {
        return (array) $this->http->request('DELETE', '/subscribers/' . rawurlencode($token));
    }
}
