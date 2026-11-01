<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

/**
 * GCS共通バックアップ設定エンティティ
 */
class CommonConfig
{
    private string $bucket;
    private string $basePath;

    public function __construct(string $bucket, string $basePath = '')
    {
        $cleanBucket = trim($bucket);
        if ($cleanBucket === '') {
            throw new InvalidArgumentException('GCSバケット名は必須です。');
        }

        $cleanBasePath = trim(trim($basePath), '/');

        $this->bucket = $cleanBucket;
        $this->basePath = $cleanBasePath;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return array{bucket: string, basePath: string}
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'basePath' => $this->basePath,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $bucket = isset($data['bucket']) && is_string($data['bucket']) ? $data['bucket'] : '';
        $basePath = isset($data['basePath']) && is_string($data['basePath']) ? $data['basePath'] : '';

        return new self($bucket, $basePath);
    }
}
