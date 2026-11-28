<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\AppConfig;
use App\Domain\Model\BackupConfig;
use App\Domain\Service\LineNotificationServiceInterface;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Configuration;

class LineNotificationService implements LineNotificationServiceInterface
{
    /**
     * @param array{success: bool, path: string, count: int, sizeBytes: int, error?: string} $result
     */
    public function notify(BackupConfig $config, array $result): void
    {
        $key = $config->getLineTargetKey();
        if ($key === null) {
            return;
        }

        $token = AppConfig::getLineConfig()['tokens'][$key] ?? null;
        $targetId = AppConfig::getLineConfig()['target_ids'][$key] ?? null;
        if ($token === null || $token === '' || $targetId === null || $targetId === '') {
            return;
        }

        $message = $result['success']
            ? sprintf(
                "Firestoreバックアップ完了\n設定: %s\nデータ数: %d件\n容量: %s\n保存先: %s",
                $config->getName(),
                $result['count'],
                self::formatBytes($result['sizeBytes']),
                $result['path']
            )
            : sprintf(
                "Firestoreバックアップ失敗\n設定: %s\nエラー: %s",
                $config->getName(),
                $result['error'] ?? '不明なエラー'
            );

        $lineConfig = new Configuration();
        $lineConfig->setAccessToken($token);
        $api = new MessagingApiApi(null, $lineConfig);
        $api->pushMessage(new PushMessageRequest([
            'to' => $targetId,
            'messages' => [new TextMessage(['text' => $message])],
        ]));
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return sprintf('%.1f MB', $bytes / (1024 * 1024));
    }
}
