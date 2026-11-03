<?php

declare(strict_types=1);

namespace App\Infrastructure\Firestore;

use App\AppConfig;
use Google\Cloud\Firestore\FirestoreClient;

class FirestoreClientFactory
{
    public static function create(): FirestoreClient
    {
        $config = [
            'transport' => 'rest',
        ];
        if (AppConfig::isLocal()) {
            $config['projectId'] = AppConfig::getFirestoreProjectId();
        };
        return new FirestoreClient($config);
    }
}
