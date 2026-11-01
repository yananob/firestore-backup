<?php

declare(strict_types=1);

namespace App\Domain\Service;

/**
 * クラウドストレージへの保存を行うサービスインターフェース
 */
interface StorageServiceInterface
{
    /**
     * 指定されたバケットおよびオブジェクトパスに内容を保存します。
     *
     * @param string $bucket バケット名
     * @param string $objectPath オブジェクトの相対パス
     * @param string $content アップロードする文字列コンテンツ
     */
    public function upload(string $bucket, string $objectPath, string $content): void;
}
