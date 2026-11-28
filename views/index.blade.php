@extends('layout')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- 左カラム: GCS共通設定 & 新規バックアップ設定フォーム -->
    <div class="lg:col-span-1 space-y-6">

        <!-- GCS共通設定カード -->
        <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-800 overflow-hidden">
            <div class="bg-gray-800/80 px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-200 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    GCS 共通設定
                </h2>
                <span class="text-xs bg-indigo-950 text-indigo-300 border border-indigo-800 px-2 py-0.5 rounded font-mono">1設定のみ</span>
            </div>
            <form action="{{ $basePath }}/settings/common" method="POST" class="p-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">GCS バケット名 <span class="text-red-400">*</span></label>
                    <input type="text" name="bucket" value="{{ $commonConfig ? $commonConfig->getBucket() : '' }}" required placeholder="my-backup-bucket" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                    <p class="text-[11px] text-gray-500 mt-1">保存先の Google Cloud Storage バケット名</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">ベースパス（任意）</label>
                    <input type="text" name="basePath" value="{{ $commonConfig ? $commonConfig->getBasePath() : '' }}" placeholder="firestore-backups/daily" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                    <p class="text-[11px] text-gray-500 mt-1">保存先プレフィックス。末尾に「/YYYYMMDD」が付与されます。</p>
                </div>
                <div class="pt-2 flex justify-end">
                    <button type="submit" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-white text-xs font-medium rounded shadow transition cursor-pointer">
                        共通設定を保存
                    </button>
                </div>
            </form>
        </div>

        <!-- バックアップ設定追加フォーム -->
        <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-800 overflow-hidden">
            <div class="bg-gray-800/80 px-4 py-3 border-b border-gray-800">
                <h2 class="text-sm font-semibold text-gray-200 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    設定の新規登録
                </h2>
            </div>
            <form action="{{ $basePath }}/settings/configs" method="POST" class="p-4 space-y-4" onsubmit="return handleCreateSubmit(this);">
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">設定名 <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required placeholder="例: ユーザーマスタ" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-[11px] text-gray-500 mt-1">バックアップ対象の識別用名称</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Firestore パス <span class="text-red-400">*</span></label>
                    <div class="flex space-x-1">
                        <input type="text" id="newFirestorePath" name="firestorePath" required placeholder="users/u1 または users/u1/orders" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">ドキュメントまたはサブコレクションの相対パス（ルートコレクション不可）</p>

                    <!-- 入力補助 UI -->
                    <div class="mt-2 bg-indigo-950/40 p-2.5 rounded border border-indigo-900/60">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-semibold text-indigo-300 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                パス入力アシスト
                            </span>
                            <button type="button" onclick="loadSuggestions('newFirestorePath')" class="text-[10px] text-indigo-400 hover:text-indigo-300 underline">候補を再取得</button>
                        </div>
                        <div id="pathSuggestions_newFirestorePath" class="flex flex-wrap gap-1 max-h-24 overflow-y-auto">
                            <span class="text-[11px] text-gray-500 italic">「候補を再取得」またはパスを入力</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="enabledNew" name="enabled" value="1" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-700 bg-gray-950 rounded">
                    <label for="enabledNew" class="ml-2 block text-xs text-gray-300">このバックアップを有効にする</label>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">LINE 通知先（任意）</label>
                    <select name="lineTargetKey" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 shadow-sm border px-3 py-2">
                        <option value="">通知しない</option>
                        @foreach ($lineTargetOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">バックアップ完了時にデータ数と容量を送信します。</p>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded shadow transition cursor-pointer">
                        設定を追加する
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 右カラム: バックアップ設定一覧 & 各種アクション -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-gray-900 rounded-lg shadow-sm border border-gray-800 overflow-hidden">
            <div class="bg-gray-800/80 px-4 py-3 border-b border-gray-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-200">登録済みバックアップ設定一覧</h2>
                    <p class="text-xs text-gray-400">定期バッチまたは手動実行時にエクスポートされる設定一覧です</p>
                </div>
                <span class="text-xs font-bold text-gray-300 bg-gray-800 border border-gray-700 px-2 py-0.5 rounded-full">{{ count($configs) }} 件</span>
            </div>

            @if (empty($configs))
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-sm">設定がまだ登録されていません。</p>
                    <p class="text-xs mt-1 text-gray-500">左のフォームからバックアップ対象の Firestore パスを追加してください。</p>
                </div>
            @else
                <div class="divide-y divide-gray-800">
                    @foreach ($configs as $config)
                        <div class="p-4 hover:bg-gray-800/50 transition flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full {{ $config->isEnabled() ? 'bg-green-500' : 'bg-gray-600' }}" title="{{ $config->isEnabled() ? '有効' : '無効' }}"></span>
                                    <h3 class="text-sm font-bold text-gray-200">{{ $config->getName() }}</h3>
                                    <span class="text-[10px] bg-gray-800 text-gray-400 border border-gray-700 px-1.5 py-0.5 rounded font-mono">{{ $config->getId() }}</span>
                                </div>
                                <div class="text-xs text-gray-400 font-mono flex items-center flex-wrap gap-1.5">
                                     <span class="text-gray-500 mr-1">Path:</span>
                                    <span class="bg-gray-950 border border-gray-800 px-1.5 py-0.5 rounded text-indigo-400 font-semibold">{{ $config->getFirestorePath() }}</span>
                                     @if ($config->isRootCollection())
                                         <span class="text-[10px] bg-amber-950 border border-amber-800 text-amber-300 px-1.5 py-0.5 rounded font-sans" title="ルートコレクションはエクスポート対象外です">ルートパス（要修正）</span>
                                     @endif
                                </div>
                                <div class="text-[11px] text-gray-500 flex space-x-4">
                                    <span>作成: {{ $config->getCreatedAt() ? $config->getCreatedAt()->format('Y/m/d H:i') : '-' }}</span>
                                    <span>更新: {{ $config->getUpdatedAt() ? $config->getUpdatedAt()->format('Y/m/d H:i') : '-' }}</span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 shrink-0">
                                <!-- 個別手動バックアップボタン -->
                                <form action="{{ $basePath }}/backup/run" method="POST" onsubmit="return confirmBackup(this);">
                                    <input type="hidden" name="configId" value="{{ $config->getId() }}">
                                    <button type="submit" class="px-2.5 py-1.5 bg-indigo-950/80 hover:bg-indigo-900 text-indigo-300 text-xs font-medium rounded border border-indigo-800 transition flex items-center cursor-pointer">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        個別実行
                                    </button>
                                </form>

                                <!-- 編集モーダル呼び出し -->
                                <button type="button" onclick="openEditModal({{ json_encode($config->toArray()) }})" class="px-2.5 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs font-medium rounded border border-gray-700 transition cursor-pointer">
                                    編集
                                </button>

                                <!-- 削除ボタン -->
                                <form action="{{ $basePath }}/settings/configs/delete" method="POST" onsubmit="return confirm('本当にこの設定を削除しますか？');">
                                    <input type="hidden" name="id" value="{{ $config->getId() }}">
                                    <button type="submit" class="px-2.5 py-1.5 bg-red-950/80 hover:bg-red-900 text-red-300 text-xs font-medium rounded border border-red-800 transition cursor-pointer">
                                        削除
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="fixed inset-0 bg-gray-950 bg-opacity-70 z-40 flex items-center justify-center hidden">
    <div class="bg-gray-900 border border-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-4 overflow-hidden">
        <div class="bg-gray-800/80 px-4 py-3 border-b border-gray-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-200">バックアップ設定の編集</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-200">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
        <form action="{{ $basePath }}/settings/configs/update" method="POST" class="p-4 space-y-4" onsubmit="return handleUpdateSubmit(this);">
            <input type="hidden" id="editConfigId" name="id">

            <div>
                <label class="block text-xs font-medium text-gray-300 mb-1">設定名 <span class="text-red-400">*</span></label>
                <input type="text" id="editConfigName" name="name" required class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-300 mb-1">Firestore パス <span class="text-red-400">*</span></label>
                <input type="text" id="editFirestorePath" name="firestorePath" required class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm border px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                <div class="mt-2 bg-indigo-950/40 p-2.5 rounded border border-indigo-900/60">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-semibold text-indigo-300">パス候補</span>
                        <button type="button" onclick="loadSuggestions('editFirestorePath')" class="text-[10px] text-indigo-400 hover:text-indigo-300 underline">候補を取得</button>
                    </div>
                    <div id="pathSuggestions_editFirestorePath" class="flex flex-wrap gap-1 max-h-24 overflow-y-auto">
                        <span class="text-[11px] text-gray-500 italic">「候補を取得」をクリック</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="editConfigEnabled" name="enabled" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-700 bg-gray-950 rounded">
                <label for="editConfigEnabled" class="ml-2 block text-xs text-gray-300">このバックアップを有効にする</label>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-300 mb-1">LINE 通知先（任意）</label>
                <select id="editLineTargetKey" name="lineTargetKey" class="w-full text-sm rounded-md bg-gray-950 border-gray-700 text-gray-100 shadow-sm border px-3 py-2">
                    <option value="">通知しない</option>
                    @foreach ($lineTargetOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-gray-800">
                <button type="button" onclick="closeEditModal()" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 text-xs font-medium rounded transition cursor-pointer">
                    キャンセル
                </button>
                <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded shadow transition cursor-pointer">
                    変更を保存
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEditModal(config) {
        document.getElementById('editConfigId').value = config.id;
        document.getElementById('editConfigName').value = config.name;
        document.getElementById('editFirestorePath').value = config.firestorePath;
        document.getElementById('editConfigEnabled').checked = !!config.enabled;
        document.getElementById('editLineTargetKey').value = config.lineTargetKey || '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function handleUpdateSubmit(form) {
        const pathInput = document.getElementById('editFirestorePath');
        const pathVal = pathInput.value.trim().replace(/^\/+|\/+$/g, '');
        if (!pathVal.includes('/')) {
            alert('ルートコレクションは指定できません。ドキュメントまたはサブコレクション以下のパス（例: users/u1 または users/u1/orders）を指定してください。');
            pathInput.focus();
            return false;
        }
        showLoading('設定の変更を保存中...', 'Firestoreのバックアップ設定を更新しています。しばらくお待ちください。');
        return true;
    }

    function handleCreateSubmit(form) {
        const pathInput = document.getElementById('newFirestorePath');
        const pathVal = pathInput.value.trim().replace(/^\/+|\/+$/g, '');
        if (!pathVal.includes('/')) {
            alert('ルートコレクションは指定できません。ドキュメントまたはサブコレクション以下のパス（例: users/u1 または users/u1/orders）を指定してください。');
            pathInput.focus();
            return false;
        }
        showLoading('新規設定を登録中...', 'Firestoreのバックアップ設定を登録しています。しばらくお待ちください。');
        return true;
    }

    async function loadSuggestions(inputId) {
        const inputElem = document.getElementById(inputId);
        const container = document.getElementById('pathSuggestions_' + inputId);
        const currentVal = inputElem.value.trim();

        container.innerHTML = '<span class="text-[11px] text-gray-400">読み込み中...</span>';

        try {
            const res = await fetch('{{ $basePath }}/api/firestore/collections?path=' + encodeURIComponent(currentVal));
            const data = await res.json();

            if (!data.items || data.items.length === 0) {
                container.innerHTML = '<span class="text-[11px] text-gray-400">利用可能な候補は見つかりませんでした</span>';
                return;
            }

            container.innerHTML = '';
            data.items.forEach(item => {
                const badge = document.createElement('button');
                badge.type = 'button';
                badge.className = 'text-[11px] bg-gray-800 border border-indigo-800 text-indigo-300 px-2 py-0.5 rounded shadow-xs hover:bg-indigo-900/60 cursor-pointer font-mono';
                badge.textContent = (data.type === 'documents' ? 'doc: ' : 'col: ') + item;
                badge.onclick = () => {
                    if (currentVal === '') {
                        inputElem.value = item;
                    } else if (currentVal.endsWith('/')) {
                        inputElem.value = currentVal + item;
                    } else {
                        // パスの直下に追加
                        inputElem.value = currentVal + '/' + item;
                    }
                    // 次の候補を自動読み込み
                    loadSuggestions(inputId);
                };
                container.appendChild(badge);
            });
        } catch (e) {
            container.innerHTML = '<span class="text-[11px] text-red-500">取得に失敗しました</span>';
        }
    }

    // 初回読み込み時に新規フォームの候補を自動フェッチ
    document.addEventListener('DOMContentLoaded', () => {
        loadSuggestions('newFirestorePath');
    });
</script>
@endsection
