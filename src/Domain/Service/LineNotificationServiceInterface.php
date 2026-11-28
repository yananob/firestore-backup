<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\BackupConfig;

interface LineNotificationServiceInterface
{
    /**
     * バックアップ結果をLINEへ通知します。
     *
     * @param array{success: bool, path: string, count: int, sizeBytes: int, error?: string} $result
     */
    public function notify(BackupConfig $config, array $result): void;
}
