<?php

declare(strict_types=1);

namespace ReachBell\Tests;

use PHPUnit\Framework\TestCase;
use ReachBell\ReachBell;
use ReachBell\ReachBellException;

final class ReachBellTest extends TestCase
{
    private function client(FakeTransport $transport): ReachBell
    {
        return new ReachBell('rb_secret_test', ['baseUrl' => 'https://api.test/', 'transport' => $transport, 'timeout' => 7]);
    }

    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReachBell(' ');
    }

    public function testTransactionalSendSetsHeadersAndDropsNulls(): void
    {
        $t = new FakeTransport(200, '{"success":true,"delivered":true}');
        $r = $this->client($t)->transactional->send(['externalId' => 'u1', 'title' => 'Hi', 'body' => 'There', 'url' => null]);

        $this->assertTrue($r['delivered']);
        $call = $t->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://api.test/transactional/send', $call['url']);
        $this->assertSame('rb_secret_test', $call['headers']['x-api-key']);
        $this->assertSame('application/json', $call['headers']['Content-Type']);
        $this->assertStringStartsWith('reachbell-php/', $call['headers']['User-Agent']);
        $this->assertSame(7, $call['timeout']);
        $this->assertSame(['externalId' => 'u1', 'title' => 'Hi', 'body' => 'There'], $t->json(0));
    }

    public function testChannelRoutes(): void
    {
        $t = new FakeTransport();
        $rb = $this->client($t);
        $rb->transactional->email(['to' => 'a@b.co', 'subject' => 'S', 'htmlContent' => '<p>x</p>']);
        $rb->transactional->sms(['externalId' => 'u1', 'text' => 'code 1']);
        $rb->transactional->whatsapp(['to' => '+15550100', 'template' => ['name' => 'otp', 'params' => ['1']]]);

        $this->assertSame(
            ['POST /transactional/email', 'POST /transactional/sms', 'POST /transactional/whatsapp'],
            [$t->route(0), $t->route(1), $t->route(2)],
        );
        $this->assertSame('<p>x</p>', $t->json(0)['htmlContent']);
    }

    public function testEventBatchesAreChunked(): void
    {
        $t = new FakeTransport(200, '{"accepted":25}');
        $events = array_map(fn ($i) => ['name' => 'e', 'externalId' => "u$i", 'subscriberId' => null], range(1, 60));
        $results = $this->client($t)->events->trackBatch($events);

        $this->assertCount(3, $results);
        $this->assertSame([25, 25, 10], array_map(fn ($i) => count($t->json($i)['events']), [0, 1, 2]));
        $this->assertArrayNotHasKey('subscriberId', $t->json(0)['events'][0]);
    }

    public function testSubscribersAndContacts(): void
    {
        $t = new FakeTransport();
        $rb = $this->client($t);
        $rb->subscribers->identify('t/1', 'u1');
        $rb->subscribers->tag('t/1', ['vip']);
        $rb->subscribers->untag('t/1', ['vip']);
        $rb->subscribers->delete('t/1');
        $rb->contacts->upsertEmail(['email' => 'a@b.co', 'tags' => ['news']]);
        $rb->contacts->unsubscribeEmail('a@b.co');

        $this->assertSame([
            'POST /subscribers/identify',
            'POST /subscribers/tag',
            'POST /subscribers/untag',
            'DELETE /subscribers/t%2F1',
            'POST /contacts/email',
            'POST /contacts/email/unsubscribe',
        ], array_map(fn ($i) => $t->route($i), range(0, 5)));
        $this->assertNull($t->calls[3]['body']);
        $this->assertArrayNotHasKey('Content-Type', $t->calls[3]['headers']);
        $this->assertSame(['token' => 't/1', 'externalId' => 'u1'], $t->json(0));
    }

    public function testCampaignSendFormatsDateAndEmptySegment(): void
    {
        $t = new FakeTransport();
        $this->client($t)->campaigns->send([
            'name' => 'Sale',
            'content' => ['title' => 'Sale', 'body' => 'Today', 'iconUrl' => null],
            'scheduledAt' => new \DateTimeImmutable('2026-12-01T10:00:00Z'),
        ]);

        $this->assertSame('{"name":"Sale","content":{"title":"Sale","body":"Today"},"scheduledAt":"2026-12-01T10:00:00+00:00","segment":{}}', $t->calls[0]['body']);
    }

    public function testErrorResponsesThrow(): void
    {
        $t = new FakeTransport(403, '{"statusCode":403,"message":"Plan does not include transactionalApi"}');
        try {
            $this->client($t)->transactional->send(['externalId' => 'u', 'title' => 't', 'body' => 'b']);
            $this->fail('expected exception');
        } catch (ReachBellException $e) {
            $this->assertSame(403, $e->statusCode);
            $this->assertSame('Plan does not include transactionalApi', $e->getMessage());
            $this->assertSame(403, $e->body['statusCode']);
        }
    }

    public function testValidationMessagesAreJoined(): void
    {
        $t = new FakeTransport(400, '{"message":["title must be a string","body must be a string"]}');
        $this->expectExceptionMessage('title must be a string; body must be a string');
        $this->client($t)->request('POST', '/x', ['a' => 1]);
    }

    public function testRequestWithQueryAndEmptyResponse(): void
    {
        $t = new FakeTransport(200, '');
        $this->assertNull($this->client($t)->request('GET', 'billing/plans', null, ['currency' => 'USD', 'skip' => null, 'all' => true]));
        $this->assertSame('https://api.test/billing/plans?currency=USD&all=true', $t->calls[0]['url']);
    }

    public function testApiKeyIsRedactedFromStackTraces(): void
    {
        if (PHP_VERSION_ID < 80200) {
            $this->markTestSkipped('#[SensitiveParameter] needs PHP 8.2');
        }
        // With zend.exception_ignore_args=On (the php.ini-production default) traces
        // carry no arguments at all and the assertions below would pass vacuously.
        $previous = ini_get('zend.exception_ignore_args');
        if (ini_set('zend.exception_ignore_args', '0') === false) {
            $this->markTestSkipped('zend.exception_ignore_args cannot be changed at runtime here');
        }
        try {
            try {
                new ReachBell('rb_secret_hidden', ['transport' => new FakeTransport(), 'timeout' => '15']);
                $this->fail('expected TypeError');
            } catch (\TypeError $e) {
                $this->assertNotEmpty(array_filter(array_column($e->getTrace(), 'args')), 'trace should carry arguments');
                $this->assertStringNotContainsString('rb_secret_hidden', $e->getTraceAsString());
                $this->assertStringNotContainsString('rb_secret_hidden', var_export(array_column($e->getTrace(), 'args'), true));
            }

            $throwing = new class implements \ReachBell\Types\Transport {
                public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, int $timeout): array
                {
                    throw new \RuntimeException('network down');
                }
            };
            try {
                (new ReachBell('rb_secret_hidden', ['transport' => $throwing]))->request('GET', '/x');
                $this->fail('expected RuntimeException');
            } catch (\RuntimeException $e) {
                $this->assertStringNotContainsString('rb_secret_hidden', var_export(array_column($e->getTrace(), 'args'), true));
            }
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    public function testDebugOutputRedactsApiKey(): void
    {
        $rb = new ReachBell('rb_secret_hidden', ['transport' => new FakeTransport()]);
        ob_start();
        var_dump($rb, $rb->transactional);
        $dump = (string) ob_get_clean() . print_r($rb, true) . print_r($rb->events, true);
        $this->assertStringNotContainsString('rb_secret_hidden', $dump);
        $this->assertStringContainsString('[redacted]', $dump);
    }

    public function testRejectsNonPositiveTimeouts(): void
    {
        foreach ([0, -1, 0.0, NAN, INF] as $bad) {
            try {
                new ReachBell('rb_secret_test', ['transport' => new FakeTransport(), 'timeout' => $bad]);
                $this->fail('expected InvalidArgumentException for ' . var_export($bad, true));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFloatTimeoutIsAcceptedAndRoundedUp(): void
    {
        $t = new FakeTransport();
        (new ReachBell('rb_secret_test', ['transport' => $t, 'timeout' => 2.5]))->request('GET', '/x');
        $this->assertSame(3, $t->calls[0]['timeout']);
    }

    public function testQueryIsAppendedToPathThatAlreadyHasOne(): void
    {
        $t = new FakeTransport();
        $this->client($t)->request('GET', '/x?a=1', null, ['b' => 2]);
        $this->assertSame('https://api.test/x?a=1&b=2', $t->calls[0]['url']);
    }

    public function testNonJsonSuccessBodyThrowsFromResources(): void
    {
        foreach (['OK', 'true', '"done"'] as $raw) {
            try {
                $this->client(new FakeTransport(200, $raw))->events->track(['name' => 'e', 'externalId' => 'u']);
                $this->fail('expected exception for ' . $raw);
            } catch (ReachBellException $e) {
                $this->assertSame(200, $e->statusCode);
                $this->assertStringContainsString('unexpected response', $e->getMessage());
            }
        }
        $this->assertSame([], $this->client(new FakeTransport(204, ''))->subscribers->delete('t'));
    }

    public function testNestedNullsAreDroppedButEmptyArraysAndListNullsKept(): void
    {
        $t = new FakeTransport();
        $this->client($t)->transactional->whatsapp([
            'to' => '+15550100',
            'template' => ['name' => 'otp', 'language' => null, 'params' => ['1', null], 'buttons' => []],
            'data' => ['a' => ['b' => null, 'c' => 1]],
        ]);
        $this->assertSame(
            '{"to":"+15550100","template":{"name":"otp","params":["1",null],"buttons":[]},"data":{"a":{"c":1}}}',
            $t->calls[0]['body'],
        );
    }

    public function testNonJsonErrorPageIsTruncated(): void
    {
        $html = '<html><body><h1>502 Bad Gateway</h1>' . str_repeat('<p>nginx padding</p>', 100) . '</body></html>';
        try {
            $this->client(new FakeTransport(502, $html))->request('GET', '/x');
            $this->fail('expected exception');
        } catch (ReachBellException $e) {
            $this->assertStringStartsWith('ReachBell returned 502: 502 Bad Gateway', $e->getMessage());
            $this->assertLessThan(260, strlen($e->getMessage()));
            $this->assertStringNotContainsString('<', $e->getMessage());
            $this->assertSame($html, $e->body);
        }
    }

    public function testNestedValidationMessagesAreEncoded(): void
    {
        $t = new FakeTransport(400, '{"message":["bad title",{"field":"body","error":"required"},3]}');
        $this->expectExceptionMessage('bad title; {"field":"body","error":"required"}; 3');
        $this->client($t)->request('POST', '/x', ['a' => 1]);
    }

    public function testPostWithoutBodySendsEmptyJsonObject(): void
    {
        $t = new FakeTransport();
        $this->client($t)->request('POST', '/x');
        $this->assertSame('{}', $t->calls[0]['body']);
        $this->assertSame('application/json', $t->calls[0]['headers']['Content-Type']);
    }

    public function testPublicKeyRaisesNotice(): void
    {
        $raised = false;
        set_error_handler(function (int $no) use (&$raised) {
            $raised = $no === E_USER_NOTICE;
            return true;
        });
        new ReachBell('rb_live_x', ['transport' => new FakeTransport()]);
        restore_error_handler();
        $this->assertTrue($raised);
    }
}
