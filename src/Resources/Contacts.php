<?php

declare(strict_types=1);

namespace ReachBell\Resources;

use ReachBell\Helpers\HttpClient;
use ReachBell\Utils\Payload;

final class Contacts
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $contact
     * @return array<string, mixed>
     */
    public function upsertEmail(array $contact): array
    {
        return $this->http->requestArray('POST', '/contacts/email', Payload::compact($contact));
    }

    /** @return array<string, mixed> */
    public function unsubscribeEmail(string $email): array
    {
        return $this->http->requestArray('POST', '/contacts/email/unsubscribe', ['email' => $email]);
    }
}
