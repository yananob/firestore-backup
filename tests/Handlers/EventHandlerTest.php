<?php

declare(strict_types=1);

namespace Tests\Handlers;

use App\Domain\Service\BackupService;
use App\Handlers\EventHandler;
use CloudEvents\V1\CloudEventInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class EventHandlerTest extends TestCase
{
    public function testHandleExecutesBackupAllAndLogs(): void
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

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        $backupServiceMock->expects($this->once())
            ->method('backupAll')
            ->willReturn([
                'total' => 2,
                'success' => 2,
                'failed' => 0,
                'details' => [],
            ]);

        $eventHandler = new EventHandler($testLogger, $backupServiceMock);

        /** @var CloudEventInterface&\PHPUnit\Framework\MockObject\MockObject $eventMock */
        $eventMock = $this->createMock(CloudEventInterface::class);
        $eventMock->method('getId')->willReturn('event-12345');
        $eventMock->method('getType')->willReturn('google.cloud.pubsub.topic.publish');

        $eventHandler->handle($eventMock);

        $this->assertCount(2, $loggedMessages);
        $this->assertSame('info', $loggedMessages[0]['level']);
        $this->assertStringContainsString('Starting Firestore backup triggered by CloudEvent (ID: event-12345', (string) $loggedMessages[0]['message']);
        $this->assertSame('info', $loggedMessages[1]['level']);
        $this->assertStringContainsString('Backup completed: Total: 2, Success: 2, Failed: 0', (string) $loggedMessages[1]['message']);
    }
}
