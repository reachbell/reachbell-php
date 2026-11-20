<?php

declare(strict_types=1);

namespace ReachBell\Types;

interface Transport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): array;
}
