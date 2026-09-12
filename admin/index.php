<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/views/layout_header.php';

// Menghitung statistik aktual dari database ecourse
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_modules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$total_topics  = $pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();
$total_ai_generated = $pdo->query("SELECT COUNT(*) FROM topic_contents WHERE generation_type = 'ai_generated'")->fetchColumn();
?>

<!-- Header -->
<header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Halo, <?= htmlspecialchars($_SESSION['admin_name']) ?> 👋</h1>
        <p class="text-sm text-slate-500 mt-0.5">Kelola dokumentasi materi dan konfigurasi AI Generator Engine.</p>
    </div>
    
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-3 bg-white border border-slate-200 py-1.5 px-3 rounded-xl shadow-sm">
            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-600/20">
                <?= strtoupper(substr($_SESSION['admin_name'], 0, 1)) ?>
            </div>
            <div class="text-xs">
                <p class="font-semibold text-slate-800"><?= htmlspecialchars($_SESSION['admin_name']) ?></p>
                <p class="text-slate-400 uppercase font-mono text-[10px]"><?= htmlspecialchars($_SESSION['admin_role']) ?></p>
            </div>
        </div>
    </div>
</header>

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white border border-slate-200/80 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Courses</span>
            <div class="w-9 h-9 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                <i class='bx bx-book-bookmark'></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-slate-900"><?= number_format($total_courses) ?></h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Total Kelas Terdaftar</p>
    </div>

    <div class="bg-white border border-slate-200/80 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Modules (Bab)</span>
            <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">
                <i class='bx bx-layer'></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-slate-900"><?= number_format($total_modules) ?></h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Total Bab Materi</p>
    </div>

    <div class="bg-white border border-slate-200/80 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Topics (Sub-bab)</span>
            <div class="w-9 h-9 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-xl">
                <i class='bx bx-list-ul'></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-slate-900"><?= number_format($total_topics) ?></h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Total Sub-bab Materi</p>
    </div>

    <div class="bg-white border border-slate-200/80 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">AI Content</span>
            <div class="w-9 h-9 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-xl">
                <i class='bx bx-bot'></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-slate-900"><?= number_format($total_ai_generated) ?></h3>
        <p class="text-xs text-purple-600 font-medium mt-1">Generated via AI</p>
    </div>
</div>

<?php
require_once __DIR__ . '/views/layout_footer.php';
?>