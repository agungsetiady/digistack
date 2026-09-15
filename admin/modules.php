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
            $id             = $_POST['id'] ?? '';
            $course_id      = $_POST['course_id'];
            $title          = trim($_POST['title']);
            $slug           = trim($_POST['slug']) ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description    = trim($_POST['description']);
            $order_position = (int)($_POST['order_position'] ?? 0);

            if ($id) {
                $stmt = $pdo->prepare("UPDATE modules SET course_id = ?, title = ?, slug = ?, description = ?, order_position = ? WHERE id = ?");
                $stmt->execute([$course_id, $title, $slug, $description, $order_position, $id]);
                echo json_encode(['status' => 'success', 'message' => 'Module (Bab) berhasil diperbarui']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO modules (course_id, title, slug, description, order_position) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$course_id, $title, $slug, $description, $order_position]);
                echo json_encode(['status' => 'success', 'message' => 'Module (Bab) berhasil ditambahkan']);
            }
            exit;
        }

        if ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Module berhasil dihapus']);
            exit;
        }

        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
            exit;
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch Courses untuk Dropdown Select
$all_courses = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC")->fetchAll();

// Fetch Data Modules dengan Filter & Pagination
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Gunakan named parameters unik (:search1 dan :search2)
$whereClause = $search ? "WHERE m.title LIKE :search1 OR c.title LIKE :search2" : "";

// Count Query
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM modules m JOIN courses c ON m.course_id = c.id $whereClause");
if ($search) {
    $countStmt->bindValue(':search1', "%$search%");
    $countStmt->bindValue(':search2', "%$search%");
}
$countStmt->execute();

$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Main Query (Semua parameter menggunakan Named Parameter)
$query = "SELECT m.*, c.title as course_title, (SELECT COUNT(*) FROM topics WHERE module_id = m.id) as total_topics 
          FROM modules m JOIN courses c ON m.course_id = c.id $whereClause 
          ORDER BY m.course_id DESC, m.order_position DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

// Bind parameter pencarian jika ada
if ($search) {
    $stmt->bindValue(':search1', "%$search%");
    $stmt->bindValue(':search2', "%$search%");
}

// Bind parameter pagination
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$modules = $stmt->fetchAll();

require_once __DIR__ . '/views/layout_header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Manajemen Modules (Bab)</h1>
        <p class="text-xs text-slate-500 mt-0.5">Organisasikan bab kurikulum berdasarkan kelas terkait</p>
    </div>
    <button onclick="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
        <i class='bx bx-plus text-base'></i>
        <span>Tambah Module</span>
    </button>
</div>

<!-- Search Bar -->
<div class="bg-white border border-slate-200/80 rounded-2xl p-4 mb-6 shadow-sm">
    <form action="./modules" method="GET" class="relative flex items-center">
        <i class='bx bx-search absolute left-3.5 text-slate-400 text-lg'></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul bab atau nama course..." class="w-full pl-10 <?= $search !== '' ? 'pr-28' : 'pr-20' ?> py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
        
        <div class="absolute right-1.5 flex items-center gap-1">
            <?php if ($search !== ''): ?>
                <a href="./modules" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-slate-200/60 rounded-lg transition-all" title="Reset pencarian">
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
                    <th class="py-3.5 px-5">Urutan</th>
                    <th class="py-3.5 px-5">Module / Bab</th>
                    <th class="py-3.5 px-5">Induk Course</th>
                    <th class="py-3.5 px-5">Topics</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                <?php if (empty($modules)): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-400">Belum ada module / bab ditemukan.</td>
                    </tr>
                <?php else: foreach ($modules as $m): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5 font-mono text-slate-400 font-semibold">#<?= $m['order_position'] ?></td>
                        <td class="py-3.5 px-5">
                            <span class="font-semibold text-slate-900 block"><?= htmlspecialchars($m['title']) ?></span>
                            <span class="text-[11px] text-slate-400 font-mono">/<?= htmlspecialchars($m['slug']) ?></span>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[11px] font-medium">
                                <?= htmlspecialchars($m['course_title']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md text-[11px]">
                                <?= $m['total_topics'] ?> Materi
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick="editData(<?= $m['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition-all">
                                    <i class='bx bx-edit-alt text-base'></i>
                                </button>
                                <button onclick="deleteData(<?= $m['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 flex items-center justify-center transition-all">
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
                        $url = './modules?' . http_build_query($queryParams);
                    ?>
                    <a href="<?= $url ?>" class="w-7 h-7 flex items-center justify-center rounded-lg font-medium transition-all <?= $i === $page ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'hover:bg-slate-100 text-slate-600' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Form -->
<div id="moduleModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl transform transition-all scale-95 opacity-0 duration-200" id="modalContainer">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-900" id="modalTitle">Tambah Module Baru</h3>
            <button onclick="closeModal()" class="w-7 h-7 text-slate-400 hover:text-slate-600 flex items-center justify-center rounded-lg"><i class='bx bx-x text-xl'></i></button>
        </div>
        <form id="moduleForm" onsubmit="saveData(event)" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="module_id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Course Induk</label>
                <select name="course_id" id="course_id" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    <option value="">-- Pilih Course --</option>
                    <?php foreach ($all_courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Judul Module (Bab)</label>
                <input type="text" name="title" id="title" required placeholder="misal: Konsep Dasar Controller & Routing" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Slug URL</label>
                    <input type="text" name="slug" id="slug" placeholder="konsep-dasar-controller" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Urutan Bab</label>
                    <input type="number" name="order_position" id="order_position" value="1" min="1" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Deskripsi Bab</label>
                <textarea name="description" id="description" rows="5" placeholder="Garis besar pembahasan dalam bab ini..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">Batal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-600/30 transition-all">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/views/toast.php'; ?>

<script>
// Helper fungsi untuk mengubah teks menjadi slug url-friendly
function generateSlug(text) {
    return text.toString().toLowerCase().trim()
        .replace(/\s+/g, '-')           // Ganti spasi dengan -
        .replace(/[^\w\-]+/g, '')       // Hapus semua karakter non-word
        .replace(/\-\-+/g, '-')         // Ganti ganda - dengan single -
        .replace(/^-+/, '')             // Hapus - di awal
        .replace(/-+$/, '');            // Hapus - di akhir
}

// Otomatis isi slug saat user mengetik judul
document.getElementById('title').addEventListener('input', function() {
    document.getElementById('slug').value = generateSlug(this.value);
});

const modal = document.getElementById('moduleModal');
const modalContainer = document.getElementById('modalContainer');

function openModal() {
    document.getElementById('moduleForm').reset();
    document.getElementById('module_id').value = '';
    document.getElementById('modalTitle').innerText = 'Tambah Module Baru';
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
    const formData = new FormData(e.target);

    fetch('<?= admin_url("modules.php") ?>', { method: 'POST', body: formData })
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

    fetch('<?= admin_url("modules.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            const d = res.data;
            document.getElementById('module_id').value = d.id;
            document.getElementById('course_id').value = d.course_id;
            document.getElementById('title').value = d.title;
            document.getElementById('slug').value = d.slug;
            document.getElementById('order_position').value = d.order_position;
            document.getElementById('description').value = d.description;
            document.getElementById('modalTitle').innerText = 'Edit Module';

            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContainer.classList.remove('scale-95', 'opacity-0');
                modalContainer.classList.add('scale-100', 'opacity-100');
            }, 10);
        }
    });
}

function deleteData(id) {
    if(!confirm('Hapus module ini beserta semua sub-bab (topics) di dalamnya?')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('<?= admin_url("modules.php") ?>', { method: 'POST', body: formData })
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