<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    private string|false $originalEnv;
    private string|false $originalLineConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv('APP_ENV');
        $this->originalLineConfig = getenv('LINE_TOKENS_N_TARGETS');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv('APP_ENV');
        } else {
            putenv("APP_ENV={$this->originalEnv}");
        }
        if ($this->originalLineConfig === false) {
            putenv('LINE_TOKENS_N_TARGETS');
        } else {
            putenv("LINE_TOKENS_N_TARGETS={$this->originalLineConfig}");
        }

        parent::tearDown();
    }

    public function testGetEnvironmentProduction(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('production', AppConfig::getEnvironment());
        $this->assertTrue(AppConfig::isProduction());
        $this->assertFalse(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertSame('firestore-backup', AppConfig::getFirestoreRootCollection());
        $this->assertSame('/firestore-backup', AppConfig::getBasePath());
    }

    public function testGetEnvironmentTest(): void
    {
        putenv('APP_ENV=test');
        $this->assertSame('test', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertTrue(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertSame('firestore-backup-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('/firestore-backup-test', AppConfig::getBasePath());
    }

    public function testGetEnvironmentLocal(): void
    {
        putenv('APP_ENV=local');
        $this->assertSame('local', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertFalse(AppConfig::isTest());
        $this->assertTrue(AppConfig::isLocal());
        $this->assertSame('firestore-backup-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('', AppConfig::getBasePath());
    }

    public function testGetEnvironmentUnset(): void
    {
        putenv('APP_ENV');
        $this->assertSame('', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertFalse(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertSame('firestore-backup-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('', AppConfig::getBasePath());
    }

    public function testGetLineTargetOptionsReturnsOnlyCompleteMappings(): void
    {
        putenv('LINE_TOKENS_N_TARGETS=' . json_encode([
            'tokens' => ['oml' => 'token', 'incomplete' => 'token'],
            'target_ids' => ['oml' => 'target'],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(['oml' => 'oml'], AppConfig::getLineTargetOptions());
    }
}
