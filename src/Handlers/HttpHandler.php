<?php

declare(strict_types=1);

namespace App\Handlers;

use App\AppConfig;
use App\Domain\Model\BackupConfig;
use App\Domain\Model\CommonConfig;
use App\Domain\Repository\ConfigRepositoryInterface;
use App\Domain\Service\BackupService;
use App\Domain\Service\FirestorePathService;
use App\Infrastructure\Firestore\FirestoreConfigRepository;
use App\Infrastructure\Storage\GcsStorageService;
use Carbon\Carbon;
use eftec\bladeone\BladeOne;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * HTTPリクエストを処理するハンドラークラス。
 */
class HttpHandler
{
    private ConfigRepositoryInterface $configRepository;
    private BackupService $backupService;
    private FirestorePathService $pathService;
    private BladeOne $blade;

    public function __construct(
        ?ConfigRepositoryInterface $configRepository = null,
        ?BackupService $backupService = null,
        ?FirestorePathService $pathService = null,
        ?BladeOne $blade = null
    ) {
        $this->configRepository = $configRepository ?? new FirestoreConfigRepository();
        $this->backupService = $backupService ?? new BackupService($this->configRepository, new GcsStorageService());
        $this->pathService = $pathService ?? new FirestorePathService();

        if ($blade === null) {
            $viewsDir = __DIR__ . '/../../views';
            // Cloud Functions 環境で書き込み可能な /tmp ディレクトリを使用
            $cacheDir = sys_get_temp_dir() . '/templates_c';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
            $blade = new BladeOne($viewsDir, $cacheDir, BladeOne::MODE_AUTO);
        }
        $this->blade = $blade;
    }

    /**
     * HTTPリクエストを処理します。
     *
     * @param ServerRequestInterface $request 受け取ったHTTPリクエスト
     * @return string レスポンス文字列 (HTML / JSON)
     */
    public function handle(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $method = strtoupper($request->getMethod());
        $basePath = AppConfig::getBasePath();

        // ベースパスが指定されている場合、プレフィックスを除去して正規化
        $routePath = $path;
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $routePath = substr($path, strlen($basePath));
        }
        $routePath = '/' . ltrim($routePath, '/');

        // クエリパラメータで通知メッセージを受け取る（リダイレクト対策）
        $queryParams = $request->getQueryParams();
        $message = null;
        if (isset($queryParams['msg_type'], $queryParams['msg_text'])) {
            $message = [
                'type' => (string) $queryParams['msg_type'],
                'text' => (string) $queryParams['msg_text'],
            ];
        }

