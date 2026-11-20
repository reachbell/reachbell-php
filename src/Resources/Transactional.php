<?php

declare(strict_types=1);

namespace ReachBell\Resources;

use ReachBell\Helpers\HttpClient;
use ReachBell\Utils\Payload;

final class Transactional
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        return (array) $this->http->request('POST', '/transactional/send', Payload::compact($params));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function email(array $params): array
    {
        return (array) $this->http->request('POST', '/transactional/email', Payload::compact($params));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function sms(array $params): array
    {
        return (array) $this->http->request('POST', '/transactional/sms', Payload::compact($params));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function whatsapp(array $params): array
    {
        return (array) $this->http->request('POST', '/transactional/whatsapp', Payload::compact($params));
    }
}
