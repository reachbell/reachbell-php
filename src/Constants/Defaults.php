<?php

declare(strict_types=1);

namespace ReachBell\Constants;

final class Defaults
{
    public const VERSION = '1.1.0';
    public const BASE_URL = 'https://api.reachbell.com';
    public const TIMEOUT = 15;
    /**
     * Largest accepted timeout in seconds (about 24.8 days): cURL rejects a
     * CURLOPT_TIMEOUT above INT_MAX / 1000, and it also keeps ceil() in int range.
     */
    public const MAX_TIMEOUT = 2147483;
    public const MAX_EVENT_BATCH = 25;
    public const PUBLIC_KEY_PREFIX = 'rb_live_';
}
