<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * 個別バックアップ設定エンティティ
 */
class BackupConfig
{
    private string $id;
    private string $name;
    private string $firestorePath;
    private bool $enabled;
    private ?string $lineTargetKey;
    private Carbon $createdAt;
    private Carbon $updatedAt;

    public function __construct(
        string $id,
        string $name,
        string $firestorePath,
        bool $enabled = true,
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        ?string $lineTargetKey = null
    ) {
        $cleanId = trim($id);
        $cleanName = trim($name);
        $cleanPath = trim(trim($firestorePath), '/');

        if ($cleanId === '') {
            throw new InvalidArgumentException('IDは必須です。');
        }
        if ($cleanName === '') {
            throw new InvalidArgumentException('設定名は必須です。');
        }
        if ($cleanPath === '') {
            throw new InvalidArgumentException('Firestoreパスは必須です。');
        }

        $this->id = $cleanId;
        $this->name = $cleanName;
        $this->firestorePath = $cleanPath;
        $this->enabled = $enabled;
        $this->lineTargetKey = $lineTargetKey !== null && trim($lineTargetKey) !== '' ? trim($lineTargetKey) : null;
        $this->createdAt = $createdAt ?? Carbon::now();
        $this->updatedAt = $updatedAt ?? Carbon::now();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFirestorePath(): string
    {
        return $this->firestorePath;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLineTargetKey(): ?string
    {
        return $this->lineTargetKey;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    /**
     * 指定されたパスがルートコレクションであるか（スラッシュを含まないか）を判定します。
     */
    public function isRootCollection(): bool
    {
        return !str_contains($this->firestorePath, '/');
    }

    /**
     * @return array{id: string, name: string, firestorePath: string, enabled: bool, lineTargetKey: string|null, createdAt: string, updatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'firestorePath' => $this->firestorePath,
            'enabled' => $this->enabled,
            'lineTargetKey' => $this->lineTargetKey,
            'createdAt' => $this->createdAt->toIso8601String(),
            'updatedAt' => $this->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $fallbackId = null): self
    {
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : ($fallbackId ?? '');
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $firestorePath = isset($data['firestorePath']) && is_string($data['firestorePath']) ? $data['firestorePath'] : '';
        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : true;
        $lineTargetKey = isset($data['lineTargetKey']) && is_string($data['lineTargetKey']) ? $data['lineTargetKey'] : null;

        $createdAt = null;
        if (isset($data['createdAt']) && is_string($data['createdAt'])) {
            $createdAt = Carbon::parse($data['createdAt']);
        }
        $updatedAt = null;
        if (isset($data['updatedAt']) && is_string($data['updatedAt'])) {
            $updatedAt = Carbon::parse($data['updatedAt']);
        }

        return new self($id, $name, $firestorePath, $enabled, $createdAt, $updatedAt, $lineTargetKey);
    }
}
