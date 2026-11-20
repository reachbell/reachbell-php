<?php

declare(strict_types=1);

namespace ReachBell\Resources;

use ReachBell\Constants\Defaults;
use ReachBell\Helpers\HttpClient;
use ReachBell\Utils\Payload;

final class Events
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function track(array $event): array
    {
        return (array) $this->http->request('POST', '/events', Payload::compact($event));
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public function trackBatch(array $events): array
    {
        $results = [];
        foreach (array_chunk(array_values($events), Defaults::MAX_EVENT_BATCH) as $chunk) {
            $results[] = (array) $this->http->request('POST', '/events', [
                'events' => array_map([Payload::class, 'compact'], $chunk),
            ]);
        }
        return $results;
    }
}
