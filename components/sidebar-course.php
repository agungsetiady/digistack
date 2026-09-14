<!-- components/sidebar-course.php -->
<aside
  id="sidebar"
  class="fixed inset-y-0 left-0 z-40 w-80 -translate-x-full transition-all duration-300 ease-in-out bg-white border-r border-gray-200 dark:bg-gray-800 dark:border-gray-700 lg:static lg:translate-x-0 flex flex-col overflow-hidden flex-shrink-0"
  aria-label="Daftar modul course"
>
  <!-- Sidebar Header -->
  <div class="h-16 min-h-16 px-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3 overflow-hidden">
    <div id="sidebar-header-content" class="min-w-0 flex-1 sidebar-text">
      <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider whitespace-nowrap block">
        Daftar Modul
      </span>
      <h2 id="course-title" class="text-sm font-bold text-gray-800 dark:text-white truncate">
        Memuat Kursus...
      </h2>
    </div>

    <span id="course-progress-badge" class="sidebar-text flex-shrink-0 text-xs font-semibold px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 rounded-full">
      0%
    </span>
  </div>

  <!-- Accordion List -->
  <div
    id="module-accordion-container"
    class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden divide-y divide-gray-100 dark:divide-gray-700/50"
  >
    <!-- Module items akan di-inject via course-viewer.js -->
  </div>
</aside>

<!-- Mobile overlay -->
<div
  id="sidebar-overlay"
  class="hidden fixed inset-0 z-30 bg-black/40 backdrop-blur-[1px] lg:hidden"
  aria-hidden="true"
></div>