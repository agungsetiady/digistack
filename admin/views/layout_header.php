<?php
require_once __DIR__ . '/../auth.php';
check_admin_login();

// Mengambil path URL dan membersihkannya dari slash/query string
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$current_page = trim(basename($uri), '/');

// Jika halaman utama/root, atur default (opsional)
if (empty($current_page)) {
    $current_page = 'dashboard'; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Course Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/aguphia-icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/aguphia-icon.png">
    <link rel="apple-touch-icon" href="/assets/img/aguphia-icon.png">
    <link rel="shortcut icon" href="/assets/img/aguphia-icon.png" type="image/x-icon">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-disabled {
            pointer-events: none; /* Mematikan interaksi klik */
            cursor: default;       /* Mengubah kursor jadi panah biasa */
            opacity: 1;          /* Efek visual disabled/pudar */
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen pb-20 md:pb-0">

    <!-- DESKTOP SIDEBAR (Tampil di md: ke atas) -->
    <aside class="hidden md:flex fixed top-0 left-0 z-40 w-20 h-screen bg-white border-r border-white flex-col justify-between py-5 px-2.5">
        <div class="flex flex-col items-center">
            <a href="#" class="w-11 h-11 rounded-xl flex items-center justify-center text-white text-2xl transition-all mb-6 btn-disabled">
                <img src="../assets/img/aguphia-icon.png">
            </a>

            <nav class="flex flex-col gap-2 w-full">
                <a href="./dashboard" class="group flex flex-col items-center justify-center py-2.5 px-1 rounded-xl <?= $current_page === 'dashboard' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?> transition-all">
                    <i class='bx bx-grid-alt text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                    <span class="text-[9px] font-semibold tracking-tight text-center leading-none">Dash</span>
                </a>

                <a href="./courses" class="group flex flex-col items-center justify-center py-2.5 px-1 rounded-xl <?= $current_page === 'courses' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?> transition-all">
                    <i class='bx bx-book-bookmark text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                    <span class="text-[9px] font-medium tracking-tight text-center leading-none">Course</span>
                </a>

                <a href="./modules" class="group flex flex-col items-center justify-center py-2.5 px-1 rounded-xl <?= $current_page === 'modules' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?> transition-all">
                    <i class='bx bx-layer text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                    <span class="text-[9px] font-medium tracking-tight text-center leading-none">Module</span>
                </a>

                <a href="./topics" class="group flex flex-col items-center justify-center py-2.5 px-1 rounded-xl <?= $current_page === 'topics' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?> transition-all">
                    <i class='bx bx-list-ul text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                    <span class="text-[9px] font-medium tracking-tight text-center leading-none">Topic</span>
                </a>

                <a href="./ai-engine" class="group flex flex-col items-center justify-center py-2.5 px-1 rounded-xl <?= $current_page === 'ai-engine' || $current_page === 'ai-providers' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?> transition-all">
                    <i class='bx bx-bot text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                    <span class="text-[9px] font-medium tracking-tight text-center leading-none">AI Engine</span>
                </a>
            </nav>
        </div>

        <div class="flex flex-col items-center">
            <a href="./logout" class="group flex flex-col items-center justify-center py-2.5 px-1 w-full rounded-xl text-slate-400 hover:bg-rose-500/10 hover:text-rose-400 transition-all">
                <i class='bx bx-log-out text-2xl mb-1 group-hover:scale-110 transition-transform'></i>
                <span class="text-[9px] font-medium tracking-tight text-center leading-none">Exit</span>
            </a>
        </div>
    </aside>

    <!-- MOBILE & TABLET BOTTOM NAVIGATION (Tampil di bawah md) -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-900 border-t border-slate-800 px-3 py-2 flex items-center justify-around">
        <a href="./dashboard" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl <?= $current_page === 'dashboard' ? 'text-indigo-400 font-semibold' : 'text-slate-400' ?>">
            <i class='bx bx-grid-alt text-xl'></i>
            <span class="text-[10px] mt-0.5">Dash</span>
        </a>
        <a href="./courses" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl <?= $current_page === 'courses' ? 'text-indigo-400 font-semibold' : 'text-slate-400' ?>">
            <i class='bx bx-book-bookmark text-xl'></i>
            <span class="text-[10px] mt-0.5">Course</span>
        </a>
        <a href="./modules" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl <?= $current_page === 'modules' ? 'text-indigo-400 font-semibold' : 'text-slate-400' ?>">
            <i class='bx bx-layer text-xl'></i>
            <span class="text-[10px] mt-0.5">Module</span>
        </a>
        <a href="./topics" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl <?= $current_page === 'topics' ? 'text-indigo-400 font-semibold' : 'text-slate-400' ?>">
            <i class='bx bx-list-ul text-xl'></i>
            <span class="text-[10px] mt-0.5">Topic</span>
        </a>
        <a href="./ai-engine" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl <?= $current_page === 'ai-engine' || $current_page === 'ai-providers' ? 'text-indigo-400 font-semibold' : 'text-slate-400' ?>">
            <i class='bx bx-bot text-xl'></i>
            <span class="text-[10px] mt-0.5">AI</span>
        </a>
        <a href="./logout" class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-rose-400">
            <i class='bx bx-log-out text-xl'></i>
            <span class="text-[10px] mt-0.5">Exit</span>
        </a>
    </div>

    <!-- MAIN CONTENT (Margin & width disesuaikan presisi di desktop) -->
    <main class="flex-1 min-w-0 md:ml-20 md:w-[calc(100%-5rem)] p-4 sm:p-6 md:p-8">