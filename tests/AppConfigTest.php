<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    private string|false $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === false) {
            putenv('APP_ENV');
        } else {
            putenv("APP_ENV={$this->originalEnv}");
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
        $this->assertSame('{firestore-backup}', AppConfig::getFirestoreRootCollection());
        $this->assertSame('/{firestore-backup}', AppConfig::getBasePath());
    }

    public function testGetEnvironmentTest(): void
    {
        putenv('APP_ENV=test');
        $this->assertSame('test', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertTrue(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertSame('{firestore-backup}-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('/{firestore-backup}-test', AppConfig::getBasePath());
    }

    public function testGetEnvironmentLocal(): void
    {
        putenv('APP_ENV=local');
        $this->assertSame('local', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertFalse(AppConfig::isTest());
        $this->assertTrue(AppConfig::isLocal());
        $this->assertSame('{firestore-backup}-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('', AppConfig::getBasePath());
    }

    public function testGetEnvironmentUnset(): void
    {
        putenv('APP_ENV');
        $this->assertSame('', AppConfig::getEnvironment());
        $this->assertFalse(AppConfig::isProduction());
        $this->assertFalse(AppConfig::isTest());
        $this->assertFalse(AppConfig::isLocal());
        $this->assertSame('{firestore-backup}-test', AppConfig::getFirestoreRootCollection());
        $this->assertSame('', AppConfig::getBasePath());
    }
}
