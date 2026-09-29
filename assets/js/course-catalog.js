// assets/js/course-catalog.js
// Mengambil daftar course dari API (api/courses.php) dan merender grid katalog
// di index.php. Bergantung pada assets/js/auth.js (API_BASE_URL, getStoredUser).

const COURSE_ICONS = {
  code: '&#128187;',           // 💻
  terminal: '&#9000;&#65039;', // ⌨️
  book: '&#128218;',           // 📚
  default: '&#127919;',        // 🎯
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

function bindCourseDetailModalEvents() {
  const closeButton = document.getElementById('course-detail-close');
  const backdrop = document.getElementById('course-detail-backdrop');
  const startButton = document.getElementById('course-detail-start');

  if (closeButton && !closeButton.dataset.bound) {
    closeButton.dataset.bound = '1';

    closeButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      closeCourseDetailModal();
    });
  }

  if (backdrop && !backdrop.dataset.bound) {
    backdrop.dataset.bound = '1';

    backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) {
        closeCourseDetailModal();
      }
    });
  }

  if (startButton && !startButton.dataset.bound) {
    startButton.dataset.bound = '1';

    startButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      const currentCourse = window.__activeCourseDetail;

      if (!currentCourse) {
        console.error('Course aktif tidak ditemukan.');
        return;
      }

      startCourse(currentCourse);
    });
  }

  if (!document.body.dataset.courseDetailEscapeBound) {
    document.body.dataset.courseDetailEscapeBound = '1';

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeCourseDetailModal();
      }
    });
  }
}

function openCourseDetailModal(course) {
  if (!course) {
    console.error('Data course tidak ditemukan.');
    return;
  }

  let modal = document.getElementById('course-detail-modal');

  // Jika modal belum ada, buat secara dinamis
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'course-detail-modal';

    modal.className = [
      'fixed',
      'inset-0',
      'bg-slate-950/70',
      'backdrop-blur-md',
      'hidden',
      'flex',
      'items-center',
      'justify-center',
      'p-4',
      'z-[90]',
      'opacity-0',
      'transition-opacity',
      'duration-300'
    ].join(' ');

    modal.innerHTML = `
      <div id="course-detail-backdrop" class="absolute inset-0"></div>

      <!-- Modal Card (Glassmorphism Concept) -->
      <div id="course-detail-panel" class="bg-white/90 dark:bg-slate-900/90 border border-white/40 dark:border-slate-800 rounded-3xl shadow-2xl backdrop-blur-xl w-full max-w-2xl relative overflow-hidden transition-all duration-300 transform scale-95 translate-y-2 flex flex-col max-h-[90vh] z-10">
        
        <!-- Ambient Inner Light Effect -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none animate-pulse"></div>
        <div class="absolute -bottom-24 -left-24 w-40 h-40 bg-indigo-400/10 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Close Button -->
        <button id="course-detail-close" type="button" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800/60 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-xl transition z-20" aria-label="Tutup">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Header (Hanya Judul Course) -->
        <div class="p-6 sm:p-7 pb-4 border-b border-slate-200/60 dark:border-slate-800/80 pr-14 relative z-10">
          <h2 id="course-detail-title" class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white tracking-tight leading-snug"></h2>
        </div>

        <!-- Scrollable Content Body -->
        <div class="overflow-y-auto p-6 sm:p-7 space-y-6 relative z-10 flex-1">
          <!-- Cover Image -->
          <div id="course-detail-cover"></div>

          <!-- Meta Info -->
          <div id="course-detail-meta" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100/70 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-200/50 dark:border-slate-800/50"></div>

          <!-- Description Section -->
          <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-2">Tentang Course</h3>
            <div id="course-detail-description" class="text-sm leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line"></div>
          </div>
        </div>

        <!-- Sticky Footer CTA -->
        <div class="p-5 sm:p-6 border-t border-slate-200/60 dark:border-slate-800/80 bg-white/50 dark:bg-slate-900/50 backdrop-blur-md relative z-10">
          <button id="course-detail-start" type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 text-sm active:scale-[0.99]">
            Mulai Belajar
          </button>
        </div>

      </div>
    `;

    document.body.appendChild(modal);
  }

  // Bind event listeners modal
  bindCourseDetailModalEvents();

  window.__activeCourseDetail = course;

  const titleEl = document.getElementById('course-detail-title');
  const coverEl = document.getElementById('course-detail-cover');
  const metaEl = document.getElementById('course-detail-meta');
  const descriptionEl = document.getElementById('course-detail-description');

  if (titleEl) {
    titleEl.textContent = course.title || 'Course';
  }

  if (coverEl) {
    coverEl.innerHTML = coverImageHtml(course).replace('mb-4', '');
  }

  const duration = formatDuration(course.duration_minutes);

  if (metaEl) {
    metaEl.innerHTML = `
      <span class="inline-flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
        ${course.module_count || 0} Modul
      </span>
      <span class="text-slate-300 dark:text-slate-700">•</span>
      <span>${course.topic_count || 0} Materi</span>
      <span class="text-slate-300 dark:text-slate-700">•</span>
      <span>Durasi ${escapeHtml(duration)}</span>
    `;
  }

  if (descriptionEl) {
    descriptionEl.textContent = course.description || 'Belum ada deskripsi course.';
  }

  // Atur teks CTA berdasarkan status login dan progress.
  const startButton = document.getElementById('course-detail-start');

  if (startButton) {
    const isLoggedIn = !!getStoredUser();
    const progressPercent = Number(course.progress_percent) || 0;

    if (!isLoggedIn) {
      startButton.textContent = 'Mulai Belajar';
    } else if (progressPercent >= 100) {
      startButton.textContent = 'Ulas Kembali';
    } else if (progressPercent > 0) {
      startButton.textContent = 'Lanjutkan Belajar';
    } else {
      startButton.textContent = 'Mulai Belajar';
    }
  }

  // Lock scroll pada body
  document.body.classList.add('overflow-hidden');

  // Tampilkan Modal dengan animasi
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  setTimeout(() => {
    modal.classList.remove('opacity-0');
    modal.classList.add('opacity-100');

    const panel = document.getElementById('course-detail-panel');
    if (panel) {
      panel.classList.remove('scale-95', 'translate-y-2');
      panel.classList.add('scale-100', 'translate-y-0');
    }
  }, 10);
}

