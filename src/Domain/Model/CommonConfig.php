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
    private ?string $lineTokenKey;

    public function __construct(string $bucket, string $basePath = '', ?string $lineTargetKey = null, ?string $lineTokenKey = null)
    {
        $cleanBucket = trim($bucket);
        if ($cleanBucket === '') {
            throw new InvalidArgumentException('GCSバケット名は必須です。');
        }

        $cleanBasePath = trim(trim($basePath), '/');

        $this->bucket = $cleanBucket;
        $this->basePath = $cleanBasePath;
        $this->lineTargetKey = $lineTargetKey !== null && trim($lineTargetKey) !== '' ? trim($lineTargetKey) : null;
        $this->lineTokenKey = $lineTokenKey !== null && trim($lineTokenKey) !== '' ? trim($lineTokenKey) : null;
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

    public function getLineTokenKey(): ?string
    {
        return $this->lineTokenKey ?? $this->lineTargetKey;
    }

    /**
     * @return array{bucket: string, basePath: string, lineTargetKey: string|null, lineTokenKey: string|null}
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'basePath' => $this->basePath,
            'lineTargetKey' => $this->lineTargetKey,
            'lineTokenKey' => $this->lineTokenKey,
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
        $lineTokenKey = isset($data['lineTokenKey']) && is_string($data['lineTokenKey']) ? $data['lineTokenKey'] : null;

        return new self($bucket, $basePath, $lineTargetKey, $lineTokenKey);
    }
}
