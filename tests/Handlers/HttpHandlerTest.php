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
    public function testIndexShowsIndependentLineOptionsAndSelectedValues(): void
    {
        $originalLineConfig = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS=' . json_encode([
            'tokens' => ['bot' => 'example-token'],
            'target_ids' => ['group' => 'group-id'],
        ], JSON_THROW_ON_ERROR));

        try {
            /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
            $repoMock = $this->createMock(ConfigRepositoryInterface::class);
            $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('bucket', '', 'group', 'bot'));
            $repoMock->method('getBackupConfigs')->willReturn([]);
            /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
            $backupServiceMock = $this->createMock(BackupService::class);
            /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
            $pathServiceMock = $this->createMock(FirestorePathService::class);
            $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock);

            $html = $handler->handle(new ServerRequest('GET', '/'));

            $this->assertStringContainsString('name="lineTargetKey"', $html);
            $this->assertStringContainsString('value="group" selected', $html);
            $this->assertStringContainsString('name="lineTokenKey"', $html);
            $this->assertStringContainsString('value="bot" selected', $html);
            $this->assertStringNotContainsString('example-token', $html);
            $this->assertStringNotContainsString('group-id', $html);
        } finally {
            if ($originalLineConfig === false) {
                putenv('LINE_TOKENS_N_TARGETS');
            } else {
                putenv('LINE_TOKENS_N_TARGETS=' . $originalLineConfig);
            }
        }
    }

    public function testSaveCommonConfigRejectsMissingTokenForTarget(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->expects($this->never())->method('saveCommonConfig');
        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);
        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $response = $handler->handle((new ServerRequest('POST', '/settings/common'))->withParsedBody([
            'bucket' => 'bucket',
            'lineTargetKey' => 'group',
            'lineTokenKey' => '',
        ]));

        $this->assertStringContainsString('msg_type=error', $response);
    }

    public function testHandleGetIndexRendersHtml(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('test-bucket', 'backups'));
        $repoMock->method('getBackupConfigs')->willReturn([
            'c1' => new BackupConfig('c1', '設定1', 'users/u1'),
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
        $repoMock->expects($this->once())->method('saveCommonConfig')
            ->with($this->callback(static fn (CommonConfig $config): bool => $config->getLineTargetKey() === 'group' && $config->getLineTokenKey() === 'bot'));

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
            'lineTargetKey' => 'group',
            'lineTokenKey' => 'bot',
        ]);

        $response = $handler->handle($request);
        $this->assertStringContainsString('msg_type=success', $response);
        $this->assertStringContainsString(urlencode('GCS共通設定を保存しました。'), $response);
        // リダイレクトレスポンスにダークモードスタイルが含まれること
        $this->assertStringContainsString('background-color: #030712;', $response);
    }

    public function testHandleCreateBackupConfigRejectsRootCollection(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->expects($this->never())->method('saveBackupConfig');

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);
        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = (new ServerRequest('POST', '/settings/configs'))->withParsedBody([
            'name' => 'ルートコレクション設定',
            'firestorePath' => 'users',
            'enabled' => '1',
        ]);

        $response = $handler->handle($request);
        $this->assertStringContainsString('msg_type=error', $response);
        $this->assertStringContainsString(urlencode('ルートコレクションは指定できません。'), $response);
    }

    public function testHandleUpdateBackupConfigRejectsRootCollection(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->expects($this->never())->method('saveBackupConfig');

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);
        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = (new ServerRequest('POST', '/settings/configs/update'))->withParsedBody([
            'id' => 'cfg-1',
            'name' => 'ルートコレクション更新',
            'firestorePath' => 'groups',
            'enabled' => '1',
        ]);

        $response = $handler->handle($request);
        $this->assertStringContainsString('msg_type=error', $response);
        $this->assertStringContainsString(urlencode('ルートコレクションは指定できません。'), $response);
    }

    public function testHandleGetIndexWithExistingRootConfigRendersSuccessfully(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('test-bucket', 'backups'));
        // すでにDBにルートパス設定が存在していてもエラーにならず描画されること
        $repoMock->method('getBackupConfigs')->willReturn([
            'c1' => new BackupConfig('c1', 'ルート設定', 'users'),
        ]);

        /** @var BackupService&\PHPUnit\Framework\MockObject\MockObject $backupServiceMock */
        $backupServiceMock = $this->createMock(BackupService::class);
        /** @var FirestorePathService&\PHPUnit\Framework\MockObject\MockObject $pathServiceMock */
        $pathServiceMock = $this->createMock(FirestorePathService::class);

        /** @var BladeOne&\PHPUnit\Framework\MockObject\MockObject $bladeMock */
        $bladeMock = $this->createMock(BladeOne::class);
        $bladeMock->expects($this->once())->method('run')->with('index', $this->callback(function (array $data): bool {
            $configs = $data['configs'];
            return isset($configs['c1']) && $configs['c1']->isRootCollection();
        }))->willReturn('<html>rendered</html>');

        $handler = new HttpHandler($repoMock, $backupServiceMock, $pathServiceMock, $bladeMock);
        $request = new ServerRequest('GET', '/');

        $response = $handler->handle($request);
        $this->assertSame('<html>rendered</html>', $response);
    }
}
