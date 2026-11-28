<?php

declare(strict_types=1);

namespace Tests\Domain\Service;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use App\Domain\Service\BackupService;
use App\Domain\Service\LineNotificationServiceInterface;
use App\Domain\Service\StorageServiceInterface;
use ArrayIterator;
use Carbon\Carbon;
use Google\Cloud\Core\Iterator\ItemIterator;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class BackupServiceTest extends TestCase
{
    /**
     * @param array<int, mixed> $items
     */
    private function createItemIterator(array $items): ItemIterator
    {
        return new ItemIterator(new ArrayIterator([$items]));
    }

    public function testBackupSingleConfigUploadsToGcs(): void
    {
        $backupConfig = new BackupConfig('c1', 'users-data', 'users/u1');
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
        $this->assertSame('gs://my-test-bucket/backups/20261101/users-data_c1.json', $result['path']);

        $this->assertCount(1, $uploaded);
        $this->assertSame('my-test-bucket', $uploaded[0]['bucket']);
        $this->assertSame('backups/20261101/users-data_c1.json', $uploaded[0]['path']);

        $decoded = json_decode($uploaded[0]['content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('c1', $decoded['configId']);
        $this->assertSame('users-data', $decoded['configName']);
        $this->assertSame('users/u1', $decoded['firestorePath']);
        $this->assertSame(2, $decoded['itemCount']);
        $this->assertArrayHasKey('u1', $decoded['data']);
    }

    public function testBackupAllExecutesEnabledConfigs(): void
    {
        $c1 = new BackupConfig('c1', 'config1', 'users/u1', true);
        $c2 = new BackupConfig('c2', 'config2', 'logs/l1', false); // disabled

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

    public function testBackupAllReportsCountAndSizeToSelectedLineDestination(): void
    {
        $config = new BackupConfig('c1', 'config1', 'users/u1', true, null, null, 'oml');
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        $repoMock->method('getCommonConfig')->willReturn(new CommonConfig('test-bucket'));
        $repoMock->method('getBackupConfigs')->willReturn(['c1' => $config]);
        /** @var StorageServiceInterface&\PHPUnit\Framework\MockObject\MockObject $storageMock */
        $storageMock = $this->createMock(StorageServiceInterface::class);
        $notified = [];
        $lineMock = new class($notified) implements LineNotificationServiceInterface {
            /** @param array<int, array{config: BackupConfig, result: array<string, mixed>}> $notified */
            public function __construct(public array &$notified)
            {
            }

            /** @param array{success: bool, path: string, count: int, sizeBytes: int, error?: string} $result */
            public function notify(BackupConfig $config, array $result): void
            {
                $this->notified[] = ['config' => $config, 'result' => $result];
            }
        };
        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);
        $backupService = new class($repoMock, $storageMock, $firestoreMock, null, $lineMock) extends BackupService {
            /** @return array<string, mixed> */
            public function fetchDataFromPath(string $firestorePath): array
            {
                return ['item' => 'data'];
            }
        };

        $backupService->backupAll(Carbon::parse('2026-11-01'));

        $this->assertCount(1, $notified);
        $this->assertSame('oml', $notified[0]['config']->getLineTargetKey());
        $this->assertSame(1, $notified[0]['result']['count']);
        $this->assertGreaterThan(0, $notified[0]['result']['sizeBytes']);
    }

    public function testFetchDataFromPathWithCollectionAndNestedSubcollections(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        /** @var StorageServiceInterface&\PHPUnit\Framework\MockObject\MockObject $storageMock */
        $storageMock = $this->createMock(StorageServiceInterface::class);
        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);

        /** @var CollectionReference&\PHPUnit\Framework\MockObject\MockObject $colRef */
        $colRef = $this->createMock(CollectionReference::class);
        $firestoreMock->expects($this->once())
            ->method('collection')
            ->with('users')
            ->willReturn($colRef);

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $userDocSnapshot */
        $userDocSnapshot = $this->createMock(DocumentSnapshot::class);
        $userDocSnapshot->method('exists')->willReturn(true);
        $userDocSnapshot->method('id')->willReturn('u1');
        $userDocSnapshot->method('data')->willReturn(['name' => 'Alice']);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $userDocRef */
        $userDocRef = $this->createMock(DocumentReference::class);
        $userDocSnapshot->method('reference')->willReturn($userDocRef);

        $colRef->method('documents')->willReturn([$userDocSnapshot]);

        /** @var CollectionReference&\PHPUnit\Framework\MockObject\MockObject $subColRef */
        $subColRef = $this->createMock(CollectionReference::class);
        $subColRef->method('id')->willReturn('orders');
        $userDocRef->method('collections')->willReturn($this->createItemIterator([$subColRef]));

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $orderDocSnapshot */
        $orderDocSnapshot = $this->createMock(DocumentSnapshot::class);
        $orderDocSnapshot->method('exists')->willReturn(true);
        $orderDocSnapshot->method('id')->willReturn('order1');
        $orderDocSnapshot->method('data')->willReturn(['total' => 1000]);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $orderDocRef */
        $orderDocRef = $this->createMock(DocumentReference::class);
        $orderDocRef->method('collections')->willReturn($this->createItemIterator([]));
        $orderDocSnapshot->method('reference')->willReturn($orderDocRef);

        $subColRef->method('documents')->willReturn([$orderDocSnapshot]);

        $backupService = new BackupService($repoMock, $storageMock, $firestoreMock);
        $data = $backupService->fetchDataFromPath('users');

        $this->assertArrayHasKey('u1', $data);
        $this->assertSame('Alice', $data['u1']['name']);
        $this->assertArrayHasKey('_collections', $data['u1']);
        $this->assertArrayHasKey('orders', $data['u1']['_collections']);
        $this->assertArrayHasKey('order1', $data['u1']['_collections']['orders']);
        $this->assertSame(1000, $data['u1']['_collections']['orders']['order1']['total']);
    }

    public function testFetchDataFromPathWithDocumentAndNestedSubcollections(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        /** @var StorageServiceInterface&\PHPUnit\Framework\MockObject\MockObject $storageMock */
        $storageMock = $this->createMock(StorageServiceInterface::class);
        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $docRef */
        $docRef = $this->createMock(DocumentReference::class);
        $firestoreMock->expects($this->once())
            ->method('document')
            ->with('groups/g1')
            ->willReturn($docRef);

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $docSnapshot */
        $docSnapshot = $this->createMock(DocumentSnapshot::class);
        $docSnapshot->method('exists')->willReturn(true);
        $docSnapshot->method('id')->willReturn('g1');
        $docSnapshot->method('data')->willReturn(['title' => 'Developers']);
        $docRef->method('snapshot')->willReturn($docSnapshot);

        /** @var CollectionReference&\PHPUnit\Framework\MockObject\MockObject $membersColRef */
        $membersColRef = $this->createMock(CollectionReference::class);
        $membersColRef->method('id')->willReturn('members');
        $docRef->method('collections')->willReturn($this->createItemIterator([$membersColRef]));

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $memberDocSnapshot */
        $memberDocSnapshot = $this->createMock(DocumentSnapshot::class);
        $memberDocSnapshot->method('exists')->willReturn(true);
        $memberDocSnapshot->method('id')->willReturn('m1');
        $memberDocSnapshot->method('data')->willReturn(['role' => 'lead']);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $memberDocRef */
        $memberDocRef = $this->createMock(DocumentReference::class);
        $memberDocRef->method('collections')->willReturn($this->createItemIterator([]));
        $memberDocSnapshot->method('reference')->willReturn($memberDocRef);

        $membersColRef->method('documents')->willReturn([$memberDocSnapshot]);

        $backupService = new BackupService($repoMock, $storageMock, $firestoreMock);
        $data = $backupService->fetchDataFromPath('groups/g1');

        $this->assertArrayHasKey('g1', $data);
        $this->assertSame('Developers', $data['g1']['title']);
        $this->assertArrayHasKey('_collections', $data['g1']);
        $this->assertArrayHasKey('members', $data['g1']['_collections']);
        $this->assertSame('lead', $data['g1']['_collections']['members']['m1']['role']);
    }

    public function testFetchDataFromPathWithMultiLevelNesting(): void
    {
        /** @var ConfigRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repoMock */
        $repoMock = $this->createMock(ConfigRepositoryInterface::class);
        /** @var StorageServiceInterface&\PHPUnit\Framework\MockObject\MockObject $storageMock */
        $storageMock = $this->createMock(StorageServiceInterface::class);
        /** @var FirestoreClient&\PHPUnit\Framework\MockObject\MockObject $firestoreMock */
        $firestoreMock = $this->createMock(FirestoreClient::class);

        // Document: company/c1 -> subcol: departments -> doc: d1 -> subcol: employees -> doc: e1
        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $docRef */
        $docRef = $this->createMock(DocumentReference::class);
        $firestoreMock->expects($this->once())
            ->method('document')
            ->with('company/c1')
            ->willReturn($docRef);

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $companySnapshot */
        $companySnapshot = $this->createMock(DocumentSnapshot::class);
        $companySnapshot->method('exists')->willReturn(true);
        $companySnapshot->method('id')->willReturn('c1');
        $companySnapshot->method('data')->willReturn(['name' => 'Acme Corp']);
        $docRef->method('snapshot')->willReturn($companySnapshot);

        /** @var CollectionReference&\PHPUnit\Framework\MockObject\MockObject $deptColRef */
        $deptColRef = $this->createMock(CollectionReference::class);
        $deptColRef->method('id')->willReturn('departments');
        $docRef->method('collections')->willReturn($this->createItemIterator([$deptColRef]));

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $deptSnapshot */
        $deptSnapshot = $this->createMock(DocumentSnapshot::class);
        $deptSnapshot->method('exists')->willReturn(true);
        $deptSnapshot->method('id')->willReturn('d1');
        $deptSnapshot->method('data')->willReturn(['name' => 'Engineering']);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $deptDocRef */
        $deptDocRef = $this->createMock(DocumentReference::class);
        $deptSnapshot->method('reference')->willReturn($deptDocRef);
        $deptColRef->method('documents')->willReturn([$deptSnapshot]);

        /** @var CollectionReference&\PHPUnit\Framework\MockObject\MockObject $empColRef */
        $empColRef = $this->createMock(CollectionReference::class);
        $empColRef->method('id')->willReturn('employees');
        $deptDocRef->method('collections')->willReturn($this->createItemIterator([$empColRef]));

        /** @var DocumentSnapshot&\PHPUnit\Framework\MockObject\MockObject $empSnapshot */
        $empSnapshot = $this->createMock(DocumentSnapshot::class);
        $empSnapshot->method('exists')->willReturn(true);
        $empSnapshot->method('id')->willReturn('e1');
        $empSnapshot->method('data')->willReturn(['name' => 'Charlie']);

        /** @var DocumentReference&\PHPUnit\Framework\MockObject\MockObject $empDocRef */
        $empDocRef = $this->createMock(DocumentReference::class);
        $empDocRef->method('collections')->willReturn($this->createItemIterator([]));
        $empSnapshot->method('reference')->willReturn($empDocRef);
        $empColRef->method('documents')->willReturn([$empSnapshot]);

        $backupService = new BackupService($repoMock, $storageMock, $firestoreMock);
        $data = $backupService->fetchDataFromPath('company/c1');

        $this->assertArrayHasKey('c1', $data);
        $this->assertSame('Acme Corp', $data['c1']['name']);
        $this->assertArrayHasKey('_collections', $data['c1']);
        $this->assertArrayHasKey('departments', $data['c1']['_collections']);
        $this->assertArrayHasKey('d1', $data['c1']['_collections']['departments']);
        $this->assertSame('Engineering', $data['c1']['_collections']['departments']['d1']['name']);

        $nestedEmployees = $data['c1']['_collections']['departments']['d1']['_collections']['employees'];
        $this->assertArrayHasKey('e1', $nestedEmployees);
        $this->assertSame('Charlie', $nestedEmployees['e1']['name']);
        $this->assertArrayNotHasKey('_collections', $nestedEmployees['e1']);
    }
}
