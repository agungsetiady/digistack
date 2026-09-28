<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

// --- COVER IMAGE HELPERS ---
const COVER_DIR      = __DIR__ . '/../assets/img/';   // root/assets/img/
const COVER_MAX_SIZE = 2 * 1024 * 1024;               // 2 MB

// "Pengantar Android" -> "pengantar_android"
function cover_basename(string $title): string {
    $t = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($title)));
    $t = trim($t, '_');
    return substr($t !== '' ? $t : 'course', 0, 100);
}

// Validasi file upload, kembalikan ekstensi (jpg/png/webp)
function cover_validate_upload(array $f): string {
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar gagal (kode error ' . $f['error'] . ').');
    }
    if ($f['size'] > COVER_MAX_SIZE) {
        throw new RuntimeException('Ukuran gambar maksimal 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime]) || !@getimagesize($f['tmp_name'])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }
    return $map[$mime];
}

// Apakah file cover ini dipakai course lain?
function cover_used_by_others(PDO $pdo, string $name, int $exceptId): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE cover_image = ? AND id <> ?");
    $s->execute([$name, $exceptId]);
    return $s->fetchColumn() > 0;
}

// Nama file unik; kalau bentrok dengan course lain, tambahkan suffix _{id}
function cover_unique_name(PDO $pdo, string $base, string $ext, int $id): string {
    $name = "$base.$ext";
    return cover_used_by_others($pdo, $name, $id) ? "{$base}_{$id}.$ext" : $name;
}

// Hapus file cover dari disk (kalau tidak dipakai course lain)
function cover_delete(PDO $pdo, ?string $name, int $exceptId): void {
    if (!$name) return;
    $name = basename($name);
    if (cover_used_by_others($pdo, $name, $exceptId)) return;
    $path = COVER_DIR . $name;
    if (is_file($path)) @unlink($path);
}

// --- AJAX HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'save') {
            $id           = $_POST['id'] ?? '';
            $title        = trim($_POST['title']);
            $slug         = trim($_POST['slug']) ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description  = trim($_POST['description']);
            $icon         = trim($_POST['icon']) ?: 'book';
            $is_published = isset($_POST['is_published']) ? 1 : 0;

            // Validasi upload cover DULU, sebelum ada perubahan di DB
            $hasUpload = isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE;
            $ext = $hasUpload ? cover_validate_upload($_FILES['cover_image']) : null;
            if ($hasUpload) {
                if (!is_dir(COVER_DIR) && !mkdir(COVER_DIR, 0755, true)) {
                    throw new RuntimeException('Folder assets/img tidak bisa dibuat.');
                }
                if (!is_writable(COVER_DIR)) {
                    throw new RuntimeException('Folder assets/img tidak writable.');
                }
            }

            $oldCover = null;
            if ($id) {
                $id = (int)$id;
                $q = $pdo->prepare("SELECT cover_image FROM courses WHERE id = ?");
                $q->execute([$id]);
                $oldCover = $q->fetchColumn() ?: null;

                $stmt = $pdo->prepare("UPDATE courses SET title = ?, slug = ?, description = ?, icon = ?, is_published = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $description, $icon, $is_published, $id]);
                $message = 'Course berhasil diperbarui';
            } else {
                $stmt = $pdo->prepare("INSERT INTO courses (title, slug, description, icon, is_published) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $description, $icon, $is_published]);
                $id = (int)$pdo->lastInsertId();
                $message = 'Course baru berhasil ditambahkan';
            }

            // --- Proses cover ---
            $base     = cover_basename($title);
            $newCover = $oldCover;

            if ($hasUpload) {
                $newCover = cover_unique_name($pdo, $base, $ext, $id);
                if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], COVER_DIR . $newCover)) {
                    throw new RuntimeException('Gagal menyimpan file gambar ke assets/img.');
                }
            } elseif ($oldCover && !cover_used_by_others($pdo, $oldCover, $id)) {
                // Tanpa upload baru: kalau judul berubah, nama file ikut disesuaikan
                $oldExt = strtolower(pathinfo($oldCover, PATHINFO_EXTENSION));
                $target = cover_unique_name($pdo, $base, $oldExt, $id);
                if ($target !== $oldCover
                    && is_file(COVER_DIR . $oldCover)
                    && !file_exists(COVER_DIR . $target)
                    && rename(COVER_DIR . $oldCover, COVER_DIR . $target)) {
                    $newCover = $target;
                }
            }

            if ($newCover !== $oldCover) {
                $u = $pdo->prepare("UPDATE courses SET cover_image = ? WHERE id = ?");
                $u->execute([$newCover, $id]);
                cover_delete($pdo, $oldCover, $id); // hapus file lama yang sudah tidak terpakai
            }

            echo json_encode(['status' => 'success', 'message' => $message]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $q = $pdo->prepare("SELECT cover_image FROM courses WHERE id = ?");
            $q->execute([$id]);
            $oldCover = $q->fetchColumn() ?: null;

            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            cover_delete($pdo, $oldCover, $id);
            echo json_encode(['status' => 'success', 'message' => 'Course berhasil dihapus']);
            exit;
        }

        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
            exit;
        }
    } catch (\RuntimeException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// --- DATA FETCHING & PAGINATION ---
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// 1. Hitung total data
$whereClause = $search ? "WHERE title LIKE ? OR description LIKE ?" : "";
$countStmt   = $pdo->prepare("SELECT COUNT(*) FROM courses $whereClause");

if ($search) {
    $searchTerm = "%$search%";
    $countStmt->execute([$searchTerm, $searchTerm]);
} else {
    $countStmt->execute();
}

$totalRows  = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// 2. Fetch data courses dengan limit & offset
$query = "SELECT c.*, (SELECT COUNT(*) FROM modules WHERE course_id = c.id) as total_modules 
          FROM courses c $whereClause ORDER BY c.id DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);

