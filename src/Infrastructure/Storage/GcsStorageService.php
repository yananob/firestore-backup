<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Service\StorageServiceInterface;
use Google\Cloud\Storage\StorageClient;

/**
 * Google Cloud Storage を利用したストレージサービス実装
 */
class GcsStorageService implements StorageServiceInterface
{
    private StorageClient $storageClient;

    public function __construct(?StorageClient $storageClient = null)
    {
        $this->storageClient = $storageClient ?? new StorageClient();
    }

    public function upload(string $bucket, string $objectPath, string $content): void
    {
        $bucketInstance = $this->storageClient->bucket($bucket);
        $cleanPath = ltrim($objectPath, '/');
        $bucketInstance->upload($content, [
            'name' => $cleanPath,
        ]);
    }
}
