<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'save_provider') {
            $id            = $_POST['id'] ?? '';
            $name          = trim($_POST['name'] ?? '');
            $slug          = trim($_POST['slug'] ?? '');
            $base_url      = trim($_POST['base_url'] ?? '');
            $api_key       = trim($_POST['api_key'] ?? '');
            $header_key    = trim($_POST['header_key'] ?? '') ?: 'Authorization';
            $header_prefix = $_POST['header_prefix'] ?? '';
            $is_active     = isset($_POST['is_active']) ? 1 : 0;

            if ($id) {
                $stmt = $pdo->prepare("UPDATE ai_providers SET name = ?, slug = ?, base_url = ?, api_key = ?, header_key = ?, header_prefix = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $base_url, $api_key, $header_key, $header_prefix, $is_active, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO ai_providers (name, slug, base_url, api_key, header_key, header_prefix, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $base_url, $api_key, $header_key, $header_prefix, $is_active]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Provider AI berhasil disimpan']);
            exit;
        }

        if ($action === 'save_model') {
            $id           = $_POST['id'] ?? '';
            $provider_id  = $_POST['provider_id'] ?? '';
            $model_code   = trim($_POST['model_code'] ?? '');
            $display_name = trim($_POST['display_name'] ?? '');
            $max_tokens   = (int)($_POST['max_tokens'] ?? 4096);
            $temperature  = (float)($_POST['temperature'] ?? 0.70);
            $is_default   = isset($_POST['is_default']) ? 1 : 0;
            $is_active    = isset($_POST['is_active']) ? 1 : 0;

            if ($is_default) {
                $pdo->prepare("UPDATE ai_models SET is_default = 0 WHERE provider_id = ?")->execute([$provider_id]);
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE ai_models SET provider_id = ?, model_code = ?, display_name = ?, max_tokens = ?, temperature = ?, is_default = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$provider_id, $model_code, $display_name, $max_tokens, $temperature, $is_default, $is_active, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO ai_models (provider_id, model_code, display_name, max_tokens, temperature, is_default, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$provider_id, $model_code, $display_name, $max_tokens, $temperature, $is_default, $is_active]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Model AI berhasil disimpan']);
            exit;
        }

        if ($action === 'delete_model') {
            $stmt = $pdo->prepare("DELETE FROM ai_models WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['status' => 'success', 'message' => 'Model berhasil dihapus']);
            exit;
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

$providers = $pdo->query("SELECT * FROM ai_providers ORDER BY id ASC")->fetchAll();
$models    = $pdo->query("SELECT m.*, p.name as provider_name FROM ai_models m JOIN ai_providers p ON m.provider_id = p.id ORDER BY p.id ASC, m.id ASC")->fetchAll();

require_once __DIR__ . '/views/layout_header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Konfigurasi AI Engine</h1>
        <p class="text-xs text-slate-500 mt-0.5">Atur API Key Provider dan pemilihan Model AI</p>
    </div>
</div>

<!-- Grid Providers -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <?php foreach ($providers as $p): ?>
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm relative flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="px-2 py-0.5 <?= $p['is_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' ?> rounded-md text-[10px] font-semibold">
                        <?= $p['is_active'] ? 'ACTIVE' : 'DISABLED' ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-400 font-mono mb-2 truncate"><?= htmlspecialchars($p['base_url']) ?></p>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs font-mono text-slate-600 flex items-center justify-between">
                    <span>Key: <?= substr($p['api_key'], 0, 8) ?>••••••••</span>
                    <i class='bx bx-key text-slate-400'></i>
                </div>
            </div>

            <button onclick='editProvider(<?= json_encode($p) ?>)' class="mt-4 w-full py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl transition-all flex items-center justify-center gap-1">
                <i class='bx bx-cog'></i> Edit Key & Endpoint
            </button>
        </div>
    <?php endforeach; ?>
</div>

<!-- Table Models -->
<div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden mb-6">
    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-900 text-sm">Daftar Model AI Terdaftar</h3>
        <button onclick="openModelModal()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-sm transition-all flex items-center gap-1">
            <i class='bx bx-plus'></i> Tambah Model
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-5">Provider</th>
                    <th class="py-3.5 px-5">Display Name</th>
                    <th class="py-3.5 px-5">Model Code</th>
                    <th class="py-3.5 px-5">Max Tokens / Temp</th>
                    <th class="py-3.5 px-5">Default</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                <?php foreach ($models as $m): ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-3.5 px-5 font-semibold text-slate-800"><?= htmlspecialchars($m['provider_name']) ?></td>
                        <td class="py-3.5 px-5 font-semibold text-indigo-600"><?= htmlspecialchars($m['display_name']) ?></td>
                        <td class="py-3.5 px-5 font-mono text-slate-500"><?= htmlspecialchars($m['model_code']) ?></td>
                        <td class="py-3.5 px-5 text-slate-600 font-mono"><?= $m['max_tokens'] ?> / <?= $m['temperature'] ?></td>
                        <td class="py-3.5 px-5">
                            <?php if ($m['is_default']): ?>
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-md text-[10px] font-bold">DEFAULT</span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick='editModel(<?= json_encode($m) ?>)' class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition-all">
                                    <i class='bx bx-edit-alt text-base'></i>
                                </button>
                                <button onclick="deleteModel(<?= $m['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 inline-flex items-center justify-center transition-all">
                                    <i class='bx bx-trash text-base'></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit Provider -->
<div id="providerModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4" id="provTitle">Edit Provider AI</h3>
        <form id="providerForm" onsubmit="saveProvider(event)" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="save_provider">
            <input type="hidden" name="id" id="p_id">

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Nama Provider</label>
                <input type="text" name="name" id="p_name" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Slug Identifier</label>
                <input type="text" name="slug" id="p_slug" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Base Endpoint URL</label>
                <input type="text" name="base_url" id="p_url" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl font-mono">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">API Key</label>
                <input type="password" name="api_key" id="p_key" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl font-mono">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Header Key</label>
                    <input type="text" name="header_key" id="p_hkey" value="Authorization" class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Header Prefix</label>
                    <input type="text" name="header_prefix" id="p_hprefix" value="Bearer " class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer mt-2">
                    <input type="checkbox" name="is_active" id="p_active" class="rounded text-indigo-600">
                    <span class="font-semibold text-slate-700">Aktifkan Provider Ini</span>
                </label>
            </div>
            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="closeProvModal()" class="px-4 py-2 bg-slate-100 rounded-xl">Batal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Add/Edit Model -->
<div id="modelModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4" id="m_modal_title">Tambah Model AI Baru</h3>
        <form id="modelForm" onsubmit="saveModel(event)" class="space-y-3 text-xs">
            <input type="hidden" name="action" value="save_model">
            <input type="hidden" name="id" id="m_id">

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Pilih Provider</label>
                <select name="provider_id" id="m_provider_id" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
                    <?php foreach($providers as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Display Name</label>
                <input type="text" name="display_name" id="m_display_name" required placeholder="misal: Llama 3.3 70B (Groq)" class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Model Code (API Param)</label>
                <input type="text" name="model_code" id="m_model_code" required placeholder="llama-3.3-70b-versatile" class="w-full px-3 py-2 bg-slate-50 border rounded-xl font-mono">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Max Tokens</label>
                    <input type="number" name="max_tokens" id="m_max_tokens" value="4096" class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Temperature</label>
                    <input type="number" step="0.01" name="temperature" id="m_temperature" value="0.70" class="w-full px-3 py-2 bg-slate-50 border rounded-xl">
                </div>
            </div>
            <div class="flex items-center gap-4 pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_default" id="m_is_default" class="rounded text-indigo-600">
                    <span class="font-semibold text-slate-700">Set Default</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" id="m_is_active" checked class="rounded text-indigo-600">
                    <span class="font-semibold text-slate-700">Aktif</span>
                </label>
            </div>
            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="closeModelModal()" class="px-4 py-2 bg-slate-100 rounded-xl">Batal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl">Simpan Model</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/toast.php'; ?>

<script>
function editProvider(data) {
    document.getElementById('p_id').value = data.id;
    document.getElementById('p_name').value = data.name;
    document.getElementById('p_slug').value = data.slug;
    document.getElementById('p_url').value = data.base_url;
    document.getElementById('p_key').value = data.api_key;
    document.getElementById('p_hkey').value = data.header_key;
    document.getElementById('p_hprefix').value = data.header_prefix;
    document.getElementById('p_active').checked = data.is_active == 1;
    document.getElementById('providerModal').classList.remove('hidden');
}

function openModelModal() {
    document.getElementById('modelForm').reset();
    document.getElementById('m_id').value = '';
    document.getElementById('m_modal_title').innerText = 'Tambah Model AI Baru';
    document.getElementById('m_is_active').checked = true;
    document.getElementById('modelModal').classList.remove('hidden');
}

function editModel(data) {
    document.getElementById('m_id').value = data.id;
    document.getElementById('m_provider_id').value = data.provider_id;
    document.getElementById('m_display_name').value = data.display_name;
    document.getElementById('m_model_code').value = data.model_code;
    document.getElementById('m_max_tokens').value = data.max_tokens;
    document.getElementById('m_temperature').value = data.temperature;
    document.getElementById('m_is_default').checked = data.is_default == 1;
    document.getElementById('m_is_active').checked = data.is_active == 1;
    document.getElementById('m_modal_title').innerText = 'Edit Model AI';
    document.getElementById('modelModal').classList.remove('hidden');
}

function closeProvModal() { document.getElementById('providerModal').classList.add('hidden'); }
function closeModelModal() { document.getElementById('modelModal').classList.add('hidden'); }

function saveProvider(e) {
    e.preventDefault();
    fetch('<?= admin_url("ai-engine.php") ?>', { method: 'POST', body: new FormData(e.target) })
    .then(r => r.json()).then(res => {
        showToast(res.message, res.status);
        if(res.status === 'success') setTimeout(() => location.reload(), 800);
    });
}

function saveModel(e) {
    e.preventDefault();
    fetch('<?= admin_url("ai-engine.php") ?>', { method: 'POST', body: new FormData(e.target) })
    .then(r => r.json()).then(res => {
        showToast(res.message, res.status);
        if(res.status === 'success') setTimeout(() => location.reload(), 800);
    });
}

function deleteModel(id) {
    if(!confirm('Hapus konfigurasi model AI ini?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_model');
    fd.append('id', id);
    fetch('<?= admin_url("ai-engine.php") ?>', { method: 'POST', body: fd })
    .then(r => r.json()).then(res => {
        showToast(res.message, res.status);
        if(res.status === 'success') setTimeout(() => location.reload(), 800);
    });
}
</script>

<?php require_once __DIR__ . '/views/layout_footer.php'; ?>