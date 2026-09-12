<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

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
            
            // Mengambil markdown tanpa merusak indentasi internal
            $content_markdown    = $_POST['content_markdown'] ?? '';
            $summary_tldr        = trim($_POST['summary_tldr'] ?? '');

            $pdo->beginTransaction();

            if ($id) {
                // Update Topic
                $stmt = $pdo->prepare("UPDATE topics SET module_id = ?, title = ?, slug = ?, order_position = ?, estimated_read_time = ? WHERE id = ?");
                $stmt->execute([$module_id, $title, $slug, $order_position, $estimated_read_time, $id]);

                // Update or Insert Content
                $stmtContent = $pdo->prepare("INSERT INTO topic_contents (topic_id, content_markdown, summary_tldr, generation_type) 
                                              VALUES (?, ?, ?, 'manual') 
                                              ON DUPLICATE KEY UPDATE content_markdown = VALUES(content_markdown), summary_tldr = VALUES(summary_tldr)");
                $stmtContent->execute([$id, $content_markdown, $summary_tldr]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Topic & materi berhasil diperbarui']);
            } else {
                // Insert Topic
                $stmt = $pdo->prepare("INSERT INTO topics (module_id, title, slug, order_position, estimated_read_time) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$module_id, $title, $slug, $order_position, $estimated_read_time]);
                $new_topic_id = $pdo->lastInsertId();

                // Insert Content
                $stmtContent = $pdo->prepare("INSERT INTO topic_contents (topic_id, content_markdown, summary_tldr, generation_type) VALUES (?, ?, ?, 'manual')");
                $stmtContent->execute([$new_topic_id, $content_markdown, $summary_tldr]);

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
            $stmt = $pdo->prepare("SELECT t.*, tc.content_markdown, tc.summary_tldr 
                                   FROM topics t 
                                   LEFT JOIN topic_contents tc ON t.id = tc.topic_id 
                                   WHERE t.id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
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

// --- DATA FETCHING & PAGINATION (FIXED) ---
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

// 1. Hitung total data
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

// 2. Query Fetching
$query = "SELECT t.*, m.title as module_title, c.title as course_title, tc.generation_type
          FROM topics t 
          JOIN modules m ON t.module_id = m.id 
          JOIN courses c ON m.course_id = c.id 
          LEFT JOIN topic_contents tc ON t.id = tc.topic_id
          $whereClause 
          ORDER BY tc.topic_id ASC, t.order_position ASC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);

// Parameter Index Binding
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
    <form action="./topics" method="GET" class="relative flex items-center">
        <i class='bx bx-search absolute left-3.5 text-slate-400 text-lg'></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul topic atau judul modul..." class="w-full pl-10 <?= $search !== '' ? 'pr-28' : 'pr-20' ?> py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
        
        <div class="absolute right-1.5 flex items-center gap-1">
            <?php if ($search !== ''): ?>
                <a href="./topics" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-slate-200/60 rounded-lg transition-all" title="Reset pencarian">
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
                                <i class='bx bx-time-five'></i> <?= $t['estimated_read_time'] ?> menit
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
                        $url = './topics?' . http_build_query($queryParams);
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

            <h3 class="text-base font-bold text-slate-900 leading-tight" id="modalTitle">
                Tambah Topic Materi
            </h3>

            <button
                type="button"
                onclick="closeModal()"
                class="w-7 h-7 flex items-center justify-center
                       text-slate-400 hover:text-slate-600
                       hover:bg-slate-100 rounded-lg transition-colors">
                <i class="bx bx-x text-xl"></i>
            </button>

        </div>

        <!-- Form Wrapper -->
        <form id="topicForm" onsubmit="saveData(event)" class="flex flex-col flex-1 overflow-hidden px-4 py-3 sm:px-5">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="topic_id">

            <!-- Body Grid (Scrollable) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 overflow-y-auto pr-1 flex-1 pb-2">
                <!-- Kolom Kiri: Form Meta Data -->
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

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">TL;DR / Rangkuman Singkat</label>
                        <textarea name="summary_tldr" id="summary_tldr" rows="7" placeholder="Kesimpulan cepat dari materi ini..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
                    </div>
                </div>

                <!-- Kolom Kanan: Toast UI Editor -->
                <div class="lg:col-span-7 flex flex-col min-h-[400px]">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Konten Materi (Markdown Format)
                    </label>
                    <div class="flex-1">
                        <textarea name="content_markdown" id="content_markdown" style="display:none;"></textarea>
                        <div id="markdown-editor"></div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-end gap-2 shrink-0">
                <button
                    type="button"
                    onclick="closeModal()"
                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-all">
                    Batal
                </button>

                <button
                    type="submit"
                    class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-md shadow-indigo-600/30 transition-all">
                    Simpan Topic
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/toast.php'; ?>

<script>
let toastEditor;

// Helper slug generator
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

// Inisialisasi Toast UI Editor
document.addEventListener('DOMContentLoaded', function () {
    toastEditor = new toastui.Editor({
        el: document.getElementById('markdown-editor'),
        height: '350px',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: '',
        usageStatistics: false
    });
});

const modal = document.getElementById('topicModal');
const modalContainer = document.getElementById('modalContainer');

function openModal() {
    document.getElementById('topicForm').reset();
    document.getElementById('topic_id').value = '';
    document.getElementById('modalTitle').innerText = 'Tambah Topic Baru';
    
    if (toastEditor) {
        toastEditor.setMarkdown('');
    }

    modal.classList.remove('hidden');
    setTimeout(() => {
        modalContainer.classList.remove('scale-95', 'opacity-0');
        modalContainer.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeModal() {
    modalContainer.classList.remove('scale-100', 'opacity-100');
    modalContainer.classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 200);
}

function saveData(e) {
    e.preventDefault();

    // Salin data Markdown dari Toast UI ke textarea hidden
    if (toastEditor) {
        document.getElementById('content_markdown').value = toastEditor.getMarkdown();
    }

    const formData = new FormData(e.target);

    fetch('<?= admin_url("topics.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            showToast(res.message);
            closeModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    });
}

function editData(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('<?= admin_url("topics.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            const d = res.data;
            document.getElementById('topic_id').value = d.id;
            document.getElementById('module_id').value = d.module_id;
            document.getElementById('title').value = d.title;
            document.getElementById('slug').value = d.slug;
            document.getElementById('estimated_read_time').value = d.estimated_read_time;
            document.getElementById('summary_tldr').value = d.summary_tldr || '';
            
            // Set konten ke Toast UI Editor
            if (toastEditor) {
                toastEditor.setMarkdown(d.content_markdown || '');
            }

            document.getElementById('modalTitle').innerText = 'Edit Topic & Content';

            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContainer.classList.remove('scale-95', 'opacity-0');
                modalContainer.classList.add('scale-100', 'opacity-100');
            }, 10);
        }
    });
}

function deleteData(id) {
    if(!confirm('Hapus sub-bab topic ini secara permanen?')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('<?= admin_url("topics.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            showToast(res.message);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/views/layout_footer.php'; ?>