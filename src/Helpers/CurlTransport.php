<?php

declare(strict_types=1);

namespace ReachBell\Helpers;

use ReachBell\Constants\Defaults;
use ReachBell\ReachBellException;
use ReachBell\Types\Transport;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, int $timeout): array
    {
        if ($timeout <= 0) {
            // cURL treats 0 as "no timeout"; never let a bad value hang a request.
            $timeout = Defaults::TIMEOUT;
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ReachBellException('Could not start a cURL request', 0);
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if (!curl_setopt_array($handle, $options)) {
            // E.g. a timeout above cURL's limit: never send without the options applied.
            throw new ReachBellException('Could not configure the cURL request (invalid option, e.g. a timeout above ' . Defaults::MAX_TIMEOUT . ' seconds)', 0);
        }
        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            $code = curl_errno($handle);
            $timedOut = $code === CURLE_OPERATION_TIMEDOUT;
            throw new ReachBellException($timedOut ? 'Request timed out' : 'Request failed: ' . $error, 0);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        return ['status' => $status, 'body' => (string) $response];
    }
}
