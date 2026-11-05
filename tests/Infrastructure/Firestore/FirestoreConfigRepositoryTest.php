<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Firestore;

use App\Domain\Model\BackupConfig;
use App\Infrastructure\Firestore\FirestoreConfigRepository;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class FirestoreConfigRepositoryTest extends TestCase
{
    public function testGetBackupConfigsReturnsSortedByNameAsc(): void
    {
        $mockClient = $this->createMock(FirestoreClient::class);
        $mockRootCol = $this->createMock(CollectionReference::class);
        $mockSettingsDoc = $this->createMock(DocumentReference::class);
        $mockBackupConfigsCol = $this->createMock(CollectionReference::class);

        $mockClient->method('collection')->willReturn($mockRootCol);
        $mockRootCol->method('document')->willReturn($mockSettingsDoc);
        $mockSettingsDoc->method('collection')->willReturn($mockBackupConfigsCol);

        $docC = $this->createMock(DocumentSnapshot::class);
        $docC->method('exists')->willReturn(true);
        $docC->method('id')->willReturn('id-c');
        $docC->method('data')->willReturn([
            'id' => 'id-c',
            'name' => 'C設定',
            'firestorePath' => 'users/c',
        ]);

        $docA = $this->createMock(DocumentSnapshot::class);
        $docA->method('exists')->willReturn(true);
        $docA->method('id')->willReturn('id-a');
        $docA->method('data')->willReturn([
            'id' => 'id-a',
            'name' => 'A設定',
            'firestorePath' => 'users/a',
        ]);

        $docB = $this->createMock(DocumentSnapshot::class);
        $docB->method('exists')->willReturn(true);
        $docB->method('id')->willReturn('id-b');
        $docB->method('data')->willReturn([
            'id' => 'id-b',
            'name' => 'B設定',
            'firestorePath' => 'users/b',
        ]);

        $mockBackupConfigsCol->method('documents')->willReturn([$docC, $docA, $docB]);

        $repo = new FirestoreConfigRepository($mockClient, 'root_col');
        $configs = $repo->getBackupConfigs();

        $names = array_map(fn (BackupConfig $cfg) => $cfg->getName(), array_values($configs));
        $this->assertSame(['A設定', 'B設定', 'C設定'], $names);
    }
}
