<?php
require_once __DIR__ . '/admin/config.php';

// Ubah nama ini sesuai branding platform kamu
$site_name = 'Ruang Belajar';

$search = trim($_GET['q'] ?? '');

$whereClause = "WHERE c.is_published = 1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND c.title LIKE ?";
    $params[] = "%{$search}%";
}

$sql = "SELECT c.*,
               COUNT(DISTINCT m.id) AS total_modules,
               COUNT(t.id) AS total_topics,
               COALESCE(SUM(t.estimated_read_time), 0) AS total_minutes
        FROM courses c
        LEFT JOIN modules m ON m.course_id = c.id
        LEFT JOIN topics t ON t.module_id = m.id
        {$whereClause}
        GROUP BY c.id
        ORDER BY c.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

function course_url(array $course): string {
    return './course?c=' . rawurlencode($course['slug']);
}

function format_duration(int $minutes): string {
    if ($minutes <= 0) return '-';
    if ($minutes < 60) return $minutes . ' menit';
    $hours = floor($minutes / 60);
    $rest  = $minutes % 60;
    return $rest > 0 ? "{$hours} jam {$rest} menit" : "{$hours} jam";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?> — Katalog Course</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --page-bg: #faf8f5;
            --line: #e7e5e4;
            --ink: #1e293b;
            --muted: #78716c;
            --accent: #4f46e5;
            --accent-soft: #eef2ff;
        }

        body {
            background: var(--page-bg);
            color: var(--ink);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .font-serif-display { font-family: 'Merriweather', serif; }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(250, 248, 245, .92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }

        .search-input {
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }

        .course-card {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            padding-left: calc(1.5rem + 4px);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .course-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 1.25rem;
            bottom: 1.25rem;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--accent);
            opacity: .85;
        }

        .course-card:hover {
            transform: translateY(-3px);
            border-color: #c7d2fe;
            box-shadow: 0 14px 32px rgba(15, 23, 42, .08);
        }

        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: .68rem;
            font-weight: 600;
            padding: .3rem .55rem;
            border-radius: .55rem;
        }

        .empty-state {
            border: 1px dashed #d6d3d1;
            border-radius: 1.5rem;
        }
    </style>
</head>

<body class="antialiased min-h-screen">

    <!-- HEADER -->
    <header class="site-header">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 h-[72px] flex items-center justify-between gap-4">
            <a href="./" class="flex items-center gap-2.5 shrink-0">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
                    <i class="bx bx-book-reader text-lg"></i>
                </span>
                <span class="font-extrabold text-slate-900 text-sm sm:text-base tracking-tight">
                    <?= htmlspecialchars($site_name) ?>
                </span>
            </a>

            <form action="./" method="GET" class="relative w-full max-w-xs hidden sm:block">
                <i class="bx bx-search absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400 text-base"></i>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Cari course..."
                    class="search-input w-full pl-10 pr-3 py-2 bg-white border border-stone-200 rounded-xl text-xs text-slate-800 placeholder-stone-400 focus:outline-none"
                >
            </form>
        </div>
    </header>

    <!-- HERO -->
    <section class="max-w-6xl mx-auto px-5 sm:px-8 pt-14 pb-10 sm:pt-20 sm:pb-14">
        <p class="text-[11px] font-bold uppercase tracking-[.16em] text-indigo-600 mb-3">Katalog Course</p>
        <h1 class="font-serif-display text-3xl sm:text-[2.75rem] leading-[1.15] text-slate-950 max-w-xl mb-4">
            Lanjutkan belajar, satu bab dalam satu waktu.
        </h1>
        <p class="text-sm sm:text-[15px] text-stone-500 max-w-md leading-relaxed">
            Pilih salah satu course di bawah untuk mulai membaca materinya.
        </p>

        <form action="./" method="GET" class="relative w-full max-w-xs mt-6 sm:hidden">
            <i class="bx bx-search absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400 text-base"></i>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari course..."
                class="search-input w-full pl-10 pr-3 py-2.5 bg-white border border-stone-200 rounded-xl text-xs text-slate-800 placeholder-stone-400 focus:outline-none"
            >
        </form>
    </section>

    <!-- COURSE GRID -->
    <main class="max-w-6xl mx-auto px-5 sm:px-8 pb-20">
        <?php if ($search !== ''): ?>
            <div class="flex items-center justify-between mb-5">
                <p class="text-xs text-stone-500">
                    Hasil pencarian untuk "<span class="font-semibold text-slate-800"><?= htmlspecialchars($search) ?></span>" — <?= count($courses) ?> course ditemukan
                </p>
                <a href="./" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Reset</a>
            </div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="empty-state py-16 sm:py-24 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-stone-100 text-stone-400 flex items-center justify-center mx-auto mb-4">
                    <i class="bx bx-book-open text-2xl"></i>
                </div>
                <h2 class="text-sm font-bold text-slate-800 mb-1.5">
                    <?= $search !== '' ? 'Course tidak ditemukan' : 'Belum ada course yang aktif' ?>
                </h2>
                <p class="text-xs text-stone-500 max-w-xs mx-auto leading-relaxed">
                    <?= $search !== ''
                        ? 'Coba kata kunci lain atau reset pencarian untuk melihat semua course.'
                        : 'Silakan cek kembali beberapa saat lagi.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($courses as $c): ?>
                    <a href="<?= htmlspecialchars(course_url($c)) ?>" class="course-card p-6 flex flex-col group">
                        <span class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="bx bx-<?= htmlspecialchars($c['icon'] ?? 'book') ?> text-xl"></i>
                        </span>

                        <h3 class="font-serif-display text-lg font-bold text-slate-900 leading-snug mb-2 group-hover:text-indigo-700 transition-colors">
                            <?= htmlspecialchars($c['title']) ?>
                        </h3>

                        <?php if (!empty($c['description'])): ?>
                            <p class="text-xs text-stone-500 leading-relaxed mb-4 line-clamp-2">
                                <?= htmlspecialchars($c['description']) ?>
                            </p>
                        <?php else: ?>
                            <div class="mb-4"></div>
                        <?php endif; ?>

                        <div class="flex flex-wrap gap-1.5 mt-auto mb-4">
                            <span class="stat-chip"><i class="bx bx-folder-open"></i> <?= (int)$c['total_modules'] ?> modul</span>
                            <span class="stat-chip"><i class="bx bx-file"></i> <?= (int)$c['total_topics'] ?> topic</span>
                            <span class="stat-chip"><i class="bx bx-time-five"></i> <?= htmlspecialchars(format_duration((int)$c['total_minutes'])) ?></span>
                        </div>

                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 group-hover:gap-2.5 transition-all">
                            Mulai Belajar <i class="bx bx-right-arrow-alt text-base"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>