// Atur urutan parameter binding
$paramIndex = 1;

if ($search) {
    $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
    $stmt->bindValue($paramIndex++, "%$search%", PDO::PARAM_STR);
}

$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

$stmt->execute();
$courses = $stmt->fetchAll();

require_once __DIR__ . '/views/layout_header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Manajemen Courses</h1>
        <p class="text-xs text-slate-500 mt-0.5">Kelola direktori kelas e-course utama Anda</p>
    </div>
    <button onclick="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
        <i class='bx bx-plus text-base'></i>
        <span>Tambah Course</span>
    </button>
</div>

<!-- Search Bar -->
<div class="bg-white border border-slate-200/80 rounded-2xl p-4 mb-6 shadow-sm">
    <form action="./courses" method="GET" class="relative flex items-center">
        <i class='bx bx-search absolute left-3.5 text-slate-400 text-lg'></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari judul course atau deskripsi..." class="w-full pl-10 <?= $search !== '' ? 'pr-28' : 'pr-20' ?> py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
        
        <div class="absolute right-1.5 flex items-center gap-1">
            <?php if ($search !== ''): ?>
                <a href="./courses" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-slate-200/60 rounded-lg transition-all" title="Reset pencarian">
                    <i class='bx bx-x text-lg block'></i>
                </a>
            <?php endif; ?>
            <button type="submit" class="px-3 py-1 bg-slate-900 text-white text-[11px] font-semibold rounded-lg hover:bg-slate-800 transition-all">Cari</button>
        </div>
    </form>
</div>

<!-- Table Data -->
<div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="py-3.5 px-5">Course Title</th>
                    <th class="py-3.5 px-5">Modules</th>
                    <th class="py-3.5 px-5">Status</th>
                    <th class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-400">Belum ada data course ditemukan.</td>
                    </tr>
                <?php else: foreach ($courses as $c): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg flex-shrink-0">
                                    <i class='bx bx-<?= htmlspecialchars($c['icon']) ?>'></i>
                                </div>
                                <div>
                                    <span class="font-semibold text-slate-900 block"><?= htmlspecialchars($c['title']) ?></span>
                                    <span class="text-[11px] text-slate-400 font-mono">/<?= htmlspecialchars($c['slug']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1 font-medium bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg text-[11px]">
                                <i class='bx bx-layer'></i> <?= $c['total_modules'] ?> Bab
                            </span>
                        </td>
                        <td class="py-3.5 px-5">
                            <?php if ($c['is_published']): ?>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-[10px] font-semibold tracking-wide">PUBLISHED</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-600 rounded-lg text-[10px] font-semibold tracking-wide">DRAFT</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button onclick="editData(<?= $c['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition-all">
                                    <i class='bx bx-edit-alt text-base'></i>
                                </button>
                                <button onclick="deleteData(<?= $c['id'] ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 flex items-center justify-center transition-all">
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
                        $url = './courses?' . http_build_query($queryParams);
                    ?>
                    <a href="<?= $url ?>" class="w-7 h-7 flex items-center justify-center rounded-lg font-medium transition-all <?= $i === $page ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'hover:bg-slate-100 text-slate-600' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Form -->
<div id="courseModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] overflow-y-auto p-6 shadow-2xl transform transition-all scale-95 opacity-0 duration-200" id="modalContainer">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-900" id="modalTitle">Tambah Course</h3>
            <button onclick="closeModal()" class="w-7 h-7 text-slate-400 hover:text-slate-600 flex items-center justify-center rounded-lg"><i class='bx bx-x text-xl'></i></button>
        </div>
        <form id="courseForm" onsubmit="saveData(event)" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="course_id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Cover Course</label>
                <div class="flex items-start gap-3">
                    <div class="w-48 h-28 rounded-xl border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                        <i id="coverPlaceholder" class='bx bx-image text-2xl text-slate-300'></i>
                        <img id="coverPreview" src="" alt="Preview cover" class="hidden w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <input type="file" name="cover_image" id="cover_image" accept="image/png,image/jpeg,image/webp" class="hidden">
                        <button type="button" onclick="document.getElementById('cover_image').click()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-lg transition-all">
                            <i class='bx bx-upload text-sm'></i> Upload Gambar
                        </button>
                        <p class="text-[11px] text-slate-400 mt-1.5">JPG, PNG, atau WEBP. Maks 2 MB.</p>
                        <p id="coverFilename" class="text-[11px] font-mono text-slate-500 mt-0.5 truncate"></p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Judul Course</label>
                <input type="text" name="title" id="title" required placeholder="misal: Laravel Web Development" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Slug URL (Opsional)</label>
                <input type="text" name="slug" id="slug" placeholder="laravel-web-development" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Icon
                    </label>

                    <select
                        name="icon"
                        id="icon"
                        class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                    >
                        <option value="book-bookmark">📖 Book / Bookmark</option>
                        <option value="book">📕 Book</option>
                        <option value="book-open">📖 Open Book</option>
                        <option value="graduation">🎓 Education</option>
                        <option value="pencil">✏️ Writing</option>
                        <option value="edit">📝 Editing</option>
                        <option value="file">📄 Document</option>
                        <option value="file-text">📄 Text / Article</option>
                        <option value="notepad">🗒️ Notes</option>
                        <option value="brain">🧠 Knowledge</option>
                        <option value="bulb">💡 Idea</option>
                        <option value="code">💻 Programming</option>
                        <option value="terminal">⌨️ Code / Terminal</option>
                        <option value="globe">🌐 Web</option>
                        <option value="search">🔍 Search</option>
                        <option value="check-circle">✅ Completed</option>
                        <option value="task">📋 Task</option>
                        <option value="list-check">☑️ Checklist</option>
                        <option value="star">⭐ Featured</option>
                        <option value="bookmark">🔖 Bookmark</option>
                        <option value="folder">📁 Folder</option>
                        <option value="image">🖼️ Image</option>
                        <option value="video">🎬 Video</option>
                        <option value="headphone">🎧 Audio</option>
                        <option value="game">🎮 Game</option>
                        <option value="trophy">🏆 Achievement</option>
                        <option value="target">🎯 Goal</option>
                        <option value="rocket">🚀 Project</option>
                        <option value="settings">⚙️ Settings</option>
                        <option value="info-circle">ℹ️ Information</option>
                        <option value="help-circle">❓ Help</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Status Publish</label>
                    <label class="mt-2 flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_published" id="is_published" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                        <span class="text-xs text-slate-600 font-medium">Publish Kelas</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Deskripsi Singkat</label>
                <textarea name="description" id="description" rows="5" placeholder="Penjelasan singkat mengenai kelas ini..." class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
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
    updateCoverFilename();
});

// --- COVER IMAGE ---
const COVER_BASE       = '../assets/img/';
const COVER_MAX_SIZE   = 2 * 1024 * 1024;
const COVER_EXT        = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
const coverInput       = document.getElementById('cover_image');
const coverPreview     = document.getElementById('coverPreview');
const coverPlaceholder = document.getElementById('coverPlaceholder');
const coverFilename    = document.getElementById('coverFilename');
let savedCover = '';      // nama file cover yang tersimpan di DB (mode edit)
let blobUrl    = null;    // object URL preview file yang baru dipilih

// "Pengantar Android" -> "pengantar_android" (sama dengan logika di PHP)
function coverBasename(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 100) || 'course';
}

function showCoverPreview(src) {
    if (src) {
        coverPreview.src = src;
        coverPreview.classList.remove('hidden');
        coverPlaceholder.classList.add('hidden');
    } else {
        coverPreview.removeAttribute('src');
        coverPreview.classList.add('hidden');
        coverPlaceholder.classList.remove('hidden');
    }
}

function updateCoverFilename() {
    const file  = coverInput.files[0];
    const title = document.getElementById('title').value;
    let ext = '';
    if (file) ext = COVER_EXT[file.type] || '';
    else if (savedCover) ext = savedCover.split('.').pop();
    coverFilename.textContent = (ext && title.trim()) ? 'Disimpan sebagai: ' + coverBasename(title) + '.' + ext : '';
}

function resetCover(saved) {
    coverInput.value = '';
    if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
    savedCover = saved || '';
    showCoverPreview(savedCover ? COVER_BASE + encodeURIComponent(savedCover) + '?t=' + Date.now() : '');
    updateCoverFilename();
}

coverInput.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) { resetCover(savedCover); return; }

    if (!COVER_EXT[file.type]) {
        showToast('Format gambar harus JPG, PNG, atau WEBP.', 'error');
        resetCover(savedCover);
        return;
    }
    if (file.size > COVER_MAX_SIZE) {
        showToast('Ukuran gambar maksimal 2 MB.', 'error');
        resetCover(savedCover);
        return;
    }

    if (blobUrl) URL.revokeObjectURL(blobUrl);
    blobUrl = URL.createObjectURL(file);
    showCoverPreview(blobUrl);   // preview sebelum submit
    updateCoverFilename();
});

const modal = document.getElementById('courseModal');
const modalContainer = document.getElementById('modalContainer');

function openModal() {
    document.getElementById('courseForm').reset();
    document.getElementById('course_id').value = '';
    resetCover('');
    document.getElementById('modalTitle').innerText = 'Tambah Course Baru';
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

    fetch('<?= admin_url("courses.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            showToast(res.message);
            closeModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    })
    .catch(() => showToast('Gagal menyimpan. Periksa ukuran file (batas upload_max_filesize / post_max_size di php.ini).', 'error'));
}

function editData(id) {
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);

    fetch('<?= admin_url("courses.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            const d = res.data;
            document.getElementById('course_id').value = d.id;
            document.getElementById('title').value = d.title;
            document.getElementById('slug').value = d.slug;
            document.getElementById('icon').value = d.icon;
            document.getElementById('description').value = d.description;
            document.getElementById('is_published').checked = d.is_published == 1;
            document.getElementById('modalTitle').innerText = 'Edit Course';
            resetCover(d.cover_image);

            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContainer.classList.remove('scale-95', 'opacity-0');
                modalContainer.classList.add('scale-100', 'opacity-100');
            }, 10);
        }
    });
}

function deleteData(id) {
    if(!confirm('Apakah Anda yakin ingin menghapus Course ini? Semua Bab & Sub-bab di dalamnya akan ikut terhapus.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('<?= admin_url("courses.php") ?>', { method: 'POST', body: formData })
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