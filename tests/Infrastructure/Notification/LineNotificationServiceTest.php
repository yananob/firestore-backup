<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification;

use App\Domain\Model\CommonConfig;
use App\Infrastructure\Notification\LineNotificationService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LineNotificationServiceTest extends TestCase
{
    private string|false $originalLineConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLineConfig = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS=' . json_encode([
            'tokens' => ['bot' => 'example-token', 'legacy' => 'legacy-token'],
            'target_ids' => ['group' => 'group-id', 'legacy' => 'legacy-id'],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if ($this->originalLineConfig === false) {
            putenv('LINE_TOKENS_N_TARGETS');
        } else {
            putenv('LINE_TOKENS_N_TARGETS=' . $this->originalLineConfig);
        }
        parent::tearDown();
    }

    public function testSendsWithIndependentTokenAndTargetKeys(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
        $stack->push(Middleware::history($history));
        $service = new LineNotificationService(new Client(['handler' => $stack]));

        $service->notify(new CommonConfig('bucket', '', 'group', 'bot'), [[
            'configName' => '設定1',
            'success' => true,
            'path' => 'gs://bucket/backup.json',
            'count' => 3,
            'sizeBytes' => 1024,
        ]]);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('Bearer ' . 'example-token', $request->getHeaderLine('Authorization'));
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('group-id', $body['to']);
        $this->assertStringContainsString('設定1: 成功、3件、1.0 KB', $body['messages'][0]['text']);
    }

    public function testExistingSettingUsesMatchingTokenKey(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
        $stack->push(Middleware::history($history));
        $service = new LineNotificationService(new Client(['handler' => $stack]));

        $service->notify(CommonConfig::fromArray(['bucket' => 'bucket', 'lineTargetKey' => 'legacy']), []);

        $this->assertCount(1, $history);
        $this->assertSame('Bearer ' . 'legacy-token', $history[0]['request']->getHeaderLine('Authorization'));
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('legacy-id', $body['to']);
    }

    public function testMissingTokenDoesNotSend(): void
    {
        $mock = new MockHandler();
        $service = new LineNotificationService(new Client(['handler' => HandlerStack::create($mock)]));
        $this->expectException(RuntimeException::class);
        $service->notify(new CommonConfig('bucket', '', 'group', 'missing'), []);
    }
}
