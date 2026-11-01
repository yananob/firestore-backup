<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firestore Backup Manager</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col font-sans">
    @if (!App\AppConfig::isProduction())
        <!-- テスト環境オーバーレイ -->
        <div class="bg-amber-500 text-white text-xs px-4 py-1.5 flex justify-between items-center shadow-inner font-mono">
            <div>
                <span class="font-bold uppercase tracking-wider bg-amber-700 px-1.5 py-0.5 rounded text-[10px] mr-2">ENV: {{ App\AppConfig::getEnvironment() ?: 'local' }}</span>
                <span>Base Path: <code class="bg-amber-600 px-1 py-0.5 rounded">{{ App\AppConfig::getBasePath() ?: '/' }}</code></span>
                <span class="ml-3">Request Path: <code class="bg-amber-600 px-1 py-0.5 rounded">{{ $requestPath ?? '/' }}</code></span>
            </div>
            <span class="text-[11px] opacity-90">開発・テスト環境</span>
        </div>
    @endif

    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="bg-indigo-600 text-white p-2 rounded-lg shadow">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7h16M9 11h6M9 15h6"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Firestore Backup Manager</h1>
                    <p class="text-xs text-gray-500">Firestore データの定期バックアップ管理・手動エクスポート</p>
                </div>
            </div>
            <div>
                <form action="{{ $basePath }}/backup/run" method="POST" id="manualBackupHeaderForm" onsubmit="return confirmBackup(this);">
                    <button type="submit" id="backupAllBtn" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 cursor-pointer">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <span>すべてのバックアップを実行</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 flex-1 w-full">
        @if (!empty($message))
            <div class="mb-6 p-4 rounded-md {{ $message['type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }} flex items-start justify-between shadow-sm">
                <div class="flex items-center">
                    @if ($message['type'] === 'success')
                        <svg class="w-5 h-5 text-green-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                    <span class="text-sm font-medium">{{ $message['text'] }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove();" class="text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Close</span>
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white border-t border-gray-200 mt-8 py-4 text-center text-xs text-gray-500">
        Firestore Backup System &bull; Powered by Google Cloud Functions (PHP 8.4)
    </footer>

    <!-- バックアップ実行中スピナーモーダル -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center max-w-xs text-center">
            <svg class="animate-spin h-10 w-10 text-indigo-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-semibold text-gray-800">エクスポート処理を実行中...</p>
            <p class="text-xs text-gray-500 mt-1">Firestoreからデータを取得し、Cloud Storageへアップロードしています。しばらくお待ちください。</p>
        </div>
    </div>

    <script>
        function confirmBackup(form) {
            if (!confirm('バックアップを直ちに実行しますか？')) {
                return false;
            }
            document.getElementById('loadingOverlay').classList.remove('hidden');
            return true;
        }
    </script>
    @yield('scripts')
</body>
</html>
