<?php

declare(strict_types=1);

namespace App\Handlers;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTPリクエストを処理するハンドラークラス。
 */
class HttpHandler
{
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface|null $logger ロガーのインスタンス（指定しない場合はMonologを標準出力向けに初期化）
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $monolog = new Logger('http_logger');
            $monolog->pushHandler(new StreamHandler('php://stdout', Level::Info));
            $this->logger = $monolog;
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * HTTPリクエストを処理します。
     *
     * @param ServerRequestInterface $request 受け取ったHTTPリクエスト
     * @return string レスポンス文字列
     */
    public function handle(ServerRequestInterface $request): string
    {
        $this->logger->info('HTTP request received', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);

        return 'Hello, World!';
    }
}
