<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Domain\Service\BackupService;
use App\Infrastructure\Firestore\FirestoreConfigRepository;
use App\Infrastructure\Storage\GcsStorageService;
use CloudEvents\V1\CloudEventInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * CloudEvent（イベント駆動処理）を処理するハンドラークラス。
 * Pub/Sub や Cloud Scheduler 等からのイベント受信時に、設定されたバックアップを一括実行します。
 */
class EventHandler
{
    private LoggerInterface $logger;
    private BackupService $backupService;

    /**
     * @param LoggerInterface|null $logger ロガーのインスタンス（指定しない場合はMonologを標準出力向けに初期化）
     * @param BackupService|null $backupService バックアップサービス（指定しない場合はFirestore/GCS実装で初期化）
     */
    public function __construct(?LoggerInterface $logger = null, ?BackupService $backupService = null)
    {
        if ($logger === null) {
            $monolog = new Logger('cloud_event_logger');
            $monolog->pushHandler(new StreamHandler('php://stdout', Level::Info));
            $this->logger = $monolog;
        } else {
            $this->logger = $logger;
        }

        if ($backupService === null) {
            $configRepo = new FirestoreConfigRepository();
            $storage = new GcsStorageService();
            $this->backupService = new BackupService($configRepo, $storage, null, $this->logger);
        } else {
            $this->backupService = $backupService;
        }
    }

    /**
     * CloudEventメッセージを処理します。
     *
     * @param CloudEventInterface $event 受信したCloudEventメッセージ
     */
    public function handle(CloudEventInterface $event): void
    {
        $this->logger->info("Starting Firestore backup triggered by CloudEvent (ID: {$event->getId()}, Type: {$event->getType()})");

        try {
            $results = $this->backupService->backupAll();
            $this->logger->info(sprintf(
                "Backup completed: Total: %d, Success: %d, Failed: %d",
                $results['total'],
                $results['success'],
                $results['failed']
            ), [
                'details' => $results['details'],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Backup process failed with exception: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
