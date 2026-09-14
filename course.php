<!-- course.php -->
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiStack - LMS Player</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 flex flex-col h-screen overflow-hidden">

  <!-- Top Navigation Header -->
  <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-6 z-40">
    <div class="flex items-center gap-4">
      <button id="toggle-sidebar" class="text-gray-500 hover:text-gray-700 dark:hover:text-white focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <a href="/" class="text-xl font-bold text-blue-600 dark:text-blue-400">DigiStack</a>
      <span class="hidden md:inline text-gray-300 dark:text-gray-600">|</span>
      <span id="current-topic-breadcrumb" class="hidden md:inline text-sm font-medium text-gray-600 dark:text-gray-400 truncate max-w-xs">Memuat Topik...</span>
    </div>

    <!-- User Action / Profile -->
    <div class="flex items-center gap-3">
      <div id="user-profile-widget" class="flex items-center gap-2">
        <button onclick="openAuthModal()" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Masuk</button>
      </div>
    </div>
  </header>

  <!-- Main Viewer Wrapper -->
  <div class="flex-1 flex overflow-hidden relative">

    <!-- Include Accordion Sidebar -->
    <?php include 'components/sidebar-course.php'; ?>

    <!-- Main Content Reader -->
    <main class="flex-1 overflow-y-auto p-6 md:p-10 max-w-4xl mx-auto flex flex-col justify-between">
      <div>
        <!-- Topic Header -->
        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
          <span id="topic-module-tag" class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">-</span>
          <h1 id="topic-title" class="text-3xl font-extrabold mt-1 text-gray-900 dark:text-white">Memuat Materi...</h1>
          <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
            <span>Estimasi baca: <strong id="topic-read-time" class="font-semibold text-gray-700 dark:text-gray-300">-</strong> menit</span>
          </p>
        </div>

        <!-- Dynamic Content Body -->
        <article id="topic-body" class="prose dark:prose-invert max-w-none mb-10 text-gray-700 dark:text-gray-300 leading-relaxed">
          <!-- Raw Text / HTML / Code Renderer -->
        </article>
      </div>

      <!-- Action Footer -->
      <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700 mt-8">
        <button id="btn-prev-topic" onclick="navTopic('prev')" class="px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-xl text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-40">
          ← Topik Sebelumnya
        </button>
        <button id="btn-complete-topic" onclick="toggleCompleteTopic()" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl text-sm transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Selesai &amp; Lanjut
        </button>
      </div>
    </main>

  </div>

  <!-- Auth Modal Component (bisa dipanggil jika user belum login) -->
  <?php include 'components/auth-modal.php'; ?>

  <!-- Scripts -->
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/course-viewer.js"></script>
</body>
</html>