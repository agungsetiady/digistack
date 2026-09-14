// assets/js/course-viewer.js

let courseData = null;   // { id, title, slug, modules: [...] }
let flatTopics = [];     // daftar topic terurut lintas modul, untuk navigasi prev/next
let currentTopicId = null;

const appEl = () => document.getElementById('course-app');

function authHeaders() {
  const token = localStorage.getItem('digistack_token');
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function showLoginRequired() {
  document.getElementById('course-app')?.classList.add('hidden');
  document.getElementById('login-required-overlay')?.classList.remove('hidden');
  document.getElementById('login-required-overlay')?.classList.add('flex');
}

/**
 * Wrapper fetch yang otomatis menangani 401 (sesi habis/tidak valid).
 */
async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: { ...(options.headers || {}), ...authHeaders() },
  });

  if (response.status === 401) {
    clearSession();
    showLoginRequired();
    throw new Error('unauthorized');
  }

  return response;
}

document.addEventListener('DOMContentLoaded', () => {
  const app = appEl();
  if (!app) return; // halaman sedang menampilkan state "course tidak ditemukan"

  initSidebarToggle();

  const user = getStoredUser();
  if (!user) {
    showLoginRequired();
    return;
  }

  loadCourse();
});

function initSidebarToggle() {
  const toggleBtn = document.getElementById('toggle-sidebar');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (!toggleBtn || !sidebar) return;

  let isCollapsed = false;

  function isMobile() {
    return window.innerWidth < 1024; // Align dengan breakpoint lg Tailwind (1024px)
  }

  function openMobileSidebar() {
    sidebar.classList.remove('-translate-x-full');
    overlay?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }

  function closeMobileSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  function toggleDesktopSidebar() {
    isCollapsed = !isCollapsed;

    if (isCollapsed) {
      // Hilangkan sidebar sepenuhnya di desktop
      sidebar.classList.remove('w-80', 'border-r');
      sidebar.classList.add('w-0');
    } else {
      // Kembalikan ke lebar semula
      sidebar.classList.remove('w-0');
      sidebar.classList.add('w-80', 'border-r');
    }
  }

  toggleBtn.addEventListener('click', () => {
    if (isMobile()) {
      const isOpen = !sidebar.classList.contains('-translate-x-full');
      if (isOpen) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }
    } else {
      toggleDesktopSidebar();
    }
  });

  overlay?.addEventListener('click', () => {
    if (isMobile()) closeMobileSidebar();
  });

  window.addEventListener('resize', () => {
    if (!isMobile()) {
      closeMobileSidebar();
      sidebar.classList.remove('-translate-x-full');
      
      // Pertahankan status collapsed jika user sedang menutup sidebar di desktop
      if (isCollapsed) {
        sidebar.classList.remove('w-80', 'border-r');
        sidebar.classList.add('w-0');
      } else {
        sidebar.classList.remove('w-0');
        sidebar.classList.add('w-80', 'border-r');
      }
    } else {
      // Pindah ke breakpoint mobile/tablet
      sidebar.classList.remove('w-0');
      sidebar.classList.add('w-80', 'border-r', '-translate-x-full');
    }
  });
}

// 2. Muat struktur course (modules + topics) dari API
async function loadCourse() {
  const app = appEl();
  const slug = app.dataset.courseSlug;
  const id = parseInt(app.dataset.courseId, 10) || 0;
  const initialTopic = parseInt(app.dataset.initialTopic, 10) || 0;

  const query = slug ? `slug=${encodeURIComponent(slug)}` : `id=${id}`;

  try {
    const response = await apiFetch(`/course-detail.php?${query}`);
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      renderCourseError(result.message || 'Course tidak ditemukan.');
      return;
    }

    courseData = result.data;
    flattenTopics();
    renderCourseSidebar();

    let startTopicId = null;
    if (initialTopic && flatTopics.some(t => t.id === initialTopic)) {
      startTopicId = initialTopic;
    } else {
      const firstIncomplete = flatTopics.find(t => !t.completed);
      startTopicId = firstIncomplete ? firstIncomplete.id : (flatTopics[0]?.id ?? null);
    }

    if (startTopicId) {
      loadTopic(startTopicId);
    } else {
      renderCourseError('Course ini belum memiliki materi.');
    }
  } catch (error) {
    if (error.message !== 'unauthorized') {
      renderCourseError('Gagal terhubung ke server. Silakan muat ulang halaman.');
    }
  }
}

function flattenTopics() {
  flatTopics = [];
  (courseData.modules || []).forEach((module) => {
    (module.topics || []).forEach((topic) => {
      flatTopics.push({ ...topic, moduleId: module.id, moduleTitle: module.title });
    });
  });
}

function renderCourseError(message) {
  const body = document.getElementById('topic-body');
  if (body) {
    body.innerHTML = `<p class="text-red-500 font-medium">${escapeHtml(message)}</p>`;
  }
  document.getElementById('topic-title').innerText = 'Terjadi Kesalahan';
  document.getElementById('btn-complete-topic')?.classList.add('hidden');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.innerText = str ?? '';
  return div.innerHTML;
}

// 3. Render Accordion Sidebar dari data asli
function renderCourseSidebar() {
  document.getElementById('course-title').innerText = courseData.title;

  const badge = document.getElementById('course-progress-badge');
  if (badge) badge.innerText = `${courseData.progress_percent}%`;

  const container = document.getElementById('module-accordion-container');
  container.innerHTML = '';

  courseData.modules.forEach((module, index) => {
    const moduleEl = document.createElement('div');

    const topicsHtml = (module.topics || []).map((t) => `
      <a href="javascript:void(0)" onclick="navigateToTopic(${t.id})"
         id="topic-btn-${t.id}"
         class="px-6 py-2.5 flex items-center justify-between text-xs font-medium transition ${t.id === currentTopicId ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'}">
        <span class="flex items-center gap-2 truncate">
          ${t.completed ? '<svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : ''}
          ${escapeHtml(t.title)}
        </span>
        <span class="text-gray-400 text-[10px] ml-2 flex-shrink-0">${t.estimated_read_time}m</span>
      </a>
    `).join('');

    const isActiveModule = (module.topics || []).some(t => t.id === currentTopicId) || (index === 0 && currentTopicId === null);

    moduleEl.innerHTML = `
      <div>
        <button onclick="toggleModule(${module.id})" class="w-full px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-700/40 text-left font-semibold text-xs text-gray-700 dark:text-gray-200">
          <span class="truncate pr-2">${escapeHtml(module.title)}</span>
          <svg id="arrow-${module.id}" class="w-4 h-4 flex-shrink-0 transform transition-transform duration-200 ${isActiveModule ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="module-body-${module.id}" class="${isActiveModule ? '' : 'hidden'} py-1">
          ${topicsHtml}
        </div>
      </div>
    `;

    container.appendChild(moduleEl);
  });
}

function toggleModule(moduleId) {
  const body = document.getElementById(`module-body-${moduleId}`);
  const arrow = document.getElementById(`arrow-${moduleId}`);
  body?.classList.toggle('hidden');
  arrow?.classList.toggle('rotate-180');
}

// 4. Navigasi ke topik tertentu (dipanggil dari sidebar)
function navigateToTopic(topicId) {
  loadTopic(topicId);
}

// 5. Muat & render konten satu topik dari API
async function loadTopic(topicId) {
  try {
    const response = await apiFetch(`/topic-content.php?id=${topicId}`);
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      renderCourseError(result.message || 'Materi tidak ditemukan.');
      return;
    }

    const topic = result.data;
    currentTopicId = topic.id;

    // Sinkronkan status completed ke data lokal
    const localTopic = flatTopics.find(t => t.id === topic.id);
    if (localTopic) localTopic.completed = topic.completed;

    // Render breadcrumb & header
    document.getElementById('current-topic-breadcrumb').innerText = `${topic.module_title} / ${topic.title}`;
    document.getElementById('topic-module-tag').innerText = topic.module_title;
    document.getElementById('topic-title').innerText = topic.title;
    document.getElementById('topic-read-time').innerText = topic.estimated_read_time;

    // TL;DR
    const tldrBox = document.getElementById('topic-tldr');
    const tldrText = document.getElementById('topic-tldr-text');
    if (topic.summary_tldr) {
      tldrText.innerText = topic.summary_tldr;
      tldrBox.classList.remove('hidden');
    } else {
      tldrBox.classList.add('hidden');
    }

    // Render Markdown -> HTML
    const bodyEl = document.getElementById('topic-body');
    if (window.marked && topic.content_markdown) {
      bodyEl.innerHTML = marked.parse(topic.content_markdown);
    } else {
      bodyEl.innerHTML = '<p class="text-gray-400">Materi ini belum memiliki konten.</p>';
    }

    // Syntax highlighting untuk blok kode
    if (window.hljs) {
      bodyEl.querySelectorAll('pre code').forEach((block) => hljs.highlightElement(block));
    }

    updateCompleteButton(topic.completed);
    updateNavButtons();
    renderCourseSidebar();

    // KODE BARU:
    const newUrl = `course/${encodeURIComponent(topic.course_slug)}/topic/${topic.id}`;
    // Atau jika memilih format pendek tanpa kata 'topic':
    // const newUrl = `course/${encodeURIComponent(topic.course_slug)}/${topic.id}`;

    window.history.replaceState({}, '', newUrl);

    // Scroll ke atas setiap ganti topik
    document.querySelector('main')?.scrollTo({ top: 0, behavior: 'instant' });
  } catch (error) {
    if (error.message !== 'unauthorized') {
      renderCourseError('Gagal memuat materi. Silakan coba lagi.');
    }
  }
}

function updateCompleteButton(isCompleted) {
  const label = document.getElementById('btn-complete-label');
  if (!label) return;
  label.innerHTML = isCompleted ? 'Sudah Selesai &#10003;' : 'Selesai &amp; Lanjut';
}

function updateNavButtons() {
  const idx = flatTopics.findIndex(t => t.id === currentTopicId);
  const prevBtn = document.getElementById('btn-prev-topic');
  const nextExists = idx >= 0 && idx < flatTopics.length - 1;

  if (prevBtn) prevBtn.disabled = idx <= 0;

  const completeBtn = document.getElementById('btn-complete-topic');
  const label = document.getElementById('btn-complete-label');
  if (completeBtn && label && !nextExists) {
    // Topik terakhir di course -- ganti label tombol saat sudah selesai
    const topic = flatTopics[idx];
    if (topic?.completed) label.innerHTML = 'Course Selesai &#127881;';
  }
}

// 6. Navigasi Prev/Next
function navTopic(direction) {
  const idx = flatTopics.findIndex(t => t.id === currentTopicId);
  if (idx === -1) return;

  const targetIdx = direction === 'prev' ? idx - 1 : idx + 1;
  if (targetIdx < 0 || targetIdx >= flatTopics.length) return;

  loadTopic(flatTopics[targetIdx].id);
}

// 7. Tandai topik selesai / batal selesai, lalu auto-lanjut ke topik berikutnya
async function toggleCompleteTopic() {
  if (!currentTopicId) return;

  const localTopic = flatTopics.find(t => t.id === currentTopicId);
  const newState = !(localTopic?.completed);

  try {
    const response = await apiFetch('/topic-progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic_id: currentTopicId, completed: newState }),
    });
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      alert(result.message || 'Gagal menyimpan progres.');
      return;
    }

    if (localTopic) localTopic.completed = newState;
    if (courseData) {
      courseData.completed_topics = result.course_progress.completed_topics;
      courseData.total_topics = result.course_progress.total_topics;
      courseData.progress_percent = result.course_progress.progress_percent;
    }

    updateCompleteButton(newState);
    renderCourseSidebar();

    // Auto-lanjut ke topik berikutnya jika baru saja ditandai selesai
    if (newState) {
      const idx = flatTopics.findIndex(t => t.id === currentTopicId);
      if (idx >= 0 && idx < flatTopics.length - 1) {
        loadTopic(flatTopics[idx + 1].id);
      }
    }
  } catch (error) {
    if (error.message !== 'unauthorized') {
      alert('Gagal terhubung ke server saat menyimpan progres.');
    }
  }
}