<?php
$courseSlug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$courseId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$initialTopicId = isset($_GET['topic']) ? (int) $_GET['topic'] : 0;

$hasCourseRef = ($courseSlug !== '' || $courseId > 0);

$base = '/digistack';
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <base href="<?= $base; ?>/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiStack - Learning Management System</title>
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <link rel="apple-touch-icon" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
  <style>
  /* Sembunyikan elemen berkelas sidebar-text saat sidebar di-collapse (desktop) */
  #sidebar.sidebar-collapsed .sidebar-text,
  #sidebar.sidebar-collapsed #module-accordion-container span,
  #sidebar.sidebar-collapsed #module-accordion-container svg {
    display: none !important;
  }
</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 flex flex-col h-screen overflow-hidden">

  <?php if (!$hasCourseRef): ?>
    <!-- State: tidak ada course yang dirujuk -->
    <div class="flex-1 flex items-center justify-center p-6">
      <div class="max-w-md text-center">
        <h1 class="text-2xl font-black text-gray-900 dark:text-white mb-2">Course Tidak Ditemukan</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Link yang kamu buka tidak menyertakan course yang valid. Silakan pilih course dari katalog.</p>
        <a href="index.php#katalog" class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition">Kembali ke Katalog</a>
      </div>
    </div>
  <?php else: ?>

  <!-- Container data attributes dibaca oleh course-viewer.js -->
  <div id="course-app"
       data-course-slug="<?= htmlspecialchars($courseSlug, ENT_QUOTES) ?>"
       data-course-id="<?= (int) $courseId ?>"
       data-initial-topic="<?= (int) $initialTopicId ?>"
       class="flex flex-col h-full">

    <!-- Top Navigation Header -->
    <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-6 z-40">
      <div class="flex items-center gap-4 min-w-0">
        <button id="toggle-sidebar" class="text-gray-500 hover:text-gray-700 dark:hover:text-white focus:outline-none">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="<?= $base; ?>/" class="flex items-center gap-2 text-xl font-bold text-blue-600 dark:text-blue-400 flex-shrink-0">
          <img src="<?= $base; ?>/assets/img/aguphia-icon.png" class="w-8 h-8 object-contain" alt="DigiStack Logo">
          <span>DigiStack</span>
        </a>
        <span class="hidden md:inline text-gray-300 dark:text-gray-600">|</span>
        <span id="current-topic-breadcrumb" class="hidden md:inline text-sm font-medium text-gray-600 dark:text-gray-400 truncate max-w-full">Memuat Topik...</span>
      </div>

      <!-- User Action / Profile -->
      <div class="flex items-center gap-3">
        <div id="user-profile-widget" class="flex items-center gap-2">
          <button onclick="openAuthModal()" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Masuk</button>
        </div>
      </div>
    </header>

    <!-- Main Viewer Wrapper -->
    <div class="flex-1 min-h-0 min-w-0 flex overflow-hidden relative">

      <!-- Include Accordion Sidebar -->
      <?php include __DIR__ . '/components/sidebar-course.php'; ?>

      <!-- Main Content Reader -->
      <main
        id="course-main"
        class="flex-1 min-w-0 min-h-0 overflow-y-auto overflow-x-hidden"
      >
        <div class="w-full px-4 py-6 sm:px-6 md:px-8 lg:px-10">
          <div class="w-full max-w-6xl mx-auto flex flex-col justify-between min-h-full">

            <div>

              <!-- Topic Header -->
              <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">

                <span
                  id="topic-module-tag"
                  class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider"
                >
                  -
                </span>

                <h1
                  id="topic-title"
                  class="text-2xl sm:text-3xl font-extrabold mt-1 text-gray-900 dark:text-white break-words"
                >
                  Memuat Materi...
                </h1>

                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-2">
                  <span>
                    Estimasi baca:
                    <strong
                      id="topic-read-time"
                      class="font-semibold text-gray-700 dark:text-gray-300"
                    >
                      -
                    </strong>
                    menit
                  </span>
                </p>

              </div>

              <!-- TL;DR Summary -->
              <div
                id="topic-tldr"
                class="hidden mb-6 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/40 text-sm text-blue-900 dark:text-blue-200 leading-relaxed"
              >
                <strong
                  class="block text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-1"
                >
                  Ringkasan
                </strong>

                <span id="topic-tldr-text"></span>
              </div>

              <!-- Dynamic Content Body -->
              <article
                id="topic-body"
                class="prose prose-sm sm:prose-base lg:prose-lg dark:prose-invert max-w-none mb-10 text-gray-700 dark:text-gray-300 leading-relaxed break-words"
              >
                <p class="text-gray-400">
                  Memuat konten materi...
                </p>
              </article>

            </div>

            <!-- Action Footer -->
            <div
              class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-6 border-t border-gray-200 dark:border-gray-700 mt-8"
            >

              <button
                id="btn-prev-topic"
                onclick="navTopic('prev')"
                class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40"
                disabled
              >
                ← Topik Sebelumnya
              </button>

              <button
                id="btn-complete-topic"
                onclick="toggleCompleteTopic()"
                class="w-full sm:w-auto px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl text-sm transition flex items-center justify-center gap-2"
              >
                <svg
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>

                <span id="btn-complete-label">
                  Selesai &amp; Lanjut
                </span>
              </button>

            </div>

          </div>
        </div>
      </main>

    </div>
  </div>

  <!-- Overlay: wajib login untuk mengakses materi -->
  <div id="login-required-overlay" class="hidden fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-sm flex items-center justify-center p-6">
    <div class="max-w-sm w-full text-center bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-2xl">
      <h2 class="text-xl font-black text-gray-900 dark:text-white mb-2">Silakan Masuk Dahulu</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Kamu perlu login untuk mengakses materi course ini dan menyimpan progres belajarmu.</p>
      <button onclick="openAuthModal()" class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition mb-3">Masuk / Daftar</button>
      <a href="index.php" class="block text-xs text-gray-400 hover:underline">Kembali ke Beranda</a>
    </div>
  </div>

  <?php endif; ?>

  <!-- Auth Modal Component (bisa dipanggil jika user belum login) -->
  <?php include __DIR__ . '/components/auth-modal.php'; ?>

  <!-- Scripts -->
  <script src="<?= $base; ?>/assets/js/auth.js"></script>
  <?php if ($hasCourseRef): ?>
  <script src="<?= $base; ?>/assets/js/course-viewer.js?v=1.2"></script>
  <?php endif; ?>
</body>
</html>