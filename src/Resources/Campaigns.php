<?php

declare(strict_types=1);

namespace ReachBell\Resources;

use ReachBell\Helpers\HttpClient;
use ReachBell\Utils\Payload;

final class Campaigns
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $campaign
     * @return array<string, mixed>
     */
    public function send(array $campaign): array
    {
        if (isset($campaign['content']) && is_array($campaign['content'])) {
            $campaign['content'] = Payload::compact($campaign['content']);
        }
        if (($campaign['scheduledAt'] ?? null) instanceof \DateTimeInterface) {
            $campaign['scheduledAt'] = $campaign['scheduledAt']->format(\DateTimeInterface::ATOM);
        }
        $campaign['segment'] = isset($campaign['segment']) && $campaign['segment'] !== [] ? $campaign['segment'] : new \stdClass();
        return (array) $this->http->request('POST', '/campaigns/server', Payload::compact($campaign));
    }
}
