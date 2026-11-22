<?php

declare(strict_types=1);

namespace ReachBell\Utils;

final class Payload
{
    private const SNIPPET_LENGTH = 200;

    /**
     * Drops null values from a payload, recursing into nested arrays and
     * \stdClass objects. Empty arrays are kept (they are often intentional, e.g.
     * clearing a list).
     *
     * Rule for arrays:
     * - An array whose keys are all ints in ascending order (a list, or a list
     *   with gaps such as the result of array_unique() or array_filter()) is a
     *   list: null elements are kept so positions do not shift, and the result is
     *   re-indexed with array_values() so it always encodes as a JSON array.
     * - Every other array is a map: null values are dropped and the remaining
     *   keys are kept. A map that ends up empty or list-shaped, e.g.
     *   ['tags' => null] or [0 => 'a', 'b' => null], is returned as a \stdClass
     *   so it still encodes as a JSON object ({} or {"0":"a"}).
     * - A \stdClass is always a map: null properties are dropped and a \stdClass
     *   is returned.
     *
     * Other objects (e.g. JsonSerializable) are passed through untouched.
     *
     * @param array<array-key, mixed>|\stdClass $data
     * @return array<array-key, mixed>|\stdClass
     */
    public static function compact(array|\stdClass $data): array|\stdClass
    {
        if ($data instanceof \stdClass) {
            return (object) self::compactMap(get_object_vars($data));
        }
        if (self::isListLike($data)) {
            return array_values(array_map([self::class, 'compactValue'], $data));
        }
        $out = self::compactMap($data);
        return array_is_list($out) ? (object) $out : $out;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private static function compactMap(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $out[$key] = self::compactValue($value);
            }
        }
        return $out;
    }

    private static function compactValue(mixed $value): mixed
    {
        return is_array($value) || $value instanceof \stdClass ? self::compact($value) : $value;
    }

    /**
     * Whether every key is an int and the keys are in ascending order.
     *
     * @param array<array-key, mixed> $data
     */
    private static function isListLike(array $data): bool
    {
        if (array_is_list($data)) {
            return true;
        }
        $previous = null;
        foreach ($data as $key => $_) {
            if (!is_int($key) || ($previous !== null && $key <= $previous)) {
                return false;
            }
            $previous = $key;
        }
        return true;
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
        if (preg_match('//u', $text) !== 1) {
            // Not valid UTF-8 (e.g. a Latin-1 error page): the /u pattern below would
            // fail, so scrub invalid bytes, or cut on bytes when mbstring is missing.
            if (!function_exists('mb_scrub')) {
                return strlen($text) > self::SNIPPET_LENGTH
                    ? rtrim(substr($text, 0, self::SNIPPET_LENGTH)) . '…'
                    : $text;
            }
            $text = mb_scrub($text, 'UTF-8');
        }
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
