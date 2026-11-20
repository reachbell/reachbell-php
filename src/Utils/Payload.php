<?php

declare(strict_types=1);

namespace ReachBell\Utils;

final class Payload
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function compact(array $data): array
    {
        return array_filter($data, static fn ($value) => $value !== null);
    }

    public static function decode(string $body): mixed
    {
        if ($body === '') {
            return null;
        }
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $body;
        }
    }

    public static function message(mixed $body, string $fallback): string
    {
        if (is_array($body) && isset($body['message'])) {
            if (is_array($body['message'])) {
                return implode('; ', array_map('strval', $body['message']));
            }
            if (is_string($body['message'])) {
                return $body['message'];
            }
        }
        if (is_string($body) && $body !== '') {
            return $body;
        }
        return $fallback;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function query(array $query): string
    {
        $query = array_map(
            static fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value,
            array_filter($query, static fn ($value) => $value !== null),
        );
        return $query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
    }
}
