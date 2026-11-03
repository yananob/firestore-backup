<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * アプリケーション環境および各種設定値を提供する設定クラス。
 * 環境は `APP_ENV` 環境変数によって決定されます。
 *
 * サポートされる環境: 'production', 'test', 'local'。
 */
class AppConfig
{
    /**
     * 現在のアプリケーション環境を取得します。
     *
     * @return string 現在の環境 ('production', 'test', 'local' または未設定時は空文字列)。
     */
    public static function getEnvironment(): string
    {
        $env = getenv('APP_ENV');
        return is_string($env) ? $env : '';
    }

    /**
     * 本番環境かどうかを判定します。
     *
     * @return bool 本番環境の場合は true
     */
    public static function isProduction(): bool
    {
        return self::getEnvironment() === 'production';
    }

    /**
     * テスト環境かどうかを判定します。
     *
     * @return bool テスト環境の場合は true
     */
    public static function isTest(): bool
    {
        return self::getEnvironment() === 'test';
    }

    /**
     * ローカル環境かどうかを判定します。
     *
     * @return bool ローカル環境の場合は true
     */
    public static function isLocal(): bool
    {
        return self::getEnvironment() === 'local';
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            'production' => '{firestore-backup}',
            'test' => '{firestore-backup}-test',
            default => '{firestore-backup}-test',
        };
    }

    /**
     * アプリケーションのベースパスを取得します。
     *
     * @return string ベースパス。
     */
    public static function getBasePath(): string
    {
        return match (self::getEnvironment()) {
            'production' => '/{firestore-backup}',
            'test' => '/{firestore-backup}-test',
            default => '',
        };
    }
}
