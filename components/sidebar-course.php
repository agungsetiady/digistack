<!-- components/sidebar-course.php -->
<aside id="sidebar" class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex-shrink-0 flex flex-col transition-all duration-300">
  
  <!-- Sidebar Header -->
  <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
    <div>
      <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Daftar Modul</span>
      <h2 id="course-title" class="text-sm font-bold text-gray-800 dark:text-white truncate max-w-[200px]">Memuat Kursus...</h2>
    </div>
    <span id="course-progress-badge" class="text-xs font-semibold px-2 py-0.5 bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 rounded-full">0%</span>
  </div>

  <!-- Accordion List (Dynamic Container) -->
  <div id="module-accordion-container" class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700/50">
    <!-- Module items akan di-inject via course-viewer.js -->
  </div>
</aside>