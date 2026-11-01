<?php

declare(strict_types=1);

namespace Tests\Domain\Service;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use App\Domain\Service\BackupService;
use App\Domain\Service\StorageServiceInterface;
use Carbon\Carbon;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class BackupServiceTest extends TestCase
{
    public function testBackupSingleConfigUploadsToGcs(): void
    {
        $backupConfig = new BackupConfig('c1', 'users-data', 'users');
        $commonConfig = new CommonConfig('my-test-bucket', 'backups');

        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);

        $uploaded = [];
        $storageMock = new class($uploaded) implements StorageServiceInterface {
            /** @param array<int, array{bucket: string, path: string, content: string}> $uploaded */
            public function __construct(public array &$uploaded)
            {
            }

            public function upload(string $bucket, string $objectPath, string $content): void
            {
                $this->uploaded[] = [
                    'bucket' => $bucket,
                    'path' => $objectPath,
                    'content' => $content,
                ];
            }
        };

        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);

        $backupService = new class($repoMock, $storageMock, $firestoreMock) extends BackupService {
            /** @return array<string, mixed> */
            public function fetchDataFromPath(string $firestorePath): array
            {
                return [
                    'u1' => ['name' => 'Alice', 'role' => 'admin'],
                    'u2' => ['name' => 'Bob', 'role' => 'user'],
                ];
            }
        };

        $fixedDate = Carbon::parse('2026-11-01 15:30:00');
        $result = $backupService->backupSingleConfig($backupConfig, $commonConfig, $fixedDate);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['count']);
        $this->assertSame('gs://my-test-bucket/backups/20261101/c1_users-data.json', $result['path']);

        $this->assertCount(1, $uploaded);
        $this->assertSame('my-test-bucket', $uploaded[0]['bucket']);
        $this->assertSame('backups/20261101/c1_users-data.json', $uploaded[0]['path']);

        $decoded = json_decode($uploaded[0]['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('c1', $decoded['configId']);
        $this->assertSame('users-data', $decoded['configName']);
        $this->assertSame('users', $decoded['firestorePath']);
        $this->assertSame(2, $decoded['itemCount']);
        $this->assertArrayHasKey('u1', $decoded['data']);
    }

    public function testBackupAllExecutesEnabledConfigs(): void
    {
        $c1 = new BackupConfig('c1', 'config1', 'users', true);
        $c2 = new BackupConfig('c2', 'config2', 'logs', false); // disabled

        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('test-bucket', ''));
        $repoMock->method('getBackupConfigs')->willReturn(['c1' => $c1, 'c2' => $c2]);

        $uploaded = [];
        $storageMock = new class($uploaded) implements StorageServiceInterface {
            /** @param array<int, array{bucket: string, path: string, content: string}> $uploaded */
            public function __construct(public array &$uploaded)
            {
            }

            public function upload(string $bucket, string $objectPath, string $content): void
            {
                $this->uploaded[] = ['bucket' => $bucket, 'path' => $objectPath, 'content' => $content];
            }
        };

        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);

        $backupService = new class($repoMock, $storageMock, $firestoreMock) extends BackupService {
            /** @return array<string, mixed> */
            public function fetchDataFromPath(string $firestorePath): array
            {
                return ['item' => 'data'];
            }
        };

        $results = $backupService->backupAll(Carbon::parse('2026-11-01'));
        $this->assertSame(1, $results['total']);
        $this->assertSame(1, $results['success']);
        $this->assertSame(0, $results['failed']);
        $this->assertCount(1, $uploaded);
    }
}
