// assets/js/course-catalog.js
// Mengambil daftar course dari API (api/courses.php) dan merender grid katalog
// di index.php. Bergantung pada assets/js/auth.js (API_BASE_URL, getStoredUser).

const COURSE_ICONS = {
  code: '&#128187;',          // 💻
  terminal: '&#9000;&#65039;', // ⌨️
  book: '&#128218;',          // 📚
  default: '&#127919;',       // 🎯
};

function iconFor(icon) {
  return COURSE_ICONS[icon] || COURSE_ICONS.default;
}

// Escape HTML agar data dari database aman saat dimasukkan ke innerHTML.
function escapeHtml(str) {
  const div = document.createElement('div');
  div.innerText = str ?? '';
  return div.innerHTML;
}

// Format durasi dari total menit.
// Contoh:
// 0     -> Belum tersedia
// 45    -> 45 menit
// 120   -> 2 jam
// 154   -> 2 jam 34 menit
function formatDuration(totalMinutes) {
  const minutes = Number(totalMinutes) || 0;

  if (minutes <= 0) {
    return 'Durasi belum tersedia';
  }

  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;

  if (hours > 0 && remainingMinutes > 0) {
    return `${hours} jam ${remainingMinutes} menit`;
  }

  if (hours > 0) {
    return `${hours} jam`;
  }

  return `${remainingMinutes} menit`;
}

// Render cover image.
// Ukuran source cover course adalah 1890x945 = rasio 2:1.
// Menggunakan aspect-[2/1] + object-contain agar gambar tidak ter-crop.
function coverImageHtml(course) {
  if (!course.cover_image) {
    return `
      <div class="w-full aspect-[2/1] rounded-xl overflow-hidden mb-4 bg-blue-500/10 border border-blue-400/20 flex items-center justify-center text-3xl">
        ${iconFor(course.icon)}
      </div>
    `;
  }

  const src = `assets/img/${encodeURIComponent(course.cover_image)}`;

  return `
    <div class="w-full aspect-[2/1] rounded-xl overflow-hidden mb-4 bg-slate-950/50 border border-white/10 flex items-center justify-center">
      <img
        src="${src}"
        alt="${escapeHtml(course.title)}"
        class="w-full h-full object-contain"
        loading="lazy"
        onerror="this.parentElement.outerHTML = '<div class=&quot;w-full aspect-[2/1] rounded-xl overflow-hidden mb-4 bg-blue-500/10 border border-blue-400/20 flex items-center justify-center text-3xl&quot;>${iconFor(course.icon)}</div>';"
      >
    </div>
  `;
}

