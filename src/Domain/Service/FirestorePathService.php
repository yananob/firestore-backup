<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\AppConfig;
use Google\Cloud\Firestore\FirestoreClient;
use Throwable;

/**
 * Firestoreパスの階層や候補コレクション・ドキュメントを取得するサービス
 */
class FirestorePathService
{
    private FirestoreClient $firestoreClient;

    public function __construct(?FirestoreClient $firestoreClient = null)
    {
        $this->firestoreClient = $firestoreClient ?? new FirestoreClient([
            'projectId' => AppConfig::getFirestoreProjectId(),
        ]);
    }

    /**
     * ルートコレクションの一覧を取得します。
     *
     * @return array<int, string>
     */
    public function getRootCollections(): array
    {
        try {
            $collections = [];
            foreach ($this->firestoreClient->collections() as $col) {
                $collections[] = $col->id();
            }
            sort($collections);
            return $collections;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * 指定された親パス（空の場合はルート）の直下にある候補（サブコレクションまたはドキュメントID）を取得します。
     *
     * @param string $path 調査対象のパス (例: "", "users", "users/user1")
     * @return array{type: 'collections'|'documents'|'unknown', items: array<int, string>}
     */
    public function getSuggestions(string $path = ''): array
    {
        $cleanPath = trim(trim($path), '/');
        if ($cleanPath === '') {
            return [
                'type' => 'collections',
                'items' => $this->getRootCollections(),
            ];
        }

        $segments = explode('/', $cleanPath);
        $count = count($segments);

        try {
            // 奇数個のセグメント = コレクションパス (例: "users" or "users/doc/subcol")
            // 直下の子要素はドキュメント
            if ($count % 2 === 1) {
                $colRef = $this->firestoreClient->collection($cleanPath);
                // 上限20件程度のドキュメントIDを取得して候補とする
                $documents = $colRef->limit(20)->documents();
                $items = [];
                foreach ($documents as $doc) {
                    $items[] = $doc->id();
                }
                sort($items);
                return [
                    'type' => 'documents',
                    'items' => $items,
                ];
            }

            // 偶数個のセグメント = ドキュメントパス (例: "users/user1")
            // 直下の子要素はサブコレクション
            $docRef = $this->firestoreClient->document($cleanPath);
            $items = [];
            foreach ($docRef->collections() as $subCol) {
                $items[] = $subCol->id();
            }
            sort($items);
            return [
                'type' => 'collections',
                'items' => $items,
            ];
        } catch (Throwable) {
            return [
                'type' => 'unknown',
                'items' => [],
            ];
        }
    }
}
