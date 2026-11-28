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

        throw new RuntimeException('Could not get Firestore project ID.');
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'firestore-backup',
            'test' => 'firestore-backup-test',
            default => 'firestore-backup-test',
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
            'production' => '/firestore-backup',
            'test' => '/firestore-backup-test',
            default => '',
        };
    }

    /**
     * LINE送信用トークンと送信先IDのマッピングを取得します。
     *
     * @return array{tokens: array<string, string>, target_ids: array<string, string>}
     */
    public static function getLineConfig(): array
    {
        $raw = getenv('LINE_TOKENS_N_TARGETS');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $_ENV['LINE_TOKENS_N_TARGETS'] ?? $_SERVER['LINE_TOKENS_N_TARGETS'] ?? null;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return ['tokens' => [], 'target_ids' => []];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('LINE_TOKENS_N_TARGETS is not valid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('LINE_TOKENS_N_TARGETS must decode to an array.');
        }

        return [
            'tokens' => self::filterStringMap($decoded['tokens'] ?? []),
            'target_ids' => self::filterStringMap($decoded['target_ids'] ?? []),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function filterStringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && (is_scalar($item) || $item === null)) {
                $result[$key] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * LINE通知に利用できる設定キーを取得します。
     *
     * @return array<string, string>
     */
    public static function getLineTargetOptions(): array
    {
        $config = self::getLineConfig();
        $keys = array_intersect(array_keys($config['tokens']), array_keys($config['target_ids']));
        $options = [];
        foreach ($keys as $key) {
            $options[$key] = $key;
        }

        return $options;
    }
}
