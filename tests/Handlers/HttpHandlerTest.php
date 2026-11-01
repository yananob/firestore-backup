<?php

declare(strict_types=1);

namespace Tests\Handlers;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use App\Domain\Service\BackupService;
use App\Domain\Service\FirestorePathService;
use App\Handlers\HttpHandler;
use eftec\bladeone\BladeOne;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class HttpHandlerTest extends TestCase
{
    public function testHandleGetIndexRendersHtml(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('test-bucket', 'backups'));
        $repoMock->method('getBackupConfigs')->willReturn([
            'c1' => new BackupConfig('c1', '設定1', 'users'),
        ]);

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);

        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);

        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);
        $bladeMock->method('run')->willReturn('<html>rendered content</html>');

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = new ServerRequest('GET', '/');

        $response = $handler->handle($request);
        $this->assertSame('<html>rendered content</html>', $response);
    }

    public function testHandleApiSuggestionsReturnsJson(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);

        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);
        $pathServiceMock->method('getSuggestions')->willReturn([
            'type' => 'collections',
            'items' => ['users', 'products'],
        ]);

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = (new ServerRequest('GET', '/api/firestore/collections'))->withQueryParams(['path' => '']);

        $response = $handler->handle($request);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['type' => 'collections', 'items' => ['users', 'products']]) ?: '',
            $response
        );
    }

    public function testHandleSaveCommonConfigRedirects(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->expects($this->once())->method('saveCommonConfig');

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);
        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = (new ServerRequest('POST', '/settings/common'))->withParsedBody([
            'bucket' => 'my-bucket',
            'basePath' => 'backups',
        ]);

        $response = $handler->handle($request);
        $this->assertStringContainsString('msg_type=success', $response);
        $this->assertStringContainsString(urlencode('GCS共通設定を保存しました。'), $response);
    }
}
