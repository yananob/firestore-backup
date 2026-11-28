<?php

declare(strict_types=1);

namespace Tests\Domain\Model;

use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BackupConfigTest extends TestCase
{
    public function testCommonConfigValid(): void
    {
        $config = new CommonConfig('my-bucket', '/backups/path/', 'group', 'bot');
        $this->assertSame('my-bucket', $config->getBucket());
        $this->assertSame('backups/path', $config->getBasePath());
        $this->assertSame('group', $config->getLineTargetKey());
        $this->assertSame('bot', $config->getLineTokenKey());

        $array = $config->toArray();
        $this->assertSame('my-bucket', $array['bucket']);
        $this->assertSame('backups/path', $array['basePath']);
        $this->assertSame('group', $array['lineTargetKey']);
        $this->assertSame('bot', $array['lineTokenKey']);

        $restored = CommonConfig::fromArray($array);
        $this->assertSame('my-bucket', $restored->getBucket());
        $this->assertSame('backups/path', $restored->getBasePath());
        $this->assertSame('group', $restored->getLineTargetKey());
        $this->assertSame('bot', $restored->getLineTokenKey());
        $this->assertSame('legacy', CommonConfig::fromArray(['bucket' => 'bucket', 'lineTargetKey' => 'legacy'])->getLineTokenKey());
    }

    public function testCommonConfigThrowsOnEmptyBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CommonConfig('   ');
    }

    public function testBackupConfigValid(): void
    {
        $now = Carbon::parse('2026-11-01 12:00:00');
        $config = new BackupConfig('cfg-1', 'ユーザー情報', '/users/collection/', true, $now, $now);

        $this->assertSame('cfg-1', $config->getId());
        $this->assertSame('ユーザー情報', $config->getName());
        $this->assertSame('users/collection', $config->getFirestorePath());
        $this->assertTrue($config->isEnabled());
        $this->assertSame($now, $config->getCreatedAt());
        $this->assertSame($now, $config->getUpdatedAt());

        $array = $config->toArray();
        $this->assertSame('cfg-1', $array['id']);
        $this->assertSame('ユーザー情報', $array['name']);
        $this->assertSame('users/collection', $array['firestorePath']);

        $restored = BackupConfig::fromArray($array);
        $this->assertSame('cfg-1', $restored->getId());
        $this->assertSame('ユーザー情報', $restored->getName());
        $this->assertSame('users/collection', $restored->getFirestorePath());
        $this->assertTrue($restored->isEnabled());
    }

    public function testBackupConfigValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BackupConfig('', 'name', 'path/doc');
    }

    public function testBackupConfigAllowsExistingRootCollection(): void
    {
        // DBに既存のルートパス設定が存在しても例外が発生せず、isRootCollection が true を返すこと
        $config = new BackupConfig('cfg-root', 'ルート指定', 'users');
        $this->assertSame('users', $config->getFirestorePath());
        $this->assertTrue($config->isRootCollection());

        // サブコレクションまたはドキュメントの場合は false
        $subConfig = new BackupConfig('cfg-sub', 'サブ指定', 'users/u1');
        $this->assertFalse($subConfig->isRootCollection());
    }
}
