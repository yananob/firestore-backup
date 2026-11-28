<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use App\Infrastructure\Firestore\FirestoreClientFactory;
use App\Infrastructure\Notification\LineNotificationService;
use Carbon\Carbon;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Firestoreパスのデータを抽出し、GCSへエクスポートするバックアップサービス
 */
class BackupService
{
    private ConfigRepositoryInterface $configRepository;
    private StorageServiceInterface $storageService;
    private FirestoreClient $firestoreClient;
    private LoggerInterface $logger;
    private LineNotificationServiceInterface $lineNotificationService;

    public function __construct(
        ConfigRepositoryInterface $configRepository,
        StorageServiceInterface $storageService,
        ?FirestoreClient $firestoreClient = null,
        ?LoggerInterface $logger = null,
        ?LineNotificationServiceInterface $lineNotificationService = null
    ) {
        $this->configRepository = $configRepository;
        $this->storageService = $storageService;
        $this->firestoreClient = $firestoreClient ?? FirestoreClientFactory::create();
        if ($logger === null) {
            $monolog = new Logger('backup_service_logger');
            $monolog->pushHandler(new StreamHandler('php://stdout', Level::Info));
            $this->logger = $monolog;
        } else {
            $this->logger = $logger;
        }
        $this->lineNotificationService = $lineNotificationService ?? new LineNotificationService();
    }

    /**
     * 指定された1つのバックアップ設定に従ってバックアップを実行します。
     *
     * @param BackupConfig $config バックアップ対象の設定
     * @param CommonConfig $commonConfig GCS共通設定
     * @param Carbon|null $date バックアップ基準日（未指定時は現在日時）
     * @return array{success: bool, path: string, count: int, sizeBytes: int, error?: string}
     */
    public function backupSingleConfig(
        BackupConfig $config,
        CommonConfig $commonConfig,
        ?Carbon $date = null
    ): array {
        $backupDate = $date ?? Carbon::now();
        $dateStr = $backupDate->format('Ymd');
        $bucket = $commonConfig->getBucket();
        $basePath = $commonConfig->getBasePath();

        // ファイル名に安全な名前（連続する記号を単一アンダースコアに整形）
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $config->getName()) ?: 'backup';
        $fileName = sprintf('%s_%s.json', trim($safeName, '_'), $config->getId());

        $targetGcsPath = $basePath !== ''
            ? sprintf('%s/%s/%s', $basePath, $dateStr, $fileName)
            : sprintf('%s/%s', $dateStr, $fileName);

        $this->logger->info("Starting backup for '{$config->getName()}' (path: {$config->getFirestorePath()}) to gs://{$bucket}/{$targetGcsPath}");

