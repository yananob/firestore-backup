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
    private ?string $lineTargetKey;

    public function __construct(string $bucket, string $basePath = '', ?string $lineTargetKey = null)
    {
        $cleanBucket = trim($bucket);
        if ($cleanBucket === '') {
            throw new InvalidArgumentException('GCSバケット名は必須です。');
        }

        $cleanBasePath = trim(trim($basePath), '/');

        $this->bucket = $cleanBucket;
        $this->basePath = $cleanBasePath;
        $this->lineTargetKey = $lineTargetKey !== null && trim($lineTargetKey) !== '' ? trim($lineTargetKey) : null;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getLineTargetKey(): ?string
    {
        return $this->lineTargetKey;
    }

    /**
     * @return array{bucket: string, basePath: string, lineTargetKey: string|null}
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'basePath' => $this->basePath,
            'lineTargetKey' => $this->lineTargetKey,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $bucket = isset($data['bucket']) && is_string($data['bucket']) ? $data['bucket'] : '';
        $basePath = isset($data['basePath']) && is_string($data['basePath']) ? $data['basePath'] : '';
        $lineTargetKey = isset($data['lineTargetKey']) && is_string($data['lineTargetKey']) ? $data['lineTargetKey'] : null;

        return new self($bucket, $basePath, $lineTargetKey);
    }
}
