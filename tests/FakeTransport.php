<?php

declare(strict_types=1);

namespace ReachBell\Tests;

use ReachBell\Types\Transport;

final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: int}> */
    public array $calls = [];

    public function __construct(private int $status = 200, private string $response = '{"success":true}')
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body', 'timeout');
        return ['status' => $this->status, 'body' => $this->response];
    }

    /** @return array<string, mixed> */
    public function json(int $index): array
    {
        return json_decode((string) $this->calls[$index]['body'], true);
    }

    public function route(int $index): string
    {
        return $this->calls[$index]['method'] . ' ' . parse_url($this->calls[$index]['url'], PHP_URL_PATH);
    }
}
