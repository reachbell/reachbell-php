<?php

declare(strict_types=1);

namespace ReachBell\Types;

interface Transport
{
    /**
     * Implementations should mark $headers with #[\SensitiveParameter] too: it
     * carries the x-api-key header, and without the attribute the key shows up in
     * stack traces of exceptions thrown from send() on PHP 8.2+.
     *
     * $timeout is always a positive number of seconds.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, int $timeout): array;
}
