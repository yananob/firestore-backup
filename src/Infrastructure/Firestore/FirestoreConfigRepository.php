<?php

declare(strict_types=1);

namespace App\Infrastructure\Firestore;

use App\AppConfig;
use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use Google\Cloud\Firestore\FirestoreClient;

/**
 * Firestore をデータストアとする設定リポジトリ実装
 */
class FirestoreConfigRepository implements ConfigRepositoryInterface
{
    private FirestoreClient $firestoreClient;
    private string $rootCollection;

    public function __construct(?FirestoreClient $firestoreClient = null, ?string $rootCollection = null)
    {
        $this->firestoreClient = $firestoreClient ?? new FirestoreClient([
            'transport' => 'rest',
        ]);
        $this->rootCollection = $rootCollection ?? AppConfig::getFirestoreRootCollection();
    }

    public function getCommonConfig(): ?CommonConfig
    {
        $doc = $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('common')
            ->document('gcs')
            ->snapshot();

        if (!$doc->exists()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $doc->data();
        return CommonConfig::fromArray($data);
    }

    public function saveCommonConfig(CommonConfig $config): void
    {
        $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('common')
            ->document('gcs')
            ->set($config->toArray());
    }

    public function getBackupConfigs(): array
    {
        $query = $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('backup_configs')
            ->documents();

        $configs = [];
        foreach ($query as $document) {
            if ($document->exists()) {
                /** @var array<string, mixed> $data */
                $data = $document->data();
                $config = BackupConfig::fromArray($data, $document->id());
                $configs[$config->getId()] = $config;
            }
        }

        return $configs;
    }

    public function getBackupConfig(string $id): ?BackupConfig
    {
        $doc = $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('backup_configs')
            ->document($id)
            ->snapshot();

        if (!$doc->exists()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $doc->data();
        return BackupConfig::fromArray($data, $doc->id());
    }

    public function saveBackupConfig(BackupConfig $config): void
    {
        $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('backup_configs')
            ->document($config->getId())
            ->set($config->toArray());
    }

    public function deleteBackupConfig(string $id): void
    {
        $this->firestoreClient
            ->collection($this->rootCollection)
            ->document('settings')
            ->collection('backup_configs')
            ->document($id)
            ->delete();
    }
}
