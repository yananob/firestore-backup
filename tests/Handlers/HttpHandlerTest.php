<?php

declare(strict_types=1);

namespace Tests\Handlers;

use App\Handlers\HttpHandler;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class HttpHandlerTest extends TestCase
{
    public function testHandleReturnsHelloWorld(): void
    {
        $handler = new HttpHandler();
        $request = new ServerRequest('GET', '/');

        $response = $handler->handle($request);

        $this->assertSame('Hello, World!', $response);
    }

    public function testHandleLogsRequestDetails(): void
    {
        $loggedMessages = [];

        $testLogger = new class($loggedMessages) extends AbstractLogger {
            /** @param array<int, array{level: mixed, message: string|\Stringable, context: array<mixed>}> $messages */
            public function __construct(public array &$messages)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $handler = new HttpHandler($testLogger);
        $request = new ServerRequest('POST', '/api/v1/resource');

        $response = $handler->handle($request);

        $this->assertSame('Hello, World!', $response);
        $this->assertCount(1, $loggedMessages);
        $this->assertSame('info', $loggedMessages[0]['level']);
        $this->assertSame('HTTP request received', $loggedMessages[0]['message']);
        $this->assertSame('POST', $loggedMessages[0]['context']['method']);
        $this->assertSame('/api/v1/resource', $loggedMessages[0]['context']['uri']);
    }
}