        try {
            $data = $this->fetchDataFromPath($config->getFirestorePath());

            $jsonContent = json_encode([
                'configId' => $config->getId(),
                'configName' => $config->getName(),
                'firestorePath' => $config->getFirestorePath(),
                'exportedAt' => $backupDate->toIso8601String(),
                'itemCount' => count($data),
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

            $this->storageService->upload($bucket, $targetGcsPath, $jsonContent);
            $sizeBytes = strlen($jsonContent);

            $this->logger->info("Successfully backed up '{$config->getName()}' to gs://{$bucket}/{$targetGcsPath} (" . count($data) . " items)");

            return [
                'success' => true,
                'path' => sprintf('gs://%s/%s', $bucket, $targetGcsPath),
                'count' => count($data),
                'sizeBytes' => $sizeBytes,
            ];
        } catch (Throwable $e) {
            $this->logger->error("Failed to backup '{$config->getName()}': " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'path' => sprintf('gs://%s/%s', $bucket, $targetGcsPath),
                'count' => 0,
                'sizeBytes' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 有効なすべてのバックアップ設定を実行します。
     *
     * @param Carbon|null $date 基準日時
     * @return array{total: int, success: int, failed: int, details: array<string, array{success: bool, path: string, count: int, sizeBytes: int, error?: string}>}
     */
    public function backupAll(?Carbon $date = null): array
    {
        $commonConfig = $this->configRepository->getCommonConfig();
        if ($commonConfig === null || $commonConfig->getBucket() === '') {
            throw new RuntimeException('GCS共通設定が設定されていません。バケット名を設定してください。');
        }

        $configs = $this->configRepository->getBackupConfigs();
        $results = [];
        $notificationResults = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($configs as $config) {
            if (!$config->isEnabled()) {
                $this->logger->info("Skipping disabled config '{$config->getName()}' ({$config->getId()})");
                $notificationResults[] = [
                    'configName' => $config->getName(),
                    'success' => false,
                    'path' => '',
                    'count' => 0,
                    'sizeBytes' => 0,
                    'skipped' => true,
                ];
                continue;
            }

            $res = $this->backupSingleConfig($config, $commonConfig, $date);
            $results[$config->getId()] = $res;
            $notificationResults[] = [
                'configName' => $config->getName(),
                ...$res,
            ];

            if ($res['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        $this->notifyResults($commonConfig, $notificationResults);

        return [
            'total' => count($results),
            'success' => $successCount,
            'failed' => $failedCount,
            'details' => $results,
        ];
    }

    /**
     * バックアップ結果を共通設定のLINE送信先へ一括通知します。
     *
     * @param array<int, array{configName: string, success: bool, path: string, count: int, sizeBytes: int, skipped?: bool, error?: string}> $results
     */
    public function notifyResults(CommonConfig $config, array $results): void
    {
        if ($config->getLineTargetKey() === null) {
            return;
        }

        try {
            $this->lineNotificationService->notify($config, $results);
        } catch (Throwable $e) {
            $this->logger->error('Failed to send LINE backup report: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * 指定されたパスからドキュメントデータを取得します。
     * コレクションパスの場合は配下の全ドキュメント（サブコレクション含む、IDをキーとする連想配列）、
     * ドキュメントパスの場合は単一ドキュメント（サブコレクション含む）を配列で返します。
     *
     * @return array<string, mixed>
     */
    public function fetchDataFromPath(string $firestorePath): array
    {
        $cleanPath = trim(trim($firestorePath), '/');
        if ($cleanPath === '') {
            return [];
        }

        $segments = explode('/', $cleanPath);
        $count = count($segments);

        // 奇数個 = コレクション (例: "users", "groups/g1/members")
        if ($count % 2 === 1) {
            $this->logger->info("Fetching collection data for path: {$cleanPath}");
            $colRef = $this->firestoreClient->collection($cleanPath);
            return $this->fetchCollectionData($colRef);
        }

        // 偶数個 = 単一ドキュメント (例: "users/u1")
        $this->logger->info("Fetching document data for path: {$cleanPath}");
        $docRef = $this->firestoreClient->document($cleanPath);
        $snapshot = $docRef->snapshot();
        return [
                $snapshot->id() => $this->fetchDocumentData($docRef, $snapshot),
            ];
    }

    /**
     * コレクション配下の全ドキュメント（サブコレクション含む）を再帰的に取得します。
     *
     * @return array<string, mixed>
     */
    public function fetchCollectionData(CollectionReference $colRef): array
    {
        $this->logger->info("Fetching collection data for reference: " . $colRef->id());
        $documents = $colRef->documents();
        $this->logger->info("Found " . iterator_count($documents) . " documents in collection: " . $colRef->id());
        $data = [];
        foreach ($documents as $doc) {
            $data[$doc->id()] = $this->fetchDocumentData($doc->reference(), $doc);
        }
        return $data;
    }

    /**
     * ドキュメントデータおよび配下のサブコレクションを再帰的に取得します。
     *
     * @return array<string, mixed>
     */
    public function fetchDocumentData(DocumentReference $docRef, ?DocumentSnapshot $snapshot = null): array
    {
        $docSnapshot = $snapshot ?? $docRef->snapshot();
        /** @var array<string, mixed> $docData */
        $docData = $docSnapshot->exists() ? (array) $docSnapshot->data() : [];

        $collections = [];
        foreach ($docRef->collections() as $subCol) {
            $subColData = $this->fetchCollectionData($subCol);
            if (!empty($subColData)) {
                $collections[$subCol->id()] = $subColData;
            }
        }

        if (!empty($collections)) {
            $docData['_collections'] = $collections;
        }

        return $docData;
    }
}
