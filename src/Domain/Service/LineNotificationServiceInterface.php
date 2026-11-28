<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\CommonConfig;

interface LineNotificationServiceInterface
{
    /**
     * バックアップ結果をLINEへ通知します。
     *
     * @param array<int, array{configName: string, success: bool, path: string, count: int, sizeBytes: int, skipped?: bool, error?: string}> $results
     */
    public function notify(CommonConfig $config, array $results): void;
}
