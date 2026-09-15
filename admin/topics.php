<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

/**
 * Memanggil cURL Engine secara dinamis berdasarkan request_template 
 * dan additional_headers dari tabel ai_providers.
 */
function execute_single_ai_request($model, $prompt) {
    // 1. Olah Additional Headers (JSON Format) dari DB
    $customHeaders = json_decode($model['additional_headers'] ?? '{}', true) ?: [];
    
    $headers = [
        'Content-Type: application/json',
        trim($model['header_key']) . ': ' . $model['header_prefix'] . $model['api_key']
    ];

    foreach ($customHeaders as $hKey => $hVal) {
        $headers[] = "{$hKey}: {$hVal}";
    }

    // 2. Olah Request Body Template (JSON Format) dari DB secara Dinamis
    $template = $model['request_template'];
    if (!$template) {
        // Fallback template jika kolom kosong
        $template = json_encode([
            'model'       => '{model}',
            'messages'    => [['role' => 'user', 'content' => '{prompt}']],
            'temperature' => '{temperature}',
            'max_tokens'  => '{max_tokens}'
        ]);
    }

    // Injeksi safe JSON prompt
    $encodedPrompt = json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Hapus tanda petik pembuka dan penutup dari json_encode agar cocok disisipkan ke dalam template
    $cleanPrompt = substr($encodedPrompt, 1, -1);

    // Replace Placeholder
    $jsonPayload = str_replace(
        ['{model}', '{prompt}', '{temperature}', '{max_tokens}'],
        [
            $model['model_code'],
            $cleanPrompt, 
            (float)$model['temperature'],
            (int)$model['max_tokens']
        ],
        $template
    );

    // Jalankan cURL Engine
    $ch = curl_init($model['base_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return ['status' => 'error', 'http_code' => 0, 'message' => 'Koneksi cURL gagal: ' . $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $errMsg = $decoded['error']['message'] ?? ('HTTP Status ' . $httpCode);
        return ['status' => 'error', 'http_code' => $httpCode, 'message' => $errMsg];
    }

    $rawText = $decoded['choices'][0]['message']['content'] ?? '';
    if (!$rawText) {
        return ['status' => 'error', 'http_code' => $httpCode, 'message' => 'Respon AI kosong.'];
    }

    // Parsing JSON dari AI Output
    $cleanText = trim($rawText);
    $cleanText = preg_replace('/^```(?:json)?/i', '', $cleanText);$cleanText = preg_replace('/```$/', '', $cleanText);
    $cleanText = trim($cleanText);

    $parsed = json_decode($cleanText, true);

    if (!is_array($parsed) || !isset($parsed['content_markdown'])) {
        if (preg_match('/\{.*\}/s', $cleanText, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
    }

    if (!is_array($parsed) || !isset($parsed['content_markdown'])) {
        return ['status' => 'error', 'http_code' => $httpCode, 'message' => 'Format JSON output AI tidak valid.'];
    }

    return [
        'status' => 'success',
        'data'   => [
            'content_markdown' => $parsed['content_markdown'],
            'summary_tldr'     => $parsed['summary_tldr'] ?? '',
        ]
    ];
}

/**
 * Pemanggilan AI Utama dengan Sistem Auto-Fallback ke Model Aktif Lainnya
 */
function call_ai_provider_with_fallback($pdo, $primaryModelId, $prompt) {
    // 1. Ambil Model Utama Pilihan User
    $stmt = $pdo->prepare("SELECT m.*, p.name as provider_name, p.base_url, p.api_key, p.header_key, p.header_prefix, p.request_template, p.additional_headers 
                           FROM ai_models m 
                           JOIN ai_providers p ON m.provider_id = p.id 
                           WHERE m.id = ? AND m.is_active = 1 AND p.is_active = 1");
    $stmt->execute([$primaryModelId]);
    $primaryModel = $stmt->fetch();

    if (!$primaryModel) {
        return ['status' => 'error', 'message' => 'Model AI utama tidak ditemukan atau tidak aktif.'];
    }

    // Eksekusi Panggilan Pertama
    $res = execute_single_ai_request($primaryModel, $prompt);
    if ($res['status'] === 'success') {
        return $res;
    }

    // 2. Jika Gagal (misal HTTP 429 / Rate Limit), Cari Model Alternatif Aktif Lainnya
    $stmtFallback = $pdo->prepare("SELECT m.*, p.name as provider_name, p.base_url, p.api_key, p.header_key, p.header_prefix, p.request_template, p.additional_headers 
                                   FROM ai_models m 
                                   JOIN ai_providers p ON m.provider_id = p.id 
                                   WHERE m.id != ? AND m.is_active = 1 AND p.is_active = 1 
                                   ORDER BY m.is_default DESC, m.id ASC");
    $stmtFallback->execute([$primaryModelId]);
    $fallbackModels = $stmtFallback->fetchAll();

    $failedLogs = [$primaryModel['display_name'] . ' (' . $res['message'] . ')'];

    foreach ($fallbackModels as $altModel) {
        $altRes = execute_single_ai_request($altModel, $prompt);
        if ($altRes['status'] === 'success') {
            $altRes['fallback_used'] = true;
            $altRes['fallback_provider'] = $altModel['display_name'];
            return $altRes;
        }
        $failedLogs[] = $altModel['display_name'] . ' (' . $altRes['message'] . ')';
    }

    return [
        'status'  => 'error', 
        'message' => 'Seluruh provider AI gagal merespons: ' . implode(' | ', $failedLogs)
    ];
}

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'save') {
            $id                  = $_POST['id'] ?? '';
            $module_id           = $_POST['module_id'];
            $title               = trim($_POST['title']);
            $slug                = trim($_POST['slug']) ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $order_position      = (int)($_POST['order_position'] ?? 0);
            $estimated_read_time = (int)($_POST['estimated_read_time'] ?? 5);
            $content_markdown    = $_POST['content_markdown'] ?? '';
            $summary_tldr        = trim($_POST['summary_tldr'] ?? '');
            $generation_type     = ($_POST['generation_type'] ?? 'manual') === 'ai_generated' ? 'ai_generated' : 'manual';

            $pdo->beginTransaction();

            if ($id) {
                // Update Topic
                $stmt = $pdo->prepare("UPDATE topics SET module_id = ?, title = ?, slug = ?, order_position = ?, estimated_read_time = ? WHERE id = ?");
                $stmt->execute([$module_id, $title, $slug, $order_position, $estimated_read_time, $id]);

                // Update or Insert Content
                $stmtContent = $pdo->prepare("INSERT INTO topic_contents (topic_id, content_markdown, summary_tldr, generation_type) 
                                              VALUES (?, ?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE content_markdown = VALUES(content_markdown), summary_tldr = VALUES(summary_tldr), generation_type = VALUES(generation_type)");
                $stmtContent->execute([$id, $content_markdown, $summary_tldr, $generation_type]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Topic & materi berhasil diperbarui']);
            } else {
                // Insert Topic
                $stmt = $pdo->prepare("INSERT INTO topics (module_id, title, slug, order_position, estimated_read_time) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$module_id, $title, $slug, $order_position, $estimated_read_time]);
                $new_topic_id = $pdo->lastInsertId();

                // Insert Content
                $stmtContent = $pdo->prepare("INSERT INTO topic_contents (topic_id, content_markdown, summary_tldr, generation_type) VALUES (?, ?, ?, ?)");
                $stmtContent->execute([$new_topic_id, $content_markdown, $summary_tldr, $generation_type]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Topic & materi baru berhasil dibuat']);
            }
            exit;
        }

        if ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Topic berhasil dihapus']);
            exit;
        }

        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT t.*, tc.content_markdown, tc.summary_tldr, tc.generation_type 
                                   FROM topics t 
                                   LEFT JOIN topic_contents tc ON t.id = tc.topic_id 
                                   WHERE t.id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
            exit;
        }

        if ($action === 'generate_ai') {
            $model_id    = $_POST['model_id'] ?? '';
            $module_id   = $_POST['module_id'] ?? '';
            $topic_title = trim($_POST['title'] ?? '');

            if (!$model_id || !$module_id || !$topic_title) {
                echo json_encode(['status' => 'error', 'message' => 'Lengkapi Induk Module, Judul Topic, dan Model AI.']);
                exit;
            }

            $stmtMod = $pdo->prepare("SELECT m.title as module_title, c.title as course_title 
                                      FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
            $stmtMod->execute([$module_id]);
            $context = $stmtMod->fetch();

            $moduleTitle = $context['module_title'] ?? '-';
            $courseTitle = $context['course_title'] ?? '-';

            $prompt = "Kamu adalah penulis materi pembelajaran profesional untuk platform e-learning.\n\n"
                . "Buatkan materi pembelajaran lengkap dalam Bahasa Indonesia untuk sub-bab/topic berikut:\n"
                . "- Course: {$courseTitle}\n"
                . "- Module: {$moduleTitle}\n"
                . "- Judul Topic: {$topic_title}\n\n"
                . "Ketentuan penulisan:\n"
                . "1. Tulis materi dalam format Markdown yang rapi (gunakan heading, sub-heading, bullet list, dan code block bila relevan dengan topik).\n"
                . "2. Bahasa jelas, terstruktur, dan mudah dipahami.\n"
                . "3. Sertakan ringkasan singkat (TL;DR) 2-4 kalimat.\n"
                . "4. WAJIB kembalikan jawaban HANYA dalam format JSON valid tanpa teks tambahan di luar JSON:\n"
                . '{"content_markdown": "...", "summary_tldr": "..."}' . "\n"
                . "5. Jangan bungkus JSON dengan code fence markdown.";

            $result = call_ai_provider_with_fallback($pdo, $model_id, $prompt);
            echo json_encode($result);
            exit;
        }
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch Modules
$all_modules = $pdo->query("SELECT m.id, m.title, c.title as course_title FROM modules m JOIN courses c ON m.course_id = c.id ORDER BY c.title ASC, m.order_position ASC")->fetchAll();

// Fetch Model AI Aktif
$active_ai_models = $pdo->query("SELECT m.id, m.display_name, p.name as provider_name 
                                 FROM ai_models m 
                                 JOIN ai_providers p ON m.provider_id = p.id 
                                 WHERE m.is_active = 1 AND p.is_active = 1 
                                 ORDER BY p.name ASC, m.display_name ASC")->fetchAll();

// --- DATA FETCHING & PAGINATION ---
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$whereClause = $search ? "WHERE t.title LIKE ? OR m.title LIKE ?" : "";
$countStmt   = $pdo->prepare("SELECT COUNT(*) FROM topics t JOIN modules m ON t.module_id = m.id $whereClause");

if ($search) {
    $searchTerm = "%$search%";
    $countStmt->execute([$searchTerm, $searchTerm]);
} else {
    $countStmt->execute();
}

$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$query = "SELECT t.*, m.title as module_title, c.title as course_title, tc.generation_type
          FROM topics t 
          JOIN modules m ON t.module_id = m.id 
          JOIN courses c ON m.course_id = c.id 
          LEFT JOIN topic_contents tc ON t.id = tc.topic_id
          $whereClause 
          ORDER BY t.id DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);

$paramIndex = 1;
if ($search) {
    $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

$stmt->execute();
$topics = $stmt->fetchAll();
?>

<!-- CDN Toast UI Editor -->
<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>

<?php require_once __DIR__ . '/views/layout_header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Manajemen Topics (Sub-Bab)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Kelola isi teks materi pembelajaran dan rangkuman Markdown</p>
    </div>
    <button onclick="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
        <i class='bx bx-plus text-base'></i>
        <span>Tambah Sub-bab Topic</span>
    </button>
</div>

<!-- Search Bar -->
<div class="bg-white border border-slate-200/80 rounded-2xl px-4 pt-4 pb-0 mb-6 shadow-sm">
    <form action="" method="GET" class="relative flex items-center">
        <i class='bx bx-search absolute left-3.5 text-slate-400 text-lg'></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul topic atau judul modul..." class="w-full pl-10 <?= $search !== '' ? 'pr-28' : 'pr-20' ?> py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
        
        <div class="absolute right-1.5 flex items-center gap-1">
            <?php if ($search !== ''): ?>
                <a href="?" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-slate-200/60 rounded-lg transition-all" title="Reset pencarian">
                    <i class='bx bx-x text-lg block'></i>
                </a>
            <?php endif; ?>
            <button type="submit" class="px-3 py-1 bg-slate-900 text-white text-[11px] font-semibold rounded-lg hover:bg-slate-800 transition-all">Cari</button>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-5">Topic Title</th>
                    <th class="py-3.5 px-5">Module & Course</th>
                    <th class="py-3.5 px-5">Estimasi Baca</th>
                    <th class="py-3.5 px-5">Tipe Konten</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                <?php if (empty($topics)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-400">Belum ada topic / sub-bab ditemukan.</td>
                    </tr>
                <?php else: foreach ($topics as $t): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5">
                            <span class="font-semibold text-slate-900 block"><?= htmlspecialchars($t['title']) ?></span>
                            <span class="text-[11px] text-slate-400 font-mono">/<?= htmlspecialchars($t['slug']) ?></span>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="font-semibold text-slate-800 block"><?= htmlspecialchars($t['module_title']) ?></span>
                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars($t['course_title']) ?></span>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1 text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md text-[11px]">
                                <i class='bx bx-time-five'></i> <?= (int)$t['estimated_read_time'] ?> menit
                            </span>
                        </td>
                        <td class="py-3.5 px-5">
                            <?php if ($t['generation_type'] === 'ai_generated'): ?>
                                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded-md text-[10px] font-semibold">AI Generated</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md text-[10px] font-semibold">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="./view-topic?s=<?= htmlspecialchars($t['slug']) ?>" target="_blank" class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white flex items-center justify-center transition-all" title="Baca Buku Materi">
                                    <i class='bx bx-book-open text-base'></i>
                                </a>
                                <button onclick="editData(<?= $t['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition-all">
                                    <i class='bx bx-edit-alt text-base'></i>
                                </button>
                                <button onclick="deleteData(<?= $t['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 flex items-center justify-center transition-all">
                                    <i class='bx bx-trash text-base'></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>
            <div class="flex items-center gap-1">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php 
                        $queryParams = ['p' => $i];
                        if (!empty($search)) $queryParams['q'] = $search;
                        $url = '?' . http_build_query($queryParams);
                    ?>
                    <a href="<?= $url ?>" class="w-7 h-7 flex items-center justify-center rounded-lg font-medium transition-all <?= $i === $page ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'hover:bg-slate-100 text-slate-600' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Form -->
<div id="topicModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-2 sm:p-3 overflow-y-auto">
    <div id="modalContainer" class="bg-white w-full max-w-none rounded-xl sm:rounded-2xl shadow-2xl my-auto transform transition-all scale-95 opacity-0 duration-200 flex flex-col min-h-[95vh] overflow-hidden">
        
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 sm:px-5 border-b border-slate-100 shrink-0">
            <h3 class="text-base font-bold text-slate-900 leading-tight" id="modalTitle">Tambah Topic Materi</h3>
            <button type="button" onclick="closeModal()" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                <i class="bx bx-x text-xl"></i>
            </button>
        </div>

        <!-- Form Wrapper -->
        <form id="topicForm" onsubmit="saveData(event)" class="flex flex-col flex-1 overflow-hidden px-4 py-3 sm:px-5">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="topic_id">
            <input type="hidden" name="generation_type" id="generation_type" value="manual">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 overflow-y-auto pr-1 flex-1 pb-2">
                <!-- Kolom Kiri -->
                <div class="lg:col-span-5 space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Induk Module (Bab)</label>
                        <select name="module_id" id="module_id" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                            <option value="">-- Pilih Module --</option>
                            <?php foreach ($all_modules as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['course_title']) ?> &raquo; <?= htmlspecialchars($m['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Judul Topic Sub-bab</label>
                        <input type="text" name="title" id="title" required placeholder="misal: Membuat Dynamic Routing PHP" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Slug URL</label>
                            <input type="text" name="slug" id="slug" placeholder="membuat-dynamic-routing-php" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Est. Baca</label>
                            <input type="number" name="estimated_read_time" id="estimated_read_time" value="5" min="1" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        </div>
                    </div>

                    <!-- Blok Generate AI -->
                    <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3 space-y-2">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-indigo-700 uppercase tracking-wider">
                            <i class='bx bx-bot text-sm'></i> Generate Materi dengan AI
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select id="ai_model_id" class="flex-1 px-3 py-2 bg-white border border-indigo-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                <?php if (empty($active_ai_models)): ?>
                                    <option value="">-- Tidak ada Model AI aktif --</option>
                                <?php else: ?>
                                    <option value="">-- Pilih Model AI --</option>
                                    <?php foreach ($active_ai_models as $am): ?>
                                        <option value="<?= $am['id'] ?>"><?= htmlspecialchars($am['display_name']) ?> (<?= htmlspecialchars($am['provider_name']) ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" id="btnGenerateAI" onclick="generateWithAI()" class="shrink-0 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-xl shadow-sm shadow-indigo-600/30 transition-all flex items-center justify-center gap-1.5">
                                <i id="btnGenerateIcon" class='bx bx-sparkles text-sm'></i>
                                <span id="btnGenerateLabel">Generate AI</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">TL;DR / Rangkuman Singkat</label>
                        <textarea name="summary_tldr" id="summary_tldr" rows="6" placeholder="Kesimpulan cepat dari materi ini..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
                    </div>
                </div>

                <!-- Kolom Kanan -->
                <div class="lg:col-span-7 flex flex-col min-h-[400px]">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Konten Materi (Markdown Format)</label>
                    <div class="flex-1">
                        <textarea name="content_markdown" id="content_markdown" class="hidden"></textarea>
                        <div id="markdown-editor"></div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-end gap-2 shrink-0">
                <button type="button" onclick="closeModal()" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-all">Batal</button>
                <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-md shadow-indigo-600/30 transition-all">Simpan Topic</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/toast.php'; ?>

<script>
let toastEditor;
let isProgrammaticFill = false;

function generateSlug(text) {
    return text.toString().toLowerCase().trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
}

document.getElementById('title').addEventListener('input', function() {
    document.getElementById('slug').value = generateSlug(this.value);
});

document.addEventListener('DOMContentLoaded', function () {
    toastEditor = new toastui.Editor({
        el: document.getElementById('markdown-editor'),
        height: '460px',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: '',
        usageStatistics: false
    });

    toastEditor.on('change', function () {
        if (!isProgrammaticFill) {
            document.getElementById('generation_type').value = 'manual';
        }
    });
});

document.getElementById('summary_tldr').addEventListener('input', function () {
    if (!isProgrammaticFill) {
        document.getElementById('generation_type').value = 'manual';
    }
});

function setGenerateButtonLoading(isLoading) {
    const btn = document.getElementById('btnGenerateAI');
    const icon = document.getElementById('btnGenerateIcon');
    const label = document.getElementById('btnGenerateLabel');

    btn.disabled = isLoading;
    if (isLoading) {
        icon.className = 'bx bx-loader-alt bx-spin text-sm';
        label.innerText = 'Generating...';
    } else {
        icon.className = 'bx bx-sparkles text-sm';
        label.innerText = 'Generate AI';
    }
}

function generateWithAI() {
    const moduleId = document.getElementById('module_id').value;
    const title    = document.getElementById('title').value.trim();
    const modelId  = document.getElementById('ai_model_id').value;

    if (!moduleId) return showToast('Pilih Induk Module terlebih dahulu.', 'error');
    if (!title) return showToast('Isi Judul Topic terlebih dahulu.', 'error');
    if (!modelId) return showToast('Pilih Model AI terlebih dahulu.', 'error');

    const fd = new FormData();
    fd.append('action', 'generate_ai');
    fd.append('module_id', moduleId);
    fd.append('title', title);
    fd.append('model_id', modelId);

    setGenerateButtonLoading(true);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            setGenerateButtonLoading(false);
            if (res.status === 'success') {
                isProgrammaticFill = true;

                if (toastEditor) {
                    toastEditor.setMarkdown(res.data.content_markdown || '');
                }
                document.getElementById('summary_tldr').value = res.data.summary_tldr || '';
                document.getElementById('generation_type').value = 'ai_generated';

                isProgrammaticFill = false;

                let msg = 'Materi berhasil di-generate oleh AI!';
                if (res.fallback_used) {
                    msg += ' (Fallback ke: ' + res.fallback_provider + ')';
                }
                showToast(msg, 'success');
            } else {
                showToast(res.message || 'Gagal generate materi AI.', 'error');
            }
        })
        .catch(err => {
            setGenerateButtonLoading(false);
            showToast('Terjadi kesalahan koneksi saat generate AI.', 'error');
            console.error(err);
        });
}

function openModal() {
    document.getElementById('topicForm').reset();
    document.getElementById('topic_id').value = '';
    document.getElementById('generation_type').value = 'manual';
    document.getElementById('modalTitle').innerText = 'Tambah Topic Baru';
    
    isProgrammaticFill = true;
    if (toastEditor) toastEditor.setMarkdown('');
    isProgrammaticFill = false;

    const modal = document.getElementById('topicModal');
    const container = document.getElementById('modalContainer');

    modal.classList.remove('hidden');
    setTimeout(() => {
        container.classList.remove('scale-95', 'opacity-0');
        container.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('topicModal');
    const container = document.getElementById('modalContainer');

    container.classList.remove('scale-100', 'opacity-100');
    container.classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 200);
}

function saveData(e) {
    e.preventDefault();

    if (toastEditor) {
        document.getElementById('content_markdown').value = toastEditor.getMarkdown();
    }

    const formData = new FormData(e.target);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            showToast(res.message, 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => {
        showToast('Gagal menyimpan data.', 'error');
        console.error(err);
    });
}

function editData(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success' && res.data) {
            const d = res.data;
            document.getElementById('topic_id').value = d.id;
            document.getElementById('module_id').value = d.module_id;
            document.getElementById('title').value = d.title;
            document.getElementById('slug').value = d.slug;
            document.getElementById('estimated_read_time').value = d.estimated_read_time;
            document.getElementById('generation_type').value = d.generation_type || 'manual';

            isProgrammaticFill = true;
            document.getElementById('summary_tldr').value = d.summary_tldr || '';

            if (toastEditor) {
                toastEditor.setMarkdown(d.content_markdown || '');
            }
            isProgrammaticFill = false;

            document.getElementById('modalTitle').innerText = 'Edit Topic & Content';

            const modal = document.getElementById('topicModal');
            const container = document.getElementById('modalContainer');

            modal.classList.remove('hidden');
            setTimeout(() => {
                container.classList.remove('scale-95', 'opacity-0');
                container.classList.add('scale-100', 'opacity-100');
            }, 10);
        } else {
            showToast('Gagal mengambil data topic.', 'error');
        }
    })
    .catch(err => console.error(err));
}

function deleteData(id) {
    if(!confirm('Hapus sub-bab topic ini secara permanen?')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(err => console.error(err));
}
</script>

<?php require_once __DIR__ . '/views/layout_footer.php'; ?>