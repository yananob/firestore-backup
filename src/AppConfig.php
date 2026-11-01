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
    public const ENV_PRODUCTION = 'production';
    public const ENV_TEST = 'test';
    public const ENV_LOCAL = 'local';

    /**
     * サポートされている環境の一覧。
     *
     * @var list<string>
     */
    public const VALID_ENVIRONMENTS = [
        self::ENV_PRODUCTION,
        self::ENV_TEST,
        self::ENV_LOCAL,
    ];

    /**
     * 現在のアプリケーション環境を取得します。
     *
     * @return string 現在の環境 ('production', 'test', 'local' または未設定時は空文字列)。
     */
    public static function getEnvironment(): string
    {
        $env = getenv('APP_ENV');
        return is_string($env) ? trim($env) : '';
    }

    /**
     * 指定された環境名（または現在の環境）が有効な環境かどうかを検証します。
     *
     * @param string|null $env 検証する環境名（null の場合は現在の環境）
     * @return bool 有効な環境の場合は true
     */
    public static function isValidEnvironment(?string $env = null): bool
    {
        $target = $env ?? self::getEnvironment();
        return in_array($target, self::VALID_ENVIRONMENTS, true);
    }

    /**
     * 本番環境かどうかを判定します。
     *
     * @return bool 本番環境の場合は true
     */
    public static function isProduction(): bool
    {
        return self::getEnvironment() === self::ENV_PRODUCTION;
    }

    /**
     * テスト環境かどうかを判定します。
     *
     * @return bool テスト環境の場合は true
     */
    public static function isTest(): bool
    {
        return self::getEnvironment() === self::ENV_TEST;
    }

    /**
     * ローカル環境かどうかを判定します。
     *
     * @return bool ローカル環境の場合は true
     */
    public static function isLocal(): bool
    {
        return self::getEnvironment() === self::ENV_LOCAL;
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            self::ENV_PRODUCTION => '{APP-NAME}',
            self::ENV_TEST => '{APP-NAME}-test',
            default => '{APP-NAME}-test',
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
            self::ENV_PRODUCTION => '/{APP-NAME}',
            self::ENV_TEST => '/{APP-NAME}-test',
            default => '',
        };
    }

    /**
     * GCP/FirestoreのプロジェクトIDを取得します。
     * 環境変数 (GCP_PROJECT_ID, GOOGLE_CLOUD_PROJECT, GCLOUD_PROJECT) を優先し、
     * 未設定の場合は gcloud CLI の設定値を取得します。
     *
     * @return string FirestoreプロジェクトID。
     * @throws RuntimeException プロジェクトIDを取得できない場合。
     */
    public static function getFirestoreProjectId(): string
    {
        $envVars = ['GCP_PROJECT_ID', 'GOOGLE_CLOUD_PROJECT', 'GCLOUD_PROJECT'];
        foreach ($envVars as $var) {
            $val = getenv($var);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        $output = [];
        $exitCode = 1;
        exec('gcloud config get-value project 2>/dev/null', $output, $exitCode);
        $projectId = trim(implode("\n", $output));
        if ($exitCode === 0 && $projectId !== '' && $projectId !== '(unset)') {
            return $projectId;
        }

        throw new RuntimeException('Could not get Firestore project ID from environment variables or gcloud CLI.');
    }
}
