<?php

declare(strict_types=1);

namespace ReachBell\Helpers;

use ReachBell\Constants\Defaults;
use ReachBell\ReachBellException;
use ReachBell\Types\Transport;
use ReachBell\Utils\Payload;

final class HttpClient
{
    private const BODY_METHODS = ['POST', 'PUT', 'PATCH'];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int|float $timeout,
        private readonly Transport $transport,
        private readonly array $headers = [],
    ) {
    }

    /**
     * Keeps the API key out of var_dump()/print_r() output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => '[redacted]',
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'transport' => get_class($this->transport),
            'headers' => array_keys($this->headers),
        ];
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, scalar|null> $query
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        return $this->send($method, $path, $body, $query)[1];
    }

    /**
     * Like request(), but the response must be a JSON object or array. An empty
     * body (e.g. 204) becomes []; any other 2xx body (plain text, a bare JSON
     * scalar) throws a ReachBellException carrying the HTTP status and raw body.
     *
     * @param array<string, mixed>|list<mixed>|null $body
     * @return array<mixed>
     */
    public function requestArray(string $method, string $path, ?array $body = null): array
    {
        [$status, $parsed] = $this->send($method, $path, $body, []);
        if ($parsed === null) {
            return [];
        }
        if (!is_array($parsed)) {
            $snippet = is_string($parsed) ? Payload::snippet($parsed) : (string) json_encode($parsed);
            throw new ReachBellException(
                "ReachBell returned {$status} with an unexpected response (expected a JSON object): {$snippet}",
                $status,
                $parsed,
            );
        }
        return $parsed;
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $body
     * @param array<string, scalar|null> $query
     * @return array{0: int, 1: mixed}
     */
    private function send(string $method, string $path, ?array $body, array $query): array
    {
        $method = strtoupper($method);
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $queryString = Payload::query($query);
        if ($queryString !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }
        $headers = array_merge(
            ['Accept' => 'application/json', 'User-Agent' => 'reachbell-php/' . Defaults::VERSION],
            $this->headers,
            ['x-api-key' => $this->apiKey],
        );
        if ($body === null && in_array($method, self::BODY_METHODS, true)) {
            // Send an empty JSON object rather than no body at all, so the request
            // carries Content-Type and Content-Length like every other write.
            $body = [];
        }
        $encoded = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $encoded = json_encode($body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException $e) {
                throw new ReachBellException('Could not encode the request body: ' . $e->getMessage(), 0, null, $e);
            }
        }

        // The Transport contract takes whole seconds; round fractional timeouts up.
        $response = $this->transport->send($method, $url, $headers, $encoded, (int) ceil($this->timeout));
        $parsed = Payload::decode($response['body']);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new ReachBellException(
                Payload::message($parsed, 'ReachBell returned ' . $response['status'], $response['status']),
                $response['status'],
                $parsed,
            );
        }
        return [$response['status'], $parsed];
    }
}
