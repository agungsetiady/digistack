<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
check_admin_login();

$topic_slug = $_GET['s'] ?? '';

$query = "SELECT t.*, m.id AS module_id, m.title AS module_title, m.order_position AS module_order,
                 c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.icon AS course_icon,
                 tc.content_markdown, tc.summary_tldr, tc.generation_type, tc.provider_name, tc.model_name
          FROM topics t
          JOIN modules m ON t.module_id = m.id
          JOIN courses c ON m.course_id = c.id
          LEFT JOIN topic_contents tc ON t.id = tc.topic_id
          WHERE t.slug = ? LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->execute([$topic_slug]);
$topic = $stmt->fetch();

$course_id = (int)($topic['course_id'] ?? 0);
$module_id = (int)($topic['module_id'] ?? 0);

/*
 * Build the complete reader tree for the active course.
 * order_position is the primary ordering field; id is a deterministic
 * fallback because the current seed data contains several topic rows
 * with order_position = 0.
 */
$modules = [];
$all_topics = [];

if ($topic) {
    $treeStmt = $pdo->prepare("
        SELECT
            m.id AS module_id,
            m.title AS module_title,
            m.slug AS module_slug,
            m.order_position AS module_order,
            t.id AS topic_id,
            t.title AS topic_title,
            t.slug AS topic_slug,
            t.order_position AS topic_order,
            t.estimated_read_time
        FROM modules m
        LEFT JOIN topics t ON t.module_id = m.id
        WHERE m.course_id = ?
        ORDER BY m.order_position ASC, m.id ASC,
                 CASE WHEN t.id IS NULL THEN 1 ELSE 0 END,
                 t.order_position ASC, t.id ASC
    ");
    $treeStmt->execute([$course_id]);

    while ($row = $treeStmt->fetch()) {
        $mid = (int)$row['module_id'];

        if (!isset($modules[$mid])) {
            $modules[$mid] = [
                'id' => $mid,
                'title' => $row['module_title'],
                'slug' => $row['module_slug'],
                'order_position' => (int)$row['module_order'],
                'topics' => []
            ];
        }

        if (!empty($row['topic_id'])) {
            $item = [
                'id' => (int)$row['topic_id'],
                'title' => $row['topic_title'],
                'slug' => $row['topic_slug'],
                'estimated_read_time' => (int)$row['estimated_read_time'],
                'module_id' => $mid
            ];
            $modules[$mid]['topics'][] = $item;
            $all_topics[] = $item;
        }
    }
}

$active_index = -1;
foreach ($all_topics as $i => $item) {
    if ($item['id'] === (int)$topic['id']) {
        $active_index = $i;
        break;
    }
}

$prev_topic = $active_index > 0 ? $all_topics[$active_index - 1] : null;
$next_topic = ($active_index >= 0 && $active_index < count($all_topics) - 1)
    ? $all_topics[$active_index + 1]
    : null;

$total_topics = count($all_topics);
$topic_number = $active_index >= 0 ? $active_index + 1 : 1;
$progress_percent = $total_topics > 0 ? round(($topic_number / $total_topics) * 100) : 0;

function topic_url(array $item): string {
    return './view-topic?s=' . rawurlencode($item['slug']);
}

if (!$topic) {
    http_response_code(404);
    exit('Topic tidak ditemukan.');
}
?>
    <!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($topic['title']) ?> - <?= htmlspecialchars($topic['course_title']) ?></title>

    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <style>
        :root {
            --reader-bg: #faf8f5;
            --sidebar-bg: #ffffff;
            --line: #e7e5e4;
            --ink: #1e293b;
            --muted: #78716c;
            --accent: #4f46e5;
            --accent-soft: #eef2ff;
        }

        html { scroll-behavior: smooth; }
        body {
            background: var(--reader-bg);
            color: var(--ink);
            min-height: 100vh;
            font-family: 'Merriweather', serif;
        }

        .app-font, button, a, .sidebar { font-family: 'Plus Jakarta Sans', sans-serif; }
        code, pre { font-family: 'JetBrains Mono', monospace !important; }

        .sidebar {
            width: 320px;
            flex: 0 0 320px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--line);
        }

        .sidebar-scroll {
            height: calc(100vh - 72px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #d6d3d1 transparent;
        }

        .module-btn {
            transition: background .18s ease, color .18s ease;
        }

        .module-btn.active {
            background: #f8fafc;
        }

        .module-chevron {
            transition: transform .2s ease;
        }

        .module-btn[aria-expanded="true"] .module-chevron {
            transform: rotate(180deg);
        }

        .topic-link {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            padding: .62rem .8rem .62rem 2.55rem;
            color: #78716c;
            font-size: .76rem;
            line-height: 1.45;
            transition: background .16s ease, color .16s ease;
        }

        .font-serif-book {
            font-family: 'Merriweather', serif;
        }

        .font-sans-reader {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .font-sans-app {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .topic-link:hover {
            background: #fafafa;
            color: #292524;
        }

        .topic-link.active {
            background: var(--accent-soft);
            color: #3730a3;
            font-weight: 700;
        }

        .topic-link.active::before {
            content: "";
            position: absolute;
            left: 0;
            top: 7px;
            bottom: 7px;
            width: 3px;
            border-radius: 0 4px 4px 0;
            background: var(--accent);
        }

        .topic-dot {
            width: 7px;
            height: 7px;
            margin-top: .35rem;
            border-radius: 999px;
            border: 1.5px solid #cbd5e1;
            flex: 0 0 auto;
        }

        .topic-link.active .topic-dot {
            border-color: var(--accent);
            background: var(--accent);
        }

        .module-topics {
            overflow: hidden;
            transition: max-height .25s ease, opacity .2s ease;
        }

        .module-topics.closed {
            max-height: 0 !important;
            opacity: 0;
        }

        .reader-shell {
            min-width: 0;
            flex: 1;
        }

        .reader-header {
            position: sticky;
            top: 0;
            z-index: 30;
            height: 72px;
            background: rgba(250,248,245,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }

        .content-wrap {
            width: min(900px, calc(100% - 48px));
            margin: 0 auto;
            padding: 52px 0 48px;
        }

        .prose {
            font-size: 1rem;
            line-height: 1.9;
        }

        .prose h1, .prose h2, .prose h3, .prose h4 {
            color: #0f172a;
            font-family: 'Plus Jakarta Sans', sans-serif;
            scroll-margin-top: 96px;
        }

        .prose h2 {
            margin-top: 2.8em;
            margin-bottom: .8em;
            font-size: 1.45rem;
        }

        .prose h3 {
            margin-top: 2em;
            font-size: 1.15rem;
        }

        .prose blockquote {
            border-left-color: #6366f1;
        }

        .prose table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }

        .code-wrapper {
            margin: 1.35rem 0;
            border-radius: .8rem;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(15,23,42,.10);
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #21252b;
            padding: .55rem .9rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .72rem;
            color: #abb2bf;
            border-bottom: 1px solid #282c34;
        }

        .code-wrapper pre {
            margin: 0 !important;
            padding: 1rem 1.1rem !important;
            background: #282c34 !important;
            overflow-x: auto;
        }

        .prose :not(pre) > code {
            background: #f1f5f9;
            color: #4f46e5;
            padding: .18rem .38rem;
            border-radius: .35rem;
            font-size: .85em;
            font-weight: 600;
            border: 1px solid #e2e8f0;
        }

        .prose :not(pre) > code::before,
        .prose :not(pre) > code::after { content: "" !important; }

        .bottom-nav {
            margin-top: 56px;
            padding-top: 28px;
            border-top: 1px solid var(--line);
        }

        .nav-card {
            min-width: 0;
            border: 1px solid #e7e5e4;
            background: rgba(255,255,255,.72);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        .nav-card:hover {
            transform: translateY(-2px);
            border-color: #c7d2fe;
            box-shadow: 0 8px 24px rgba(15,23,42,.06);
        }

        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 39;
            background: rgba(15,23,42,.36);
        }

        @media (max-width: 1023px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 40;
                width: min(330px, 88vw);
                transform: translateX(-100%);
                transition: transform .25s ease;
                box-shadow: 20px 0 50px rgba(15,23,42,.12);
            }

            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open .mobile-overlay { display: block; }
            .content-wrap {
                width: min(900px, calc(100% - 36px));
                padding-top: 38px;
            }
        }

        @media (max-width: 640px) {
            .reader-header { height: 64px; }
            .content-wrap {
                width: calc(100% - 28px);
                padding-top: 30px;
            }
            .prose { font-size: .95rem; line-height: 1.82; }
            .prose h2 { font-size: 1.25rem; }
            .bottom-nav { margin-top: 40px; }
            .nav-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body class="antialiased">
    <div class="mobile-overlay" onclick="closeSidebar()"></div>

    <div class="min-h-screen flex">
        <!-- LEFT COURSE NAVIGATION -->
        <aside class="sidebar">
            <div class="h-[72px] border-b border-stone-200 px-5 flex items-center">
                <a href="./topics" class="flex items-center gap-3 min-w-0 group">
                    <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <i class="bx bx-<?= htmlspecialchars($topic['course_icon'] ?: 'book') ?> text-xl"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[10px] font-bold uppercase tracking-[.16em] text-stone-400">Course aktif</span>
                        <span class="block truncate text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 transition">
                            <?= htmlspecialchars($topic['course_title']) ?>
                        </span>
                    </span>
                </a>
            </div>

            <div class="sidebar-scroll">
                <div class="px-4 py-4">
                    <div class="flex items-center justify-between px-1 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-[.16em] text-stone-400">Materi pembelajaran</span>
                        <span class="text-[10px] font-bold text-stone-400"><?= $total_topics ?> topic</span>
                    </div>

                    <nav aria-label="Daftar modul">
                        <?php foreach ($modules as $module): ?>
                            <?php
                                $is_active_module = ((int)$module['id'] === $module_id);
                                $panel_id = 'module-topics-' . $module['id'];
                            ?>
                            <div class="mb-1">
                                <button
                                    type="button"
                                    class="module-btn <?= $is_active_module ? 'active' : '' ?> w-full rounded-xl px-3 py-3 text-left flex items-center gap-2"
                                    aria-expanded="<?= $is_active_module ? 'true' : 'false' ?>"
                                    aria-controls="<?= $panel_id ?>"
                                    onclick="toggleModule(this, '<?= $panel_id ?>')"
                                >
                                    <span class="w-7 h-7 rounded-lg <?= $is_active_module ? 'bg-indigo-100 text-indigo-600' : 'bg-stone-100 text-stone-500' ?> flex items-center justify-center shrink-0">
                                        <i class="bx bx-folder-open text-base"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[11px] font-bold <?= $is_active_module ? 'text-slate-900' : 'text-stone-700' ?> leading-4">
                                            <?= htmlspecialchars($module['title']) ?>
                                        </span>
                                        <span class="block mt-0.5 text-[10px] text-stone-400">
                                            <?= count($module['topics']) ?> topic
                                        </span>
                                    </span>
                                    <i class="bx bx-chevron-down module-chevron text-lg text-stone-400 shrink-0"></i>
                                </button>

                                <div id="<?= $panel_id ?>"
                                     class="module-topics <?= $is_active_module ? '' : 'closed' ?>"
                                     style="max-height: <?= $is_active_module ? '1200px' : '0px' ?>;">
                                    <?php foreach ($module['topics'] as $item): ?>
                                        <?php $is_active = ((int)$item['id'] === (int)$topic['id']); ?>
                                        <a
                                            href="<?= htmlspecialchars(topic_url($item)) ?>"
                                            class="topic-link <?= $is_active ? 'active' : '' ?>"
                                            <?= $is_active ? 'aria-current="page"' : '' ?>
                                            title="<?= htmlspecialchars($item['title']) ?>"
                                        >
                                            <span class="topic-dot"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block"><?= htmlspecialchars($item['title']) ?></span>
                                                <span class="block mt-0.5 text-[10px] opacity-70"><?= $item['estimated_read_time'] ?> min baca</span>
                                            </span>
                                            <?php if ($is_active): ?>
                                                <i class="bx bx-book-open text-sm mt-0.5"></i>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>

                                    <?php if (!$module['topics']): ?>
                                        <div class="px-10 py-3 text-xs text-stone-400">Belum ada topic.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        </aside>

        <!-- READER -->
        <section class="reader-shell">
            <header class="reader-header app-font">
                <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <button
                            type="button"
                            onclick="openSidebar()"
                            class="lg:hidden w-9 h-9 rounded-xl border border-stone-200 bg-white/70 text-stone-600 flex items-center justify-center"
                            aria-label="Buka daftar materi"
                        >
                            <i class="bx bx-menu text-xl"></i>
                        </button>

                        <div class="min-w-0">
                            <div class="text-[10px] uppercase tracking-[.16em] font-bold text-stone-400">
                                <?= htmlspecialchars($topic['module_title']) ?>
                            </div>
                            <div class="truncate max-w-[58vw] sm:max-w-[60vw] text-xs sm:text-sm font-bold text-slate-800">
                                <?= htmlspecialchars($topic['title']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="hidden sm:inline-flex text-[10px] font-bold text-stone-400">
                            <?= $topic_number ?> / <?= $total_topics ?>
                        </span>
                        <button
                            type="button"
                            onclick="toggleFont()"
                            class="w-9 h-9 rounded-xl border border-stone-200 bg-white/70 text-stone-600 hover:text-indigo-600 flex items-center justify-center"
                            title="Ganti font"
                            aria-label="Ganti font"
                        >
                            <i class="bx bx-font text-lg"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Reading progress -->
            <div class="h-0.5 bg-stone-200">
                <div class="h-full bg-indigo-500 transition-all" style="width: <?= $progress_percent ?>%;"></div>
            </div>

            <main>
                <div class="content-wrap">
                    <div class="mb-9 app-font">
                        <div class="flex flex-wrap items-center gap-2 text-[10px] sm:text-[11px] font-bold uppercase tracking-[.13em] text-stone-400 mb-3">
                            <span>Topic <?= $topic_number ?></span>
                            <span>•</span>
                            <span><?= $topic['estimated_read_time'] ?> menit baca</span>
                        </div>

                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-950 tracking-tight leading-[1.12] mb-5">
                            <?= htmlspecialchars($topic['title']) ?>
                        </h1>

                        <?php if (!empty($topic['summary_tldr'])): ?>
                            <div class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200/70 text-amber-950 text-xs leading-relaxed">
                                <div class="flex items-center gap-1.5 font-bold text-amber-700 uppercase tracking-wider text-[10px] mb-1.5">
                                    <i class="bx bx-bulb text-base"></i>
                                    <span>Rangkuman Cepat</span>
                                </div>
                                <p><?= htmlspecialchars($topic['summary_tldr']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <article id="reader-content" class="prose prose-stone max-w-none text-slate-800">
                    </article>

                    <!-- PREV / NEXT -->
                    <nav class="bottom-nav app-font" aria-label="Navigasi topic">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-[10px] uppercase tracking-[.16em] font-bold text-stone-400">Navigasi</div>
                                <div class="text-xs text-stone-500 mt-1">Lanjutkan membaca secara berurutan</div>
                            </div>
                            <span class="text-[10px] font-bold text-stone-400"><?= $progress_percent ?>% selesai</span>
                        </div>

                        <div class="nav-grid grid grid-cols-2 gap-3 sm:gap-4">
                            <?php if ($prev_topic): ?>
                                <a href="<?= htmlspecialchars(topic_url($prev_topic)) ?>" class="nav-card rounded-2xl p-4 sm:p-5 group">
                                    <span class="flex items-center gap-1.5 text-[10px] uppercase tracking-[.12em] font-bold text-stone-400 mb-2">
                                        <i class="bx bx-left-arrow-alt text-base"></i> Sebelumnya
                                    </span>
                                    <span class="block text-xs sm:text-sm font-bold leading-5 text-slate-800 group-hover:text-indigo-600">
                                        <?= htmlspecialchars($prev_topic['title']) ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <div class="rounded-2xl p-4 sm:p-5 border border-dashed border-stone-200 text-stone-400">
                                    <span class="text-[10px] uppercase tracking-[.12em] font-bold">Awal course</span>
                                    <span class="block mt-2 text-xs">Ini adalah topic pertama.</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($next_topic): ?>
                                <a href="<?= htmlspecialchars(topic_url($next_topic)) ?>" class="nav-card rounded-2xl p-4 sm:p-5 text-right group">
                                    <span class="flex items-center justify-end gap-1.5 text-[10px] uppercase tracking-[.12em] font-bold text-stone-400 mb-2">
                                        Berikutnya <i class="bx bx-right-arrow-alt text-base"></i>
                                    </span>
                                    <span class="block text-xs sm:text-sm font-bold leading-5 text-slate-800 group-hover:text-indigo-600">
                                        <?= htmlspecialchars($next_topic['title']) ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <div class="rounded-2xl p-4 sm:p-5 border border-dashed border-stone-200 text-right text-stone-400">
                                    <span class="text-[10px] uppercase tracking-[.12em] font-bold">Akhir course</span>
                                    <span class="block mt-2 text-xs">Semua topic sudah selesai.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </nav>
                </div>
            </main>
        </section>
    </div>

    <script>
        marked.setOptions({
            breaks: true,
            gfm: true
        });

        const rawMarkdown = <?= json_encode($topic['content_markdown'] ?? '*(Belum ada konten materi)*', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const readerEl = document.getElementById('reader-content');

        readerEl.innerHTML = marked.parse(rawMarkdown);

        document.querySelectorAll('#reader-content pre code').forEach((block) => {
            const pre = block.parentNode;

            let lang = 'CODE';
            block.classList.forEach(cls => {
                if (cls.startsWith('language-')) {
                    lang = cls.replace('language-', '').toUpperCase();
                }
            });

            const wrapper = document.createElement('div');
            wrapper.className = 'code-wrapper';

            const header = document.createElement('div');
            header.className = 'code-header';
            header.innerHTML = `
                <span class="font-mono font-semibold tracking-wide text-xs">${lang}</span>
                <button type="button"
                    onclick="copyCode(this)"
                    class="inline-flex items-center gap-1 text-[11px] bg-stone-700/50 hover:bg-stone-700 text-stone-300 px-2 py-1 rounded transition-all">
                    <i class="bx bx-copy"></i><span>Copy</span>
                </button>
            `;

            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(header);
            wrapper.appendChild(pre);

            if (window.hljs) hljs.highlightElement(block);
        });

        function copyCode(btn) {
            const code = btn.closest('.code-wrapper').querySelector('code').innerText;

            if (!navigator.clipboard) return;

            navigator.clipboard.writeText(code).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = `<i class="bx bx-check text-emerald-400"></i><span class="text-emerald-400">Copied</span>`;
                setTimeout(() => btn.innerHTML = original, 1800);
            });
        }

        function toggleModule(button, panelId) {
            const panel = document.getElementById(panelId);
            const isOpen = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', String(!isOpen));
            button.classList.toggle('active', !isOpen);
            panel.classList.toggle('closed', isOpen);
            panel.style.maxHeight = isOpen ? '0px' : panel.scrollHeight + 'px';
        }

        function openSidebar() {
            document.body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            document.body.classList.remove('sidebar-open');
        }

        // Toggle Font Serif (Buku) vs Sans-serif (Digital)
        function toggleFont() {
            const body = document.body;

            if (body.classList.contains('font-serif-book')) {
                body.classList.remove('font-serif-book');
                body.classList.add('font-sans-reader');
                localStorage.setItem('readerFont', 'sans');
            } else {
                body.classList.remove('font-sans-reader');
                body.classList.add('font-serif-book');
                localStorage.setItem('readerFont', 'serif');
            }
        }

        // Ambil font terakhir yang dipilih
        document.addEventListener('DOMContentLoaded', function () {
            const savedFont = localStorage.getItem('readerFont');

            if (savedFont === 'sans') {
                document.body.classList.remove('font-serif-book');
                document.body.classList.add('font-sans-reader');
            }
        });

        // Close mobile drawer after selecting a topic.
        document.querySelectorAll('.topic-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) closeSidebar();
            });
        });

        // Keep the active topic visible inside the sidebar.
        const activeTopic = document.querySelector('.topic-link.active');
        if (activeTopic) {
            setTimeout(() => activeTopic.scrollIntoView({ block: 'center', behavior: 'smooth' }), 100);
        }
    </script>
</body>
</html>