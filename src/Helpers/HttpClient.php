<?php

declare(strict_types=1);

namespace ReachBell\Helpers;

use ReachBell\Constants\Defaults;
use ReachBell\ReachBellException;
use ReachBell\Types\Transport;
use ReachBell\Utils\Payload;

final class HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly Transport $transport,
        private readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, scalar|null> $query
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/') . Payload::query($query);
        $headers = array_merge(
            ['Accept' => 'application/json', 'User-Agent' => 'reachbell-php/' . Defaults::VERSION],
            $this->headers,
            ['x-api-key' => $this->apiKey],
        );
        $encoded = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $encoded = json_encode($body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException $e) {
                throw new ReachBellException('Could not encode the request body: ' . $e->getMessage(), 0, null, $e);
            }
        }

        $response = $this->transport->send(strtoupper($method), $url, $headers, $encoded, $this->timeout);
        $parsed = Payload::decode($response['body']);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new ReachBellException(
                Payload::message($parsed, 'ReachBell returned ' . $response['status']),
                $response['status'],
                $parsed,
            );
        }
        return $parsed;
    }
}