        try {
            // ルーティング
            if ($method === 'GET' && $routePath === '/') {
                return $this->renderIndex($basePath, $path, $message);
            }

            if ($method === 'POST' && $routePath === '/settings/common') {
                return $this->handleSaveCommonConfig($request, $basePath);
            }

            if ($method === 'POST' && $routePath === '/settings/configs') {
                return $this->handleCreateBackupConfig($request, $basePath);
            }

            if ($method === 'POST' && $routePath === '/settings/configs/update') {
                return $this->handleUpdateBackupConfig($request, $basePath);
            }

            if ($method === 'POST' && $routePath === '/settings/configs/delete') {
                return $this->handleDeleteBackupConfig($request, $basePath);
            }

            if ($method === 'POST' && $routePath === '/backup/run') {
                return $this->handleRunBackup($request, $basePath);
            }

            if ($method === 'GET' && $routePath === '/api/firestore/collections') {
                return $this->handleApiSuggestions($request);
            }

            // ルートが見つからない場合
            return $this->redirectWithAlert($basePath, 'error', '指定されたページが見つかりません。');
        } catch (Throwable $e) {
            return $this->renderIndex($basePath, $path, [
                'type' => 'error',
                'text' => 'エラーが発生しました: ' . $e->getMessage(),
            ]);
        }
    }

    private function renderIndex(string $basePath, string $requestPath, ?array $message = null): string
    {
        $commonConfig = $this->configRepository->getCommonConfig();
        $configs = $this->configRepository->getBackupConfigs();

        return $this->blade->run('index', [
            'basePath' => $basePath,
            'requestPath' => $requestPath,
            'commonConfig' => $commonConfig,
            'configs' => $configs,
            'message' => $message,
        ]);
    }

    private function handleSaveCommonConfig(ServerRequestInterface $request, string $basePath): string
    {
        $params = $this->parsePostParams($request);
        $bucket = isset($params['bucket']) && is_string($params['bucket']) ? $params['bucket'] : '';
        $subPath = isset($params['basePath']) && is_string($params['basePath']) ? $params['basePath'] : '';

        $config = new CommonConfig($bucket, $subPath);
        $this->configRepository->saveCommonConfig($config);

        return $this->redirectWithAlert($basePath, 'success', 'GCS共通設定を保存しました。');
    }

    private function handleCreateBackupConfig(ServerRequestInterface $request, string $basePath): string
    {
        $params = $this->parsePostParams($request);
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $path = isset($params['firestorePath']) && is_string($params['firestorePath']) ? $params['firestorePath'] : '';
        $enabled = isset($params['enabled']) && ($params['enabled'] === '1' || $params['enabled'] === 'true');

        try {
            $cleanPath = trim(trim($path), '/');
            if (!str_contains($cleanPath, '/')) {
                throw new \InvalidArgumentException('ルートコレクションは指定できません。ドキュメントまたはサブコレクション以下のパス（例: users/u1 または users/u1/orders）を指定してください。');
            }

            $id = Uuid::uuid4()->toString();
            $config = new BackupConfig($id, $name, $cleanPath, $enabled, Carbon::now(), Carbon::now());
            $this->configRepository->saveBackupConfig($config);

            return $this->redirectWithAlert($basePath, 'success', "バックアップ設定「{$name}」を作成しました。");
        } catch (\InvalidArgumentException $e) {
            return $this->redirectWithAlert($basePath, 'error', $e->getMessage());
        }
    }

    private function handleUpdateBackupConfig(ServerRequestInterface $request, string $basePath): string
    {
        $params = $this->parsePostParams($request);
        $id = isset($params['id']) && is_string($params['id']) ? $params['id'] : '';
        $name = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        $path = isset($params['firestorePath']) && is_string($params['firestorePath']) ? $params['firestorePath'] : '';
        $enabled = isset($params['enabled']) && ($params['enabled'] === '1' || $params['enabled'] === 'true');

        try {
            $cleanPath = trim(trim($path), '/');
            if (!str_contains($cleanPath, '/')) {
                throw new \InvalidArgumentException('ルートコレクションは指定できません。ドキュメントまたはサブコレクション以下のパス（例: users/u1 または users/u1/orders）を指定してください。');
            }

            $existing = $this->configRepository->getBackupConfig($id);
            $createdAt = $existing ? $existing->getCreatedAt() : Carbon::now();

            $config = new BackupConfig($id, $name, $cleanPath, $enabled, $createdAt, Carbon::now());
            $this->configRepository->saveBackupConfig($config);

            return $this->redirectWithAlert($basePath, 'success', "バックアップ設定「{$name}」を更新しました。");
        } catch (\InvalidArgumentException $e) {
            return $this->redirectWithAlert($basePath, 'error', $e->getMessage());
        }
    }

    private function handleDeleteBackupConfig(ServerRequestInterface $request, string $basePath): string
    {
        $params = $this->parsePostParams($request);
        $id = isset($params['id']) && is_string($params['id']) ? $params['id'] : '';

        $this->configRepository->deleteBackupConfig($id);

        return $this->redirectWithAlert($basePath, 'success', 'バックアップ設定を削除しました。');
    }

    private function handleRunBackup(ServerRequestInterface $request, string $basePath): string
    {
        $params = $this->parsePostParams($request);
        $configId = isset($params['configId']) && is_string($params['configId']) ? $params['configId'] : null;

        if ($configId !== null && $configId !== '') {
            $config = $this->configRepository->getBackupConfig($configId);
            $commonConfig = $this->configRepository->getCommonConfig();
            if ($config === null) {
                return $this->redirectWithAlert($basePath, 'error', '対象の設定が見つかりません。');
            }
            if ($commonConfig === null || $commonConfig->getBucket() === '') {
                return $this->redirectWithAlert($basePath, 'error', 'GCS共通設定が設定されていません。バケット名を設定してください。');
            }

            $result = $this->backupService->backupSingleConfig($config, $commonConfig);
            if ($result['success']) {
                $msg = sprintf("バックアップ完了: %s (%d 件のデータをエクスポート -> %s)", $config->getName(), $result['count'], $result['path']);
                return $this->redirectWithAlert($basePath, 'success', $msg);
            }

            $errorMsg = $result['error'] ?? '不明なエラー';
            return $this->redirectWithAlert($basePath, 'error', "バックアップ失敗: {$errorMsg}");
        }

        // 全件実行
        $results = $this->backupService->backupAll();
        $msg = sprintf(
            "一括バックアップ完了: 合計 %d 件 (成功: %d, 失敗: %d)",
            $results['total'],
            $results['success'],
            $results['failed']
        );
        $type = $results['failed'] === 0 ? 'success' : 'error';

        return $this->redirectWithAlert($basePath, $type, $msg);
    }

    private function handleApiSuggestions(ServerRequestInterface $request): string
    {
        $queryParams = $request->getQueryParams();
        $path = isset($queryParams['path']) && is_string($queryParams['path']) ? $queryParams['path'] : '';

        $result = $this->pathService->getSuggestions($path);

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePostParams(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }

        $rawBody = (string) $request->getBody();
        if ($rawBody === '') {
            return [];
        }

        $params = [];
        parse_str($rawBody, $params);
        return $params;
    }

    private function redirectWithAlert(string $basePath, string $type, string $text): string
    {
        $url = ($basePath !== '' ? $basePath : '') . '/?' . http_build_query([
            'msg_type' => $type,
            'msg_text' => $text,
        ]);

        return sprintf(
            '<!DOCTYPE html><html style="background-color: #030712;"><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=%s"><style>body{background-color:#030712;color:#f3f4f6;margin:0;}</style></head><body style="background-color: #030712;"><script>window.location.href=%s;</script></body></html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            json_encode($url)
        );
    }
}
