<?php

declare(strict_types=1);

namespace ReachBell\Utils;

final class Payload
{
    private const SNIPPET_LENGTH = 200;

    /**
     * Drops null values from a payload, recursing into nested maps. Empty arrays
     * are kept (they are often intentional, e.g. clearing a list), and null
     * elements inside lists are kept so positional values do not shift.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function compact(array $data): array
    {
        $isList = array_is_list($data);
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null && !$isList) {
                continue;
            }
            $out[$key] = is_array($value) ? self::compact($value) : $value;
        }
        return $out;
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

    public static function message(mixed $body, string $fallback, ?int $status = null): string
    {
        if (is_array($body) && isset($body['message'])) {
            if (is_array($body['message'])) {
                return implode('; ', array_map([self::class, 'stringify'], $body['message']));
            }
            if (is_string($body['message'])) {
                return $body['message'];
            }
        }
        if (is_string($body) && trim($body) !== '') {
            $snippet = self::snippet($body);
            return $status !== null ? "ReachBell returned {$status}: {$snippet}" : $snippet;
        }
        return $fallback;
    }

    /**
     * A short single-line excerpt of a raw (non-JSON) response body, e.g. an HTML error page.
     */
    public static function snippet(string $body): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
        // Cut on a UTF-8 character boundary without requiring ext-mbstring.
        if (preg_match('/^.{' . self::SNIPPET_LENGTH . '}(?=.)/su', $text, $m) === 1) {
            return rtrim($m[0]) . '…';
        }
        return $text;
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
        return $query ? http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '';
    }

    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
