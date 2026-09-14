// assets/js/course-catalog.js
// Mengambil daftar course dari API (api/courses.php) dan merender grid katalog
// di index.php. Bergantung pada assets/js/auth.js (API_BASE_URL, getStoredUser).

const COURSE_ICONS = {
  code: '&#128187;',       // 💻
  terminal: '&#9000;&#65039;', // ⌨️
  book: '&#128218;',       // 📚
  default: '&#127919;',    // 🎯
};

function iconFor(icon) {
  return COURSE_ICONS[icon] || COURSE_ICONS.default;
}

// Render cover image jika ada (field `cover_image` di tabel courses = nama file,
// disimpan di folder assets/img/). Fallback ke kotak icon emoji jika kosong/gagal load.
function coverImageHtml(course) {
  if (!course.cover_image) {
    return `<div class="w-11 h-11 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center mb-4 text-xl">${iconFor(course.icon)}</div>`;
  }
  const src = `assets/img/${encodeURIComponent(course.cover_image)}`;
  return `
    <div class="w-full h-36 rounded-xl overflow-hidden mb-4 bg-blue-500/20 border border-blue-400/30">
      <img src="${src}" alt="${escapeHtml(course.title)}" class="w-full h-full object-cover"
        onerror="this.parentElement.outerHTML = '<div class=&quot;w-11 h-11 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center mb-4 text-xl&quot;>${iconFor(course.icon)}</div>';">
    </div>`;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.innerText = str ?? '';
  return div.innerHTML;
}

async function fetchCourseCatalog() {
  const loadingEl = document.getElementById('catalog-loading');
  const emptyEl = document.getElementById('catalog-empty');
  const gridEl = document.getElementById('catalog-grid');

  try {
    const token = localStorage.getItem('digistack_token');
    const headers = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const response = await fetch(`${API_BASE_URL}/courses.php`, { headers });
    const result = await response.json();

    loadingEl.classList.add('hidden');

    if (!response.ok || result.status !== 'success' || !Array.isArray(result.data) || result.data.length === 0) {
      emptyEl.classList.remove('hidden');
      return;
    }

    renderCourseCatalog(result.data);
    gridEl.classList.remove('hidden');
  } catch (error) {
    loadingEl.classList.add('hidden');
    emptyEl.classList.remove('hidden');
    emptyEl.querySelector('p')?.remove();
    emptyEl.innerHTML = `
      <p class="text-slate-300 font-medium">Gagal memuat katalog course.</p>
      <p class="text-slate-400 text-sm mt-1">Periksa koneksi kamu, lalu muat ulang halaman.</p>
    `;
  }
}

function renderCourseCatalog(courses) {
  const gridEl = document.getElementById('catalog-grid');
  const isLoggedIn = !!getStoredUser();

  gridEl.innerHTML = courses.map((course) => {
    const hasProgress = isLoggedIn && course.progress_percent > 0;
    const isDone = isLoggedIn && course.progress_percent >= 100;

    let ctaLabel = 'Mulai Belajar';
    if (!isLoggedIn) {
      ctaLabel = 'Login untuk Mulai';
    } else if (isDone) {
      ctaLabel = 'Ulas Kembali';
    } else if (hasProgress) {
      ctaLabel = 'Lanjutkan Belajar';
    }

    const progressBarHtml = isLoggedIn ? `
      <div class="mb-4">
        <div class="flex items-center justify-between text-[11px] font-semibold text-slate-300 mb-1.5">
          <span>${course.completed_topics}/${course.topic_count} materi selesai</span>
          <span>${course.progress_percent}%</span>
        </div>
        <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-400 rounded-full transition-all" style="width: ${course.progress_percent}%"></div>
        </div>
      </div>
    ` : '';

    return `
      <div class="group flex flex-col p-6 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 backdrop-blur-xl shadow-lg transition-all duration-300 hover:-translate-y-1">
        ${coverImageHtml(course)}
        <h3 class="text-white font-bold text-lg mb-1.5 leading-snug">${escapeHtml(course.title)}</h3>
        <p class="text-sm text-slate-300/90 leading-relaxed mb-4 flex-1">${escapeHtml(course.description || 'Belum ada deskripsi.')}</p>
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">
          ${course.module_count} Modul &middot; ${course.topic_count} Materi
        </p>
        ${progressBarHtml}
        <button
          onclick="handleCourseCta('${course.slug}')"
          class="w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm shadow-md transition-all">
          ${ctaLabel}
        </button>
      </div>
    `;
  }).join('');
}

function handleCourseCta(slug) {
  const user = getStoredUser();
  if (!user) {
    openAuthModal();
    return;
  }
  window.location.href = `course.php?slug=${encodeURIComponent(slug)}`;
}

function handleHeroCta() {
  const user = getStoredUser();
  if (!user) {
    openAuthModal();
    return;
  }
  document.getElementById('katalog')?.scrollIntoView({ behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', () => {
  fetchCourseCatalog();
});