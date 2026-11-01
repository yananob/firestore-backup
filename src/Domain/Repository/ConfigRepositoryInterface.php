<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;

/**
 * バックアップ設定リポジトリインターフェース
 */
interface ConfigRepositoryInterface
{
    /**
     * 共通GCS設定を取得します。
     */
    public function getCommonConfig(): ?CommonConfig;

    /**
     * 共通GCS設定を保存します。
     */
    public function saveCommonConfig(CommonConfig $config): void;

    /**
     * すべてのバックアップ設定を取得します。
     *
     * @return array<string, BackupConfig> 設定IDをキーとする配列
     */
    public function getBackupConfigs(): array;

    /**
     * IDで指定されたバックアップ設定を取得します。
     */
    public function getBackupConfig(string $id): ?BackupConfig;

    /**
     * バックアップ設定を保存（新規作成または更新）します。
     */
    public function saveBackupConfig(BackupConfig $config): void;

    /**
     * 指定されたIDのバックアップ設定を削除します。
     */
    public function deleteBackupConfig(string $id): void;
}