function closeCourseDetailModal() {
  const modal = document.getElementById('course-detail-modal');

  if (!modal) return;

  const panel = document.getElementById('course-detail-panel');

  if (panel) {
    panel.classList.remove('scale-100', 'translate-y-0');
    panel.classList.add('scale-95', 'translate-y-2');
  }

  modal.classList.remove('opacity-100');
  modal.classList.add('opacity-0');

  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }, 300);
}

// ============================================================
// TOPIC SLUG RESOLVER
// ============================================================
//
// Mendukung beberapa kemungkinan nama field dari API.
// Prioritas pertama adalah:
//   topic_slug
//   first_topic_slug
//   slug_topic
//
// Kemudian fallback ke struktur object/array topic.
// ============================================================

function getCourseTopicSlug(course) {
  if (!course) {
    return '';
  }

  const candidates = [
    course.topic_slug,
    course.first_topic_slug,
    course.slug_topic,
    course.firstTopicSlug,

    course.topic?.slug,

    course.first_topic?.slug,

    course.firstTopic?.slug,

    course.topics?.[0]?.slug,

    course.topic_list?.[0]?.slug
  ];

  const topicSlug =
    candidates.find(
      (value) =>
        typeof value === 'string' &&
        value.trim() !== ''
    );

  return topicSlug
    ? topicSlug.trim()
    : '';
}

// ============================================================
// START COURSE
// ============================================================
//
// Card:
//   ↓
// Modal Detail
//   ↓
// Klik tombol CTA
//   ↓
// Cek login
//
// Guest:
//   → Auth Modal
//
// Logged in:
//   → /digistack/{course-slug}/{topic-slug}
// ============================================================

function startCourse(course) {
  if (!course || !course.slug) {
    return;
  }

  const user =
    getStoredUser();

  // ==========================================================
  // USER BELUM LOGIN
  // ==========================================================

  if (!user) {
    openAuthModal();
    return;
  }

  // ==========================================================
  // USER SUDAH LOGIN
  // ==========================================================

  const topicSlug =
    getCourseTopicSlug(course);

  // Jangan membuat URL yang salah jika API
  // belum mengirim slug topic.
  if (!topicSlug) {
    console.error(
      'Slug topic pertama tidak tersedia pada data course:',
      course
    );

    return;
  }

  const courseSlug =
    encodeURIComponent(
      String(course.slug).trim()
    );

  const encodedTopicSlug =
    encodeURIComponent(topicSlug);

  // Clean URL Phase 1.
  //
  // Contoh:
  // /digistack/belajar-javascript/dasar-javascript
  //
  window.location.href =
    `/digistack/${courseSlug}/${encodedTopicSlug}`;
}

