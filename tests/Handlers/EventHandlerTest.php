<?php

declare(strict_types=1);

namespace Tests\Handlers;

use App\Handlers\EventHandler;
use CloudEvents\V1\CloudEventInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleLogsHelloWorld(): void
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

        $eventHandler = new EventHandler($testLogger);

        /** @var CloudEventInterface&\PHPUnit\Framework\MockObject\MockObject $eventMock */
        $eventMock = $this->createMock(CloudEventInterface::class);

        $eventHandler->handle($eventMock);

        $this->assertCount(1, $loggedMessages);
        $this->assertSame('info', $loggedMessages[0]['level']);
        $this->assertSame('Hello, World!', $loggedMessages[0]['message']);
    }

    public function testHandleLogsCloudEventContext(): void
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

        $eventHandler = new EventHandler($testLogger);

        /** @var CloudEventInterface&\PHPUnit\Framework\MockObject\MockObject $eventMock */
        $eventMock = $this->createMock(CloudEventInterface::class);
        $eventMock->method('getId')->willReturn('event-12345');
        $eventMock->method('getType')->willReturn('google.cloud.pubsub.topic.v1.messagePublished');
        $eventMock->method('getSource')->willReturn('//pubsub.googleapis.com/test-topic');

        $eventHandler->handle($eventMock);

        $this->assertCount(1, $loggedMessages);
        $this->assertSame([
            'id' => 'event-12345',
            'type' => 'google.cloud.pubsub.topic.v1.messagePublished',
            'source' => '//pubsub.googleapis.com/test-topic',
        ], $loggedMessages[0]['context']);
    }

    public function testHandleDefaultConstructorRunsWithoutError(): void
    {
        $eventHandler = new EventHandler();

        /** @var CloudEventInterface&\PHPUnit\Framework\MockObject\MockObject $eventMock */
        $eventMock = $this->createMock(CloudEventInterface::class);

        $eventHandler->handle($eventMock);

        $this->expectNotToPerformAssertions();
    }
}
