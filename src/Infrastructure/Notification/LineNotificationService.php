<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\AppConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Service\LineNotificationServiceInterface;
use GuzzleHttp\ClientInterface;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Configuration;
use RuntimeException;

class LineNotificationService implements LineNotificationServiceInterface
{
    public function __construct(private ?ClientInterface $client = null)
    {
    }

    /**
     * @param array<int, array{configName: string, success: bool, path: string, count: int, sizeBytes: int, skipped?: bool, error?: string}> $results
     */
    public function notify(CommonConfig $config, array $results): void
    {
        $key = $config->getLineTargetKey();
        if ($key === null) {
            return;
        }

        $lineConfigData = AppConfig::getLineConfig();
        $tokenKey = $config->getLineTokenKey();
        $token = $tokenKey !== null ? ($lineConfigData['tokens'][$tokenKey] ?? null) : null;
        $targetId = $lineConfigData['target_ids'][$key] ?? null;
        if ($token === null || $token === '' || $targetId === null || $targetId === '') {
            throw new RuntimeException('LINEのトークンまたは通知先が設定されていません。');
        }

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $itemCount = 0;
        $sizeBytes = 0;
        $lines = [];

        foreach ($results as $result) {
            if ($result['skipped'] ?? false) {
                $skippedCount++;
                $lines[] = sprintf('- %s: スキップ', $result['configName']);
                continue;
            }

            if ($result['success']) {
                $successCount++;
                $itemCount += $result['count'];
                $sizeBytes += $result['sizeBytes'];
                $lines[] = sprintf(
                    '- %s: 成功、%d件、%s',
                    $result['configName'],
                    $result['count'],
                    self::formatBytes($result['sizeBytes'])
                );
            } else {
                $failedCount++;
                $lines[] = sprintf(
                    '- %s: 失敗（%s）',
                    $result['configName'],
                    $result['error'] ?? '不明なエラー'
                );
            }
        }

        $message = sprintf(
            "Firestoreバックアップレポート\n成功: %d / 失敗: %d / スキップ: %d\n合計データ数: %d件\n合計容量: %s\n\n%s",
            $successCount,
            $failedCount,
            $skippedCount,
            $itemCount,
            self::formatBytes($sizeBytes),
            $lines === [] ? 'バックアップ対象はありません。' : implode("\n", $lines)
        );

        $lineConfig = new Configuration();
        $lineConfig->setAccessToken($token);
        $api = new MessagingApiApi($this->client, $lineConfig);
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