// ============================================================
// FETCH COURSE CATALOG
// ============================================================

async function fetchCourseCatalog() {
  const loadingEl =
    document.getElementById(
      'catalog-loading'
    );

  const emptyEl =
    document.getElementById(
      'catalog-empty'
    );

  const gridEl =
    document.getElementById(
      'catalog-grid'
    );

  try {
    const token =
      localStorage.getItem(
        'digistack_token'
      );

    const headers = {};

    if (token) {
      headers['Authorization'] =
        `Bearer ${token}`;
    }

    const response =
      await fetch(
        `${API_BASE_URL}/courses.php`,
        {
          headers
        }
      );

    const result =
      await response.json();

    loadingEl?.classList.add(
      'hidden'
    );

    if (
      !response.ok ||
      result.status !== 'success' ||
      !Array.isArray(result.data) ||
      result.data.length === 0
    ) {
      emptyEl?.classList.remove(
        'hidden'
      );

      return;
    }

    // Simpan data course secara global
    // agar bisa digunakan ketika card diklik.
    window.__courseCatalog = result.data;

    renderCourseCatalog(result.data);

    gridEl?.classList.remove(
      'hidden'
    );

  } catch (error) {
    console.error(
      'Gagal memuat katalog course:',
      error
    );

    loadingEl?.classList.add(
      'hidden'
    );

    emptyEl?.classList.remove(
      'hidden'
    );

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

// ============================================================
// RENDER COURSE CATALOG
// ============================================================

function renderCourseCatalog(courses) {
  const gridEl =
    document.getElementById(
      'catalog-grid'
    );

  if (!gridEl) {
    return;
  }

  const isLoggedIn =
    !!getStoredUser();

  gridEl.innerHTML =
    courses
      .map((course) => {
        const hasProgress =
          isLoggedIn &&
          Number(
            course.progress_percent
          ) > 0;

        const isDone =
          isLoggedIn &&
          Number(
            course.progress_percent
          ) >= 100;

        let ctaLabel =
          'Mulai Belajar';

        if (!isLoggedIn) {
          ctaLabel =
            'Course Detail';
        } else if (isDone) {
          ctaLabel =
            'Ulas Kembali';
        } else if (hasProgress) {
          ctaLabel =
            'Lanjutkan Belajar';
        }

        const progressBarHtml =
          isLoggedIn
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

        const duration =
          formatDuration(
            course.duration_minutes
          );

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

            <h3
              class="text-white font-bold text-lg mb-1.5 leading-snug"
            >
              ${escapeHtml(course.title)}
            </h3>

            <p
              class="text-sm text-slate-300/90 leading-relaxed mb-4 flex-1"
            >
              ${escapeHtml(
                course.description ||
                'Belum ada deskripsi.'
              )}
            </p>

            <div
              class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4"
            >
              <span>
                ${course.module_count || 0} Modul
              </span>

              <span>
                •
              </span>

              <span>
                ${course.topic_count || 0} Materi
              </span>

              <span>
                •
              </span>

              <span>
                Durasi ${escapeHtml(duration)}
              </span>
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

// ============================================================
// COURSE CARD CTA
// ============================================================
//
// PENTING:
// Fungsi ini TIDAK mengecek login.
//
// Baik guest maupun user login:
//   klik course
//      ↓
//   modal detail
//
// Pengecekan login dilakukan oleh startCourse()
// setelah tombol CTA di dalam modal diklik.
// ============================================================

function handleCourseCta(slug) {
  const course =
    window.__courseCatalog?.find(
      (item) =>
        String(item.slug) ===
        String(slug)
    );

  if (!course) {
    return;
  }

  // Selalu tampilkan detail course terlebih dahulu.
  openCourseDetailModal(
    course
  );
}

// ============================================================
// HERO CTA
// ============================================================
//
// Fungsi existing ini tetap dipertahankan.
// Tidak berhubungan dengan flow card → modal → start course.
// ============================================================

function handleHeroCta() {
  const user =
    getStoredUser();

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

// ============================================================
// DOM READY
// ============================================================

document.addEventListener(
  'DOMContentLoaded',
  () => {
    // Inisialisasi catalog global.
    window.__courseCatalog = [];

    // fetchCourseCatalog() akan menyimpan
    // result.data ke window.__courseCatalog
    // sebelum melakukan render.
    fetchCourseCatalog();
  }
);