// Modal detail course.
function openCourseDetailModal(course) {
  let modal = document.getElementById('course-detail-modal');

  // Jika modal belum ada, buat secara dinamis.
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'course-detail-modal';

    modal.className = [
      'fixed',
      'inset-0',
      'z-[100]',
      'hidden',
      'items-end',
      'sm:items-center',
      'justify-center',
      'p-0',
      'sm:p-6'
    ].join(' ');

    modal.innerHTML = `
      <div
        id="course-detail-backdrop"
        class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm"
      ></div>

      <div
        id="course-detail-panel"
        class="relative w-full sm:max-w-2xl max-h-[92vh] overflow-hidden bg-slate-900 border border-slate-700/80 shadow-2xl rounded-t-3xl sm:rounded-3xl transform transition-all duration-300"
      >
        <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-slate-800">
          <div class="flex items-center gap-3 min-w-0">
            <div
              id="course-detail-icon"
              class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-400/20 flex items-center justify-center text-xl flex-shrink-0"
            ></div>

            <div class="min-w-0">
              <p class="text-[10px] uppercase tracking-widest font-bold text-blue-400">
                Course
              </p>
              <h2
                id="course-detail-title"
                class="text-base sm:text-lg font-bold text-white truncate"
              ></h2>
            </div>
          </div>

          <button
            id="course-detail-close"
            type="button"
            class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition flex-shrink-0"
            aria-label="Tutup"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        <div class="overflow-y-auto max-h-[calc(92vh-150px)]">
          <div id="course-detail-cover" class="px-5 sm:px-6 pt-5"></div>

          <div class="px-5 sm:px-6 pb-6">
            <div
              id="course-detail-meta"
              class="flex flex-wrap items-center gap-x-4 gap-y-2 py-4 text-xs font-semibold text-slate-400"
            ></div>

            <div>
              <h3 class="text-sm font-bold text-white mb-2">
                Tentang Course
              </h3>

              <div
                id="course-detail-description"
                class="text-sm leading-7 text-slate-300 whitespace-pre-line"
              ></div>
            </div>
          </div>
        </div>

        <div class="px-5 sm:px-6 py-4 border-t border-slate-800 bg-slate-900/95 backdrop-blur-md">
          <button
            id="course-detail-start"
            type="button"
            class="w-full px-5 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-blue-600/20 transition-all active:scale-[0.99]"
          >
            Mulai Belajar
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    document
      .getElementById('course-detail-close')
      ?.addEventListener('click', closeCourseDetailModal);

    document
      .getElementById('course-detail-backdrop')
      ?.addEventListener('click', closeCourseDetailModal);

    document
      .getElementById('course-detail-start')
      ?.addEventListener('click', () => {
        const currentCourse = window.__activeCourseDetail;

        if (!currentCourse) {
          return;
        }

        startCourse(currentCourse);
      });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeCourseDetailModal();
      }
    });
  }

  window.__activeCourseDetail = course;

  const titleEl = document.getElementById('course-detail-title');
  const iconEl = document.getElementById('course-detail-icon');
  const coverEl = document.getElementById('course-detail-cover');
  const metaEl = document.getElementById('course-detail-meta');
  const descriptionEl = document.getElementById('course-detail-description');

  if (titleEl) {
    titleEl.textContent = course.title || 'Course';
  }

  if (iconEl) {
    iconEl.innerHTML = iconFor(course.icon);
  }

  if (coverEl) {
    coverEl.innerHTML = coverImageHtml(course).replace('mb-4', '');
  }

  const duration = formatDuration(course.duration_minutes);

  if (metaEl) {
    metaEl.innerHTML = `
      <span class="inline-flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"
          />
        </svg>
        ${course.module_count || 0} Modul
      </span>

      <span class="text-slate-700">•</span>

      <span>
        ${course.topic_count || 0} Materi
      </span>

      <span class="text-slate-700">•</span>

      <span>
        Durasi ${escapeHtml(duration)}
      </span>
    `;
  }

  if (descriptionEl) {
    descriptionEl.textContent =
      course.description || 'Belum ada deskripsi course.';
  }

  // Lock scroll ketika modal terbuka.
  document.body.classList.add('overflow-hidden');

  modal.classList.remove('hidden');
  modal.classList.add('flex');

  // Animasi panel.
  const panel = document.getElementById('course-detail-panel');

  if (panel) {
    requestAnimationFrame(() => {
      panel.classList.remove('translate-y-full');
      panel.classList.add('translate-y-0');
    });
  }
}

function closeCourseDetailModal() {
  const modal = document.getElementById('course-detail-modal');

  if (!modal) {
    return;
  }

  const panel = document.getElementById('course-detail-panel');

  if (panel) {
    panel.classList.remove('translate-y-0');
    panel.classList.add('translate-y-full');
  }

  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.body.classList.remove('overflow-hidden');
  }, 180);
}

// URL awal course.
// Tahap ini menghasilkan URL:
// /digistack/course/{course-slug}
//
// Topic slug akan ditambahkan oleh course.php/course-viewer
// pada tahap berikutnya setelah resolver topic selesai.
function startCourse(course) {
  if (!course || !course.slug) {
    return;
  }

  window.location.href =
    `/digistack/course/${encodeURIComponent(course.slug)}`;
}

// Mengambil daftar course dari API.
async function fetchCourseCatalog() {
  const loadingEl = document.getElementById('catalog-loading');
  const emptyEl = document.getElementById('catalog-empty');
  const gridEl = document.getElementById('catalog-grid');

  try {
    const token = localStorage.getItem('digistack_token');

    const headers = {};

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(
      `${API_BASE_URL}/courses.php`,
      {
        headers
      }
    );

    const result = await response.json();

    loadingEl?.classList.add('hidden');

    if (
      !response.ok ||
      result.status !== 'success' ||
      !Array.isArray(result.data) ||
      result.data.length === 0
    ) {
      emptyEl?.classList.remove('hidden');
      return;
    }

    renderCourseCatalog(result.data);

    gridEl?.classList.remove('hidden');

  } catch (error) {
    console.error('Gagal memuat katalog course:', error);

    loadingEl?.classList.add('hidden');
    emptyEl?.classList.remove('hidden');

    if (emptyEl) {
      emptyEl.innerHTML = `
        <p class="text-slate-300 font-medium">
          Gagal memuat katalog course.
        </p>

        <p class="text-slate-400 text-sm mt-1">
          Periksa koneksi kamu, lalu muat ulang halaman.
        </p>
      `;
    }
  }
}

function renderCourseCatalog(courses) {
  const gridEl = document.getElementById('catalog-grid');

  if (!gridEl) {
    return;
  }

  const isLoggedIn = !!getStoredUser();

  gridEl.innerHTML = courses
    .map((course) => {
      const hasProgress =
        isLoggedIn && Number(course.progress_percent) > 0;

      const isDone =
        isLoggedIn && Number(course.progress_percent) >= 100;

      let ctaLabel = 'Mulai Belajar';

      if (!isLoggedIn) {
        ctaLabel = 'Login untuk Mulai';
      } else if (isDone) {
        ctaLabel = 'Ulas Kembali';
      } else if (hasProgress) {
        ctaLabel = 'Lanjutkan Belajar';
      }

      const progressBarHtml = isLoggedIn
        ? `
          <div class="mb-4">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-300 mb-1.5">
              <span>
                ${course.completed_topics || 0}/${course.topic_count || 0}
                materi selesai
              </span>

              <span>
                ${course.progress_percent || 0}%
              </span>
            </div>

            <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-blue-500 to-indigo-400 rounded-full transition-all"
                style="width: ${course.progress_percent || 0}%"
              ></div>
            </div>
          </div>
        `
        : '';

      const duration = formatDuration(course.duration_minutes);

      return `
        <div
          class="group flex flex-col p-6 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 backdrop-blur-xl shadow-lg transition-all duration-300 hover:-translate-y-1 cursor-pointer"
          data-course-slug="${escapeHtml(course.slug)}"
          role="button"
          tabindex="0"
          aria-label="Lihat detail ${escapeHtml(course.title)}"
          onclick="handleCourseCta('${String(course.slug || '').replace(/'/g, "\\'")}')"
          onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); handleCourseCta('${String(course.slug || '').replace(/'/g, "\\'")}'); }"
        >

          ${coverImageHtml(course)}

          <h3 class="text-white font-bold text-lg mb-1.5 leading-snug">
            ${escapeHtml(course.title)}
          </h3>

          <p class="text-sm text-slate-300/90 leading-relaxed mb-4 flex-1">
            ${escapeHtml(course.description || 'Belum ada deskripsi.')}
          </p>

          <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">
            <span>${course.module_count || 0} Modul</span>
            <span>•</span>
            <span>${course.topic_count || 0} Materi</span>
            <span>•</span>
            <span>Durasi ${escapeHtml(duration)}</span>
          </div>

          ${progressBarHtml}

          <button
            type="button"
            onclick="event.stopPropagation(); handleCourseCta('${String(course.slug || '').replace(/'/g, "\\'")}')"
            class="w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm shadow-md transition-all"
          >
            ${ctaLabel}
          </button>

        </div>
      `;
    })
    .join('');
}

// Card course sekarang hanya membuka modal.
// Tidak langsung redirect ke course.php.
function handleCourseCta(slug) {
  const course = window.__courseCatalog?.find(
    (item) => String(item.slug) === String(slug)
  );

  if (!course) {
    return;
  }

  const user = getStoredUser();

  if (!user) {
    openAuthModal();
    return;
  }

  openCourseDetailModal(course);
}

function handleHeroCta() {
  const user = getStoredUser();

  if (!user) {
    openAuthModal();
    return;
  }

  document
    .getElementById('katalog')
    ?.scrollIntoView({
      behavior: 'smooth'
    });
}

document.addEventListener('DOMContentLoaded', () => {
  // Menyimpan data course agar bisa diakses ketika card diklik.
  const originalRenderCourseCatalog = renderCourseCatalog;

  window.__courseCatalog = [];

  // Override kecil agar data API tersimpan global sebelum render.
  window.renderCourseCatalog = function (courses) {
    window.__courseCatalog = Array.isArray(courses)
      ? courses
      : [];

    originalRenderCourseCatalog(courses);
  };

  fetchCourseCatalog();
});