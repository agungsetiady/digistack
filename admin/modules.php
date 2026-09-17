<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

// --- HELPER: AI CHAPTER GENERATION ---

/**
 * Bersihkan title (misal "Bab 1. Apa Itu SEO") menjadi slug bersih tanpa angka.
 * Hasil: "apa-itu-seo"
 */
function generate_clean_slug($title)
{
    $t = preg_replace('/^\s*(bab|chapter|bagian)\s*\d+\s*[\.\:\-]?\s*/i', '', $title);
    $t = preg_replace('/^\s*\d+(\.\d+)*\s*[\.\:\-]?\s*/', '', $t);
    $t = preg_replace('/[0-9]+/', '', $t);
    $t = strtolower(trim($t));
    $t = preg_replace('/[^a-z\s-]/', '', $t);
    $t = preg_replace('/\s+/', '-', trim($t));
    $t = preg_replace('/-+/', '-', $t);
    return trim($t, '-');
}

/**
 * Ambil nomor urut bab dari title (misal "Bab 3. ..." => 3).
 * Jika tidak ditemukan, gunakan $fallback (index berurutan).
 */
function extract_order_position($title, $fallback)
{
    if (preg_match('/(?:bab|chapter|bagian)\s*(\d+)/i', $title, $m)) {
        return (int)$m[1];
    }
    return (int)$fallback;
}

/**
 * Buang penomoran yang dibuat AI di awal judul topic (misal "1.2 ", "1.2: ", "2.3 - ")
 * supaya sistem bisa memberi penomoran ulang yang konsisten.
 */
function strip_leading_numbering($title)
{
    $t = preg_replace('/^\s*(bab|chapter|bagian)\s*\d+\s*[\.\:\-]?\s*/i', '', $title);
    $t = preg_replace('/^\s*\d+(\.\d+)*\s*[\.\:\)\-]?\s*/', '', $t);
    return trim($t);
}

/**
 * Normalisasi estimasi waktu baca (menit) agar tetap masuk akal.
 */
function normalize_read_time($value, $default = 5)
{
    $v = (int)preg_replace('/[^0-9]/', '', (string)$value);
    if ($v < 1)  return $default;
    if ($v > 90) return 90;
    return $v;
}

/**
 * Ekstrak JSON array dari teks balasan AI (jaga-jaga jika AI membungkus
 * jawabannya dengan ```json ... ``` atau menambahkan kalimat pengantar).
 */
function extract_json_array($text)
{
    $text = trim($text);
    $text = preg_replace('/^```(json)?/i', '', $text);
    $text = preg_replace('/```$/', '', $text);
    $text = trim($text);

    $start = strpos($text, '[');
    if ($start === false) {
        return null;
    }

    $end = strrpos($text, ']');
    if ($end !== false && $end > $start) {
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    // Fallback: respons AI terpotong di tengah jalan (max_tokens habis).
    // Potong sampai objek terakhir yang masih utuh lalu tutup array-nya.
    $partial  = substr($text, $start);
    $lastBrace = strrpos($partial, '}');
    while ($lastBrace !== false) {
        $candidate = substr($partial, 0, $lastBrace + 1) . ']';
        $decoded   = json_decode($candidate, true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
        $lastBrace = strrpos(substr($partial, 0, $lastBrace), '}');
    }

    return null;
}

/**
 * Panggil provider AI secara dinamis berdasarkan konfigurasi di tabel
 * ai_providers & ai_models (base_url, api_key, header, request_template
 * semuanya diambil dari DB, tidak hardcode).
 */
function call_ai_provider(PDO $pdo, $model_id, $prompt)
{
    $stmt = $pdo->prepare("SELECT am.model_code, am.max_tokens, am.temperature,
            ap.base_url, ap.api_key, ap.header_key, ap.header_prefix, ap.request_template, ap.additional_headers
        FROM ai_models am
        JOIN ai_providers ap ON ap.id = am.provider_id
        WHERE am.id = ? AND am.is_active = 1 AND ap.is_active = 1");
    $stmt->execute([$model_id]);
    $cfg = $stmt->fetch();

    if (!$cfg) {
        throw new Exception('Model AI tidak ditemukan atau sedang tidak aktif. Silakan pilih model lain.');
    }

    // Escape prompt & model code dengan aman agar tetap valid saat disisipkan ke JSON template
    $promptEscaped = substr(json_encode($prompt), 1, -1);
    $modelEscaped  = substr(json_encode($cfg['model_code']), 1, -1);

    $body = str_replace(
        ['{model}', '{prompt}', '{temperature}', '{max_tokens}'],
        [$modelEscaped, $promptEscaped, $cfg['temperature'], $cfg['max_tokens']],
        $cfg['request_template']
    );

    json_decode($body);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('request_template pada provider AI ini tidak valid (JSON error).');
    }

    $headers   = ['Content-Type: application/json'];
    $headers[] = $cfg['header_key'] . ': ' . $cfg['header_prefix'] . $cfg['api_key'];

    if (!empty($cfg['additional_headers'])) {
        $extra = json_decode($cfg['additional_headers'], true);
        if (is_array($extra)) {
            foreach ($extra as $k => $v) {
                $headers[] = "$k: $v";
            }
        }
    }

    $ch = curl_init($cfg['base_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Gagal menghubungi provider AI: ' . $curlErr);
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Provider AI mengembalikan error (HTTP $httpCode): " . substr(strip_tags($response), 0, 300));
    }

    $data    = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? null;

    if ($content === null) {
        throw new Exception('Format respons dari provider AI tidak dikenali. Periksa kembali konfigurasi provider di menu AI Providers.');
    }

    return $content;
}

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

        // --- AI GENERATE: hasilkan draft Bab + Topic berdasarkan course terpilih ---
        if ($action === 'ai_generate') {
            try {
                $course_id    = (int)($_POST['course_id'] ?? 0);
                $model_id     = (int)($_POST['model_id'] ?? 0);
                $jumlah_bab   = trim($_POST['jumlah_bab'] ?? '');
                $jumlah_topic = trim($_POST['jumlah_topic'] ?? '');
                $jumlah_bab   = ($jumlah_bab !== '' && (int)$jumlah_bab > 0) ? (int)$jumlah_bab : null;
                $jumlah_topic = ($jumlah_topic !== '' && (int)$jumlah_topic > 0) ? (int)$jumlah_topic : null;

                if (!$course_id || !$model_id) {
                    echo json_encode(['status' => 'error', 'message' => 'Course dan Model AI wajib dipilih.']);
                    exit;
                }

                // Pastikan course valid & benar-benar belum punya module (cegah race condition)
                $stmt = $pdo->prepare("SELECT c.id, c.title, c.description FROM courses c LEFT JOIN modules m ON m.course_id = c.id WHERE c.id = ? AND m.id IS NULL");
                $stmt->execute([$course_id]);
                $course = $stmt->fetch();

                if (!$course) {
                    echo json_encode(['status' => 'error', 'message' => 'Course tidak valid atau ternyata sudah memiliki module. Silakan refresh halaman.']);
                    exit;
                }

                $courseDesc = trim(strip_tags($course['description'] ?? ''));
                if (mb_strlen($courseDesc) > 1200) {
                    $courseDesc = mb_substr($courseDesc, 0, 1200) . '...';
                }
                if ($courseDesc === '') {
                    $courseDesc = '(Tidak ada deskripsi tambahan, gunakan judul course sebagai acuan utama)';
                }

                $babInstruction = $jumlah_bab
                    ? "Buat tepat {$jumlah_bab} bab."
                    : "Tentukan sendiri jumlah bab yang paling ideal (umumnya 5 sampai 10 bab) sesuai cakupan materi.";

                $topicInstruction = $jumlah_topic
                    ? "Setiap bab HARUS memiliki tepat {$jumlah_topic} topic."
                    : "Setiap bab memiliki 3 sampai 5 topic, sesuaikan dengan luas pembahasan bab tersebut.";

                $prompt = "Kamu adalah asisten penyusun kurikulum e-learning yang ahli.\n\n"
                    . "Judul Course: {$course['title']}\n"
                    . "Deskripsi Course: {$courseDesc}\n\n"
                    . "Susun struktur kurikulum lengkap yang runtut dan logis, dari dasar hingga mahir, untuk course tersebut. "
                    . "{$babInstruction} {$topicInstruction}\n\n"
                    . "Struktur setiap BAB:\n"
                    . "- \"title\": format \"Bab {nomor}. {Judul Singkat Bab}\" (nomor mulai dari 1, berurutan, tidak melompat)\n"
                    . "- \"description\": 1 paragraf singkat (2-4 kalimat) berbahasa Indonesia berisi garis besar materi bab tersebut\n"
                    . "- \"topics\": array sub-bab di dalam bab itu\n\n"
                    . "Struktur setiap TOPIC di dalam \"topics\":\n"
                    . "- \"title\": format \"{nomor_bab}.{nomor_topic} {Judul Topic}\" (contoh: \"1.1 Apa Itu Laravel\", \"1.2 Kapan Harus Menggunakan Laravel\")\n"
                    . "- \"read_time\": perkiraan waktu baca materi topic tersebut dalam MENIT, berupa angka bulat antara 3 sampai 15\n\n"
                    . "PENTING: Balas HANYA dengan JSON array yang valid, tanpa teks pembuka/penutup, tanpa markdown code block. Contoh format persis:\n"
                    . "[{\"title\":\"Bab 1. Judul Bab\",\"description\":\"Penjelasan singkat...\",\"topics\":[{\"title\":\"1.1 Judul Topic\",\"read_time\":7},{\"title\":\"1.2 Judul Topic\",\"read_time\":5}]},"
                    . "{\"title\":\"Bab 2. Judul Bab\",\"description\":\"Penjelasan singkat...\",\"topics\":[{\"title\":\"2.1 Judul Topic\",\"read_time\":6}]}]";

                $rawContent = call_ai_provider($pdo, $model_id, $prompt);
                $chapters   = extract_json_array($rawContent);

                if (!is_array($chapters) || empty($chapters)) {
                    throw new Exception('AI tidak mengembalikan format yang valid. Silakan coba generate ulang atau gunakan model AI lain.');
                }

                $result      = [];
                $babNo       = 1;
                $totalTopics = 0;

                foreach ($chapters as $ch) {
                    $title = trim($ch['title'] ?? '');
                    $desc  = trim($ch['description'] ?? '');
                    if ($title === '') continue;

                    $order = extract_order_position($title, $babNo);

                    // Susun ulang topic dengan penomoran konsisten: {bab}.{urutan}
                    $topics   = [];
                    $rawTopics = $ch['topics'] ?? [];
                    if (is_array($rawTopics)) {
                        $topicNo = 1;
                        foreach ($rawTopics as $tp) {
                            $tpTitle = is_array($tp) ? trim($tp['title'] ?? '') : trim((string)$tp);
                            if ($tpTitle === '') continue;

                            $cleanTitle = strip_leading_numbering($tpTitle);
                            if ($cleanTitle === '') continue;

                            $topics[] = [
                                'title'               => $order . '.' . $topicNo . ' ' . $cleanTitle,
                                'slug'                => generate_clean_slug($cleanTitle),
                                'order_position'      => $topicNo,
                                'estimated_read_time' => normalize_read_time(is_array($tp) ? ($tp['read_time'] ?? null) : null),
                            ];
                            $topicNo++;
                        }
                    }

                    $totalTopics += count($topics);

                    $result[] = [
                        'title'          => $title,
                        'slug'           => generate_clean_slug($title),
                        'description'    => $desc,
                        'order_position' => $order,
                        'topics'         => $topics,
                    ];
                    $babNo++;
                }

                if (empty($result)) {
                    throw new Exception('AI tidak mengembalikan bab yang valid. Silakan coba generate ulang.');
                }

                echo json_encode([
                    'status'       => 'success',
                    'data'         => $result,
                    'total_topics' => $totalTopics,
                    'course'       => ['id' => $course['id'], 'title' => $course['title']],
                ]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // --- AI BULK SAVE: simpan Bab + Topic hasil generate AI (setelah direview admin) ---
        if ($action === 'ai_bulk_save') {
            try {
                $course_id    = (int)($_POST['course_id'] ?? 0);
                $chaptersJson = $_POST['chapters'] ?? '[]';
                $chapters     = json_decode($chaptersJson, true);

                if (!$course_id || !is_array($chapters) || empty($chapters)) {
                    echo json_encode(['status' => 'error', 'message' => 'Data bab tidak valid.']);
                    exit;
                }

                // Safety check: pastikan course belum punya module (hindari duplikasi)
                $check = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE course_id = ?");
                $check->execute([$course_id]);
                if ($check->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Course ini sudah memiliki module. Silakan refresh halaman.']);
                    exit;
                }

                $pdo->beginTransaction();

                $stmtModule = $pdo->prepare("INSERT INTO modules (course_id, title, slug, description, order_position) VALUES (?, ?, ?, ?, ?)");
                $stmtTopic  = $pdo->prepare("INSERT INTO topics (module_id, title, slug, order_position, estimated_read_time) VALUES (?, ?, ?, ?, ?)");

                $savedModule = 0;
                $savedTopic  = 0;

                foreach ($chapters as $ch) {
                    $title = trim($ch['title'] ?? '');
                    if ($title === '') continue;

                    $slug  = trim($ch['slug'] ?? '') ?: generate_clean_slug($title);
                    $desc  = trim($ch['description'] ?? '');
                    $order = (int)($ch['order_position'] ?? 0);

                    $stmtModule->execute([$course_id, $title, $slug, $desc, $order]);
                    $module_id = (int)$pdo->lastInsertId();
                    $savedModule++;

                    $topics = $ch['topics'] ?? [];
                    if (!is_array($topics)) continue;

                    $topicNo = 1;
                    foreach ($topics as $tp) {
                        $tpTitle = trim($tp['title'] ?? '');
                        if ($tpTitle === '') continue;

                        $tpSlug  = trim($tp['slug'] ?? '') ?: generate_clean_slug($tpTitle);
                        $tpOrder = (int)($tp['order_position'] ?? 0);
                        if ($tpOrder < 1) $tpOrder = $topicNo;
                        $tpTime  = normalize_read_time($tp['estimated_read_time'] ?? null);

                        $stmtTopic->execute([$module_id, $tpTitle, $tpSlug, $tpOrder, $tpTime]);
                        $savedTopic++;
                        $topicNo++;
                    }
                }

                $pdo->commit();
                echo json_encode([
                    'status'  => 'success',
                    'message' => "$savedModule module (Bab) & $savedTopic topic berhasil ditambahkan via AI",
                ]);
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch Courses untuk Dropdown Select
$all_courses = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC")->fetchAll();

// Fetch Courses yang BELUM punya module sama sekali (khusus combobox modal Generate AI)
$empty_courses = $pdo->query("SELECT c.id, c.title FROM courses c LEFT JOIN modules m ON m.course_id = c.id WHERE m.id IS NULL ORDER BY c.title ASC")->fetchAll();

// Fetch Model AI yang aktif (join ke provider yang juga aktif), untuk combobox modal Generate AI
$active_ai_models = $pdo->query("SELECT am.id, am.display_name, am.model_code, ap.name as provider_name
    FROM ai_models am
    JOIN ai_providers ap ON ap.id = am.provider_id
    WHERE am.is_active = 1 AND ap.is_active = 1
    ORDER BY ap.name ASC, am.display_name ASC")->fetchAll();

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
    <div class="flex items-center gap-2">
        <button onclick="openAiModal()" class="inline-flex items-center gap-2 bg-white border border-indigo-200 hover:bg-indigo-50 text-indigo-600 text-xs font-semibold py-2.5 px-4 rounded-xl shadow-sm transition-all">
            <i class='bx bx-bot text-base'></i>
            <span>Generate dengan AI</span>
        </button>
        <button onclick="openModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-indigo-600/30 transition-all">
            <i class='bx bx-plus text-base'></i>
            <span>Tambah Module</span>
        </button>
    </div>
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


<!-- Modal Generate Modules dengan AI (khusus ADD, bukan EDIT) -->
<div id="aiModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl transform transition-all scale-95 opacity-0 duration-200 max-h-[92vh] flex flex-col" id="aiModalContainer">
        <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2"><i class='bx bx-bot text-indigo-600 text-lg'></i> Generate Modules dengan AI</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Hanya untuk course yang belum memiliki module sama sekali</p>
            </div>
            <button onclick="closeAiModal()" class="w-7 h-7 text-slate-400 hover:text-slate-600 flex items-center justify-center rounded-lg"><i class='bx bx-x text-xl'></i></button>
        </div>

        <div class="px-6 py-5 overflow-y-auto">
            <!-- STEP 1: Pilih Course & Model AI -->
            <div id="aiStepSelect" class="space-y-4">
                <?php if (empty($empty_courses)): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-xl p-4 flex items-start gap-2">
                        <i class='bx bx-info-circle text-base mt-0.5'></i>
                        <span>Semua course saat ini sudah memiliki module. Fitur generate AI hanya tersedia untuk course yang modulenya masih kosong.</span>
                    </div>
                <?php else: ?>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Course (belum ada module)</label>
                        <select id="ai_course_id" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                            <option value="">-- Pilih Course --</option>
                            <?php foreach ($empty_courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Pilih Model AI</label>
                        <?php if (empty($active_ai_models)): ?>
                            <div class="bg-rose-50 border border-rose-200 text-rose-600 text-xs rounded-xl p-3">Belum ada Model AI yang aktif. Aktifkan minimal 1 model di menu AI Providers terlebih dahulu.</div>
                        <?php else: ?>
                            <select id="ai_model_id" required class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                                <option value="">-- Pilih Model AI --</option>
                                <?php
                                    $__grouped = [];
                                    foreach ($active_ai_models as $m) { $__grouped[$m['provider_name']][] = $m; }
                                ?>
                                <?php foreach ($__grouped as $providerName => $models): ?>
                                    <optgroup label="<?= htmlspecialchars($providerName) ?>">
                                        <?php foreach ($models as $m): ?>
                                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['display_name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Jumlah Bab <span class="normal-case text-slate-400 font-normal">(opsional)</span></label>
                            <input type="number" id="ai_jumlah_bab" min="1" max="30" placeholder="Otomatis" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Topic / Bab <span class="normal-case text-slate-400 font-normal">(opsional)</span></label>
                            <input type="number" id="ai_jumlah_topic" min="1" max="15" placeholder="Otomatis" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 -mt-1">Kosongkan keduanya agar AI menentukan sendiri jumlah yang paling ideal. Semakin banyak bab &times; topic, semakin lama proses generate dan semakin besar risiko respons AI terpotong.</p>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeAiModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">Batal</button>
                        <button type="button" id="aiGenerateBtn" onclick="generateWithAI()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-600/30 transition-all">
                            <i class='bx bx-bot text-base'></i>
                            <span>Generate Modules</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- STEP 2: Preview & Edit Hasil Generate sebelum Simpan -->
            <div id="aiStepPreview" class="hidden space-y-4">
                <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 text-[11px] rounded-xl p-3 flex items-start gap-2">
                    <i class='bx bx-info-circle text-sm mt-0.5'></i>
                    <span>Hasil generate AI untuk course <strong id="aiPreviewCourseTitle"></strong> &mdash; <strong id="aiPreviewCount"></strong>. Periksa &amp; edit bila perlu sebelum disimpan ke database.</span>
                </div>
                <div id="aiPreviewList" class="space-y-3"></div>
                <div class="pt-2 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="backToAiSelect()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">
                            <i class='bx bx-arrow-back'></i> Kembali
                        </button>
                        <button type="button" onclick="renumberAiPreview()" title="Rapikan ulang penomoran Bab &amp; Topic" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">
                            <i class='bx bx-sort-alt-2'></i> Rapikan Nomor
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="closeAiModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-all">Batal</button>
                        <button type="button" id="aiSaveBtn" onclick="saveAiModules()" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-emerald-600/30 transition-all">
                            <i class='bx bx-save'></i>
                            <span>Simpan Semua Module</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
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

// ============================================================
// GENERATE MODULES + TOPICS DENGAN AI
// (khusus ADD, tidak menyentuh modal manual Add/Edit di atas)
// ============================================================

const aiModal = document.getElementById('aiModal');
const aiModalContainer = document.getElementById('aiModalContainer');

let aiCurrentCourseId = null;
let aiCurrentCourseTitle = '';

// Slug bersih: buang prefix "Bab 1." / "1.2", buang semua angka, lowercase
function cleanSlugFromTitle(text) {
    return text.toString()
        .replace(/^\s*(bab|chapter|bagian)\s*\d+\s*[\.\:\-]?\s*/i, '')
        .replace(/^\s*\d+(\.\d+)*\s*[\.\:\)\-]?\s*/, '')
        .replace(/[0-9]+/g, '')
        .toLowerCase()
        .replace(/[^a-z\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Buang penomoran di awal judul, sisakan judul murninya
function stripLeadingNumbering(text) {
    return text.toString()
        .replace(/^\s*(bab|chapter|bagian)\s*\d+\s*[\.\:\-]?\s*/i, '')
        .replace(/^\s*\d+(\.\d+)*\s*[\.\:\)\-]?\s*/, '')
        .trim();
}

function escapeHtml(str) {
    return (str || '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, '&quot;');
}

function openAiModal() {
    backToAiSelect();
    ['ai_course_id', 'ai_model_id', 'ai_jumlah_bab', 'ai_jumlah_topic'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    aiModal.classList.remove('hidden');
    setTimeout(() => {
        aiModalContainer.classList.remove('scale-95', 'opacity-0');
        aiModalContainer.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeAiModal() {
    aiModalContainer.classList.remove('scale-100', 'opacity-100');
    aiModalContainer.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        aiModal.classList.add('hidden');
        backToAiSelect();
    }, 200);
}

function backToAiSelect() {
    document.getElementById('aiStepPreview').classList.add('hidden');
    document.getElementById('aiStepSelect').classList.remove('hidden');
}

function setAiBtnLoading(btnId, isLoading, loadingText, normalHtml) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    if (isLoading) {
        btn.dataset.originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('opacity-60', 'cursor-not-allowed');
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin text-base'></i><span>" + loadingText + "</span>";
    } else {
        btn.disabled = false;
        btn.classList.remove('opacity-60', 'cursor-not-allowed');
        btn.innerHTML = normalHtml || btn.dataset.originalHtml || btn.innerHTML;
    }
}

function generateWithAI() {
    const courseSel   = document.getElementById('ai_course_id');
    const modelSel    = document.getElementById('ai_model_id');
    const jumlahBab   = document.getElementById('ai_jumlah_bab');
    const jumlahTopic = document.getElementById('ai_jumlah_topic');

    if (!courseSel || !courseSel.value) {
        showToast('Silakan pilih course terlebih dahulu', 'error');
        return;
    }
    if (!modelSel || !modelSel.value) {
        showToast('Silakan pilih Model AI terlebih dahulu', 'error');
        return;
    }

    aiCurrentCourseId    = courseSel.value;
    aiCurrentCourseTitle = courseSel.options[courseSel.selectedIndex].text;

    const formData = new FormData();
    formData.append('action', 'ai_generate');
    formData.append('course_id', aiCurrentCourseId);
    formData.append('model_id', modelSel.value);
    formData.append('jumlah_bab', jumlahBab ? jumlahBab.value : '');
    formData.append('jumlah_topic', jumlahTopic ? jumlahTopic.value : '');

    setAiBtnLoading('aiGenerateBtn', true, 'AI sedang menyusun kurikulum...');

    fetch('<?= admin_url("modules.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        setAiBtnLoading('aiGenerateBtn', false, '', "<i class='bx bx-bot text-base'></i><span>Generate Modules</span>");
        if (res.status === 'success') {
            renderAiPreview(res.data, res.course);
            showToast(res.data.length + ' bab & ' + (res.total_topics || 0) + ' topic berhasil digenerate. Silakan review.');
        } else {
            showToast(res.message || 'Gagal generate modules', 'error');
        }
    })
    .catch(err => {
        setAiBtnLoading('aiGenerateBtn', false, '', "<i class='bx bx-bot text-base'></i><span>Generate Modules</span>");
        showToast('Terjadi kesalahan koneksi: ' + err.message, 'error');
    });
}

// ---------- RENDER PREVIEW ----------

function buildTopicRow(tp) {
    const row = document.createElement('div');
    row.className = 'ai-topic-item flex items-center gap-2';
    row.innerHTML = `
        <input type="number" min="1" value="${tp.order_position || 1}" title="Urutan Topic"
            class="ai-topic-order w-11 px-1.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[11px] text-center font-mono text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
        <input type="text" value="${escapeAttr(tp.title)}" placeholder="Judul Topic"
            class="ai-topic-title flex-1 min-w-0 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[11px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
        <input type="text" value="${escapeAttr(tp.slug)}" placeholder="slug-topic"
            class="ai-topic-slug w-40 shrink-0 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] font-mono text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
        <div class="flex items-center gap-1 shrink-0">
            <input type="number" min="1" max="90" value="${tp.estimated_read_time || 5}" title="Estimasi waktu baca (menit)"
                class="ai-topic-time w-12 px-1.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[11px] text-center text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            <span class="text-[10px] text-slate-400">mnt</span>
        </div>
        <button type="button" onclick="removeAiTopic(this)" title="Hapus topic ini"
            class="shrink-0 w-6 h-6 rounded-md bg-white border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-slate-400 flex items-center justify-center transition-all">
            <i class='bx bx-x text-sm'></i>
        </button>
    `;

    const titleInput = row.querySelector('.ai-topic-title');
    const slugInput  = row.querySelector('.ai-topic-slug');
    titleInput.addEventListener('input', function () {
        slugInput.value = cleanSlugFromTitle(this.value);
    });

    return row;
}

function renderAiPreview(chapters, course) {
    if (course) {
        aiCurrentCourseId    = course.id;
        aiCurrentCourseTitle = course.title;
    }
    document.getElementById('aiPreviewCourseTitle').innerText = aiCurrentCourseTitle;

    const wrap = document.getElementById('aiPreviewList');
    wrap.innerHTML = '';

    chapters.forEach(ch => {
        const card = document.createElement('div');
        card.className = 'ai-chapter-item border border-slate-200 rounded-xl p-3.5 bg-slate-50/60';
        card.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="shrink-0 mt-1">
                    <input type="number" min="1" value="${ch.order_position}" title="Urutan Bab"
                        class="ai-order w-14 px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-center font-mono text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <div class="flex-1 min-w-0 space-y-2">
                    <input type="text" value="${escapeAttr(ch.title)}" placeholder="Judul Bab"
                        class="ai-title w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] text-slate-400 font-mono">/</span>
                        <input type="text" value="${escapeAttr(ch.slug)}" placeholder="slug-bab"
                            class="ai-slug flex-1 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-[11px] font-mono text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <textarea rows="3" placeholder="Deskripsi singkat bab ini..."
                        class="ai-desc w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-[11px] text-slate-600 leading-relaxed focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">${escapeHtml(ch.description)}</textarea>

                    <div class="pt-1">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                                <i class='bx bx-list-ul text-sm'></i> Topics (Sub-Bab)
                            </span>
                            <button type="button" onclick="addAiTopic(this)"
                                class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-500 flex items-center gap-1 transition-all">
                                <i class='bx bx-plus'></i> Tambah Topic
                            </button>
                        </div>
                        <div class="ai-topic-list space-y-1.5"></div>
                        <p class="ai-topic-empty text-[10px] text-slate-400 italic hidden">Belum ada topic di bab ini.</p>
                    </div>
                </div>
                <button type="button" onclick="removeAiChapter(this)" title="Hapus bab ini beserta topic-nya"
                    class="shrink-0 w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-slate-400 flex items-center justify-center transition-all">
                    <i class='bx bx-trash text-sm'></i>
                </button>
            </div>
        `;
        wrap.appendChild(card);

        const topicList = card.querySelector('.ai-topic-list');
        (ch.topics || []).forEach(tp => topicList.appendChild(buildTopicRow(tp)));
        toggleTopicEmptyState(card);

        // Slug bab otomatis mengikuti perubahan judul
        const titleInput = card.querySelector('.ai-title');
        const slugInput  = card.querySelector('.ai-slug');
        titleInput.addEventListener('input', function () {
            slugInput.value = cleanSlugFromTitle(this.value);
        });
    });

    updateAiPreviewCount();
    document.getElementById('aiStepSelect').classList.add('hidden');
    document.getElementById('aiStepPreview').classList.remove('hidden');
}

function toggleTopicEmptyState(card) {
    const list  = card.querySelector('.ai-topic-list');
    const empty = card.querySelector('.ai-topic-empty');
    if (!list || !empty) return;
    empty.classList.toggle('hidden', list.children.length > 0);
}

function updateAiPreviewCount() {
    const totalBab   = document.querySelectorAll('#aiPreviewList .ai-chapter-item').length;
    const totalTopic = document.querySelectorAll('#aiPreviewList .ai-topic-item').length;
    const el = document.getElementById('aiPreviewCount');
    if (el) el.innerText = totalBab + ' bab, ' + totalTopic + ' topic';
}

function addAiTopic(btn) {
    const card = btn.closest('.ai-chapter-item');
    const list = card.querySelector('.ai-topic-list');
    const babNo = parseInt(card.querySelector('.ai-order').value, 10) || 1;
    const nextNo = list.children.length + 1;

    list.appendChild(buildTopicRow({
        title: babNo + '.' + nextNo + ' ',
        slug: '',
        order_position: nextNo,
        estimated_read_time: 5
    }));

    toggleTopicEmptyState(card);
    updateAiPreviewCount();
    list.lastElementChild.querySelector('.ai-topic-title').focus();
}

function removeAiTopic(btn) {
    const card = btn.closest('.ai-chapter-item');
    const row  = btn.closest('.ai-topic-item');
    if (row) row.remove();
    toggleTopicEmptyState(card);
    updateAiPreviewCount();
}

function removeAiChapter(btn) {
    const item = btn.closest('.ai-chapter-item');
    if (item) item.remove();
    updateAiPreviewCount();

    if (document.querySelectorAll('#aiPreviewList .ai-chapter-item').length === 0) {
        showToast('Semua bab dihapus. Silakan generate ulang.', 'error');
        backToAiSelect();
    }
}

// Rapikan ulang penomoran Bab (1,2,3...) & Topic (1.1, 1.2, 2.1 ...) setelah edit/hapus
function renumberAiPreview() {
    document.querySelectorAll('#aiPreviewList .ai-chapter-item').forEach((card, i) => {
        const babNo = i + 1;
        card.querySelector('.ai-order').value = babNo;

        const babTitleInput = card.querySelector('.ai-title');
        const pureBabTitle  = stripLeadingNumbering(babTitleInput.value);
        if (pureBabTitle !== '') {
            babTitleInput.value = 'Bab ' + babNo + '. ' + pureBabTitle;
            card.querySelector('.ai-slug').value = cleanSlugFromTitle(pureBabTitle);
        }

        card.querySelectorAll('.ai-topic-item').forEach((row, j) => {
            const topicNo = j + 1;
            row.querySelector('.ai-topic-order').value = topicNo;

            const tpTitleInput = row.querySelector('.ai-topic-title');
            const pureTpTitle  = stripLeadingNumbering(tpTitleInput.value);
            if (pureTpTitle !== '') {
                tpTitleInput.value = babNo + '.' + topicNo + ' ' + pureTpTitle;
                row.querySelector('.ai-topic-slug').value = cleanSlugFromTitle(pureTpTitle);
            }
        });
    });

    updateAiPreviewCount();
    showToast('Penomoran Bab & Topic sudah dirapikan');
}

// ---------- SIMPAN ----------

function saveAiModules() {
    const items = document.querySelectorAll('#aiPreviewList .ai-chapter-item');
    if (!items.length) {
        showToast('Tidak ada bab untuk disimpan', 'error');
        return;
    }

    const chapters = [];
    let invalid = false;

    items.forEach((item, idx) => {
        const title = item.querySelector('.ai-title').value.trim();
        let slug    = item.querySelector('.ai-slug').value.trim();
        const desc  = item.querySelector('.ai-desc').value.trim();
        let order   = parseInt(item.querySelector('.ai-order').value, 10);

        if (!title) { invalid = true; return; }
        if (!slug)  slug = cleanSlugFromTitle(title);
        if (!order || order < 1) order = idx + 1;

        const topics = [];
        item.querySelectorAll('.ai-topic-item').forEach((row, j) => {
            const tpTitle = row.querySelector('.ai-topic-title').value.trim();
            if (!tpTitle) return;

            let tpSlug  = row.querySelector('.ai-topic-slug').value.trim();
            let tpOrder = parseInt(row.querySelector('.ai-topic-order').value, 10);
            let tpTime  = parseInt(row.querySelector('.ai-topic-time').value, 10);

            if (!tpSlug)  tpSlug = cleanSlugFromTitle(tpTitle);
            if (!tpOrder || tpOrder < 1) tpOrder = j + 1;
            if (!tpTime || tpTime < 1)   tpTime = 5;

            topics.push({
                title: tpTitle,
                slug: tpSlug,
                order_position: tpOrder,
                estimated_read_time: tpTime
            });
        });

        chapters.push({
            title: title,
            slug: slug,
            description: desc,
            order_position: order,
            topics: topics
        });
    });

    if (invalid) {
        showToast('Judul bab tidak boleh kosong', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'ai_bulk_save');
    formData.append('course_id', aiCurrentCourseId);
    formData.append('chapters', JSON.stringify(chapters));

    setAiBtnLoading('aiSaveBtn', true, 'Menyimpan...');

    fetch('<?= admin_url("modules.php") ?>', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            showToast(res.message);
            closeAiModal();
            setTimeout(() => location.reload(), 900);
        } else {
            setAiBtnLoading('aiSaveBtn', false, '', "<i class='bx bx-save'></i><span>Simpan Semua Module</span>");
            showToast(res.message || 'Gagal menyimpan module', 'error');
        }
    })
    .catch(err => {
        setAiBtnLoading('aiSaveBtn', false, '', "<i class='bx bx-save'></i><span>Simpan Semua Module</span>");
        showToast('Terjadi kesalahan koneksi: ' + err.message, 'error');
    });
}

</script>

<?php require_once __DIR__ . '/views/layout_footer.php'; ?>