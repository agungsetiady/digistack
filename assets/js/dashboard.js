// assets/js/dashboard.js
// Mengatur tampilan index.php agar berbeda antara guest (belum login) dan
// member (sudah login): guest melihat landing page marketing, member melihat
// dashboard personal (continue learning, rekomendasi, statistik, aktivitas).
// Bergantung pada assets/js/auth.js (API_BASE_URL, getStoredUser, initials).

const DASH_ICONS = {
  code: '&#128187;',
  terminal: '&#9000;&#65039;',
  book: '&#128218;',
  default: '&#127919;',
};

function dashIconFor(icon) {
  return DASH_ICONS[icon] || DASH_ICONS.default;
}

// Render cover image jika ada (field `cover_image` di tabel courses = nama file,
// disimpan di folder assets/img/). Fallback ke kotak icon emoji jika kosong/gagal load.
function dashCoverImageHtml(c, accentClass) {
  const fallback = `<div class="w-11 h-11 rounded-xl ${accentClass} flex items-center justify-center text-xl">${dashIconFor(c.icon)}</div>`;
  if (!c.cover_image) return fallback;

  const src = `assets/img/${encodeURIComponent(c.cover_image)}`;
  const escapedFallback = fallback.replace(/"/g, '&quot;');
  return `
    <div class="w-11 h-11 rounded-xl overflow-hidden ${accentClass}">
      <img src="${src}" alt="${dashEscape(c.title)}" class="w-full h-full object-cover"
        onerror="this.parentElement.outerHTML = '${escapedFallback}';">
    </div>`;
}

function dashEscape(str) {
  const div = document.createElement('div');
  div.innerText = str ?? '';
  return div.innerHTML;
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const then = new Date(dateStr.replace(' ', 'T'));
  const diffMs = Date.now() - then.getTime();
  const diffMin = Math.floor(diffMs / 60000);

  if (diffMin < 1) return 'Baru saja';
  if (diffMin < 60) return `${diffMin} menit lalu`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} jam lalu`;
  const diffDay = Math.floor(diffHour / 24);
  if (diffDay < 7) return `${diffDay} hari lalu`;
  return then.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatMinutes(totalMinutes) {
  if (totalMinutes < 60) return `${totalMinutes} menit`;
  const hours = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  return mins > 0 ? `${hours} jam ${mins} menit` : `${hours} jam`;
}

function greetingByHour() {
  const h = new Date().getHours();
  if (h < 11) return 'Selamat pagi';
  if (h < 15) return 'Selamat siang';
  if (h < 19) return 'Selamat sore';
  return 'Selamat malam';
}

// ---------------------------------------------------------------------
// Toggle utama: tampilkan guest-view ATAU member-view
// ---------------------------------------------------------------------
function initHomeView() {
  const user = getStoredUser();
  const guestView  = document.getElementById('guest-view');
  const memberView = document.getElementById('member-view');
  const catalogSubtitle = document.getElementById('catalog-subtitle');
  const catalogHeading  = document.getElementById('catalog-heading');

  if (!user) {
    if (guestView) guestView.classList.remove('hidden');
    if (memberView) memberView.classList.add('hidden');
    if (catalogHeading) catalogHeading.textContent = 'Katalog Course';
    if (catalogSubtitle) catalogSubtitle.textContent = 'Pilih materi yang ingin Anda kuasai. Progres belajar tersimpan secara otomatis.';
    return;
  }

  if (guestView) guestView.classList.add('hidden');
  if (memberView) memberView.classList.remove('hidden');
  if (catalogHeading) catalogHeading.textContent = 'Jelajahi Course Lainnya';
  if (catalogSubtitle) catalogSubtitle.textContent = 'Perluas kemampuanmu dengan materi lain di luar course yang sedang kamu ikuti.';

  fetchDashboardData(user);
}

async function fetchDashboardData(user) {
  const token = localStorage.getItem('digistack_token');
  const skeletonEl = document.getElementById('dashboard-loading');
  const contentEl  = document.getElementById('dashboard-content');
  const errorEl    = document.getElementById('dashboard-error');

  try {
    const response = await fetch(`${API_BASE_URL}/dashboard.php`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Gagal memuat dashboard.');
    }

    renderDashboard(result.data, user);
    skeletonEl?.classList.add('hidden');
    contentEl?.classList.remove('hidden');
  } catch (error) {
    skeletonEl?.classList.add('hidden');
    errorEl?.classList.remove('hidden');
  }
}

function renderDashboard(data, user) {
  renderGreeting(data, user);
  renderStats(data.stats);
  renderContinueLearning(data.continue_learning);
  renderRecommended(data.recommended);
  renderRecentActivity(data.recent_activity);
}

function renderGreeting(data, user) {
  const nameEl = document.getElementById('member-greeting-name');
  const subEl  = document.getElementById('member-greeting-sub');
  if (nameEl) nameEl.textContent = `${greetingByHour()}, ${user.name.split(' ')[0]}!`;

  if (subEl) {
    const { stats } = data;
    if (stats.enrolled_courses === 0) {
      subEl.textContent = 'Yuk mulai course pertamamu dan bangun kebiasaan belajar hari ini.';
    } else if (data.continue_learning.length > 0) {
      subEl.textContent = 'Ini progres belajarmu. Lanjutkan dari tempat terakhir kamu berhenti.';
    } else {
      subEl.textContent = 'Semua course yang kamu ikuti sudah tuntas. Saatnya jelajahi topik baru!';
    }
  }
}

function renderStats(stats) {
  const el = document.getElementById('member-stats');
  if (!el) return;

  const items = [
    { label: 'Course Diikuti', value: stats.enrolled_courses },
    { label: 'Course Selesai', value: stats.completed_courses },
    { label: 'Materi Tuntas', value: stats.completed_topics },
    { label: 'Waktu Belajar', value: formatMinutes(stats.total_minutes) },
  ];

  el.innerHTML = items.map((item, i) => `
    <div class="text-center p-3 ${i > 0 ? 'border-l border-slate-800/60' : ''}">
      <div class="text-2xl font-black text-white">${item.value}</div>
      <div class="text-xs text-slate-400 mt-0.5">${item.label}</div>
    </div>
  `).join('');
}

function renderContinueLearning(courses) {
  const wrap  = document.getElementById('continue-learning-wrap');
  const grid  = document.getElementById('continue-learning-grid');
  const empty = document.getElementById('continue-learning-empty');
  if (!wrap || !grid) return;

  if (!courses || courses.length === 0) {
    grid.classList.add('hidden');
    empty?.classList.remove('hidden');
    return;
  }

  empty?.classList.add('hidden');
  grid.classList.remove('hidden');

  grid.innerHTML = courses.map((c) => {
    const next = c.next_topic;
    const href = next
      ? `course.php?slug=${encodeURIComponent(c.slug)}&topic=${next.id}`
      : `course.php?slug=${encodeURIComponent(c.slug)}`;

    return `
      <a href="${href}" class="group flex flex-col p-6 rounded-2xl bg-slate-900/60 hover:bg-slate-900/90 border border-slate-800/80 hover:border-blue-500/40 transition-all duration-300 hover:-translate-y-1">
        <div class="flex items-center justify-between mb-4">
          ${dashCoverImageHtml(c, 'bg-blue-500/10 border border-blue-500/20')}
          <span class="text-xs font-bold text-blue-400">${c.progress_percent}%</span>
        </div>
        <h3 class="text-white font-bold text-base mb-1 leading-snug line-clamp-2">${dashEscape(c.title)}</h3>
        <p class="text-xs text-slate-400 mb-4">${c.completed_topics}/${c.topic_count} materi selesai</p>

        <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden mb-4">
          <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-400 rounded-full transition-all" style="width: ${c.progress_percent}%"></div>
        </div>

        ${next ? `
          <div class="mt-auto pt-4 border-t border-slate-800/80">
            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-1">Lanjutkan</p>
            <p class="text-sm text-slate-200 font-medium line-clamp-1 group-hover:text-blue-400 transition-colors">${dashEscape(next.title)}</p>
          </div>
        ` : ''}
      </a>
    `;
  }).join('');
}

function renderRecommended(courses) {
  const wrap  = document.getElementById('recommended-wrap');
  const grid  = document.getElementById('recommended-grid');
  if (!wrap || !grid) return;

  if (!courses || courses.length === 0) {
    wrap.classList.add('hidden');
    return;
  }

  wrap.classList.remove('hidden');
  grid.innerHTML = courses.map((c) => `
    <a href="course.php?slug=${encodeURIComponent(c.slug)}" class="group flex flex-col p-6 rounded-2xl bg-slate-900/60 hover:bg-slate-900/90 border border-slate-800/80 hover:border-indigo-500/40 transition-all duration-300 hover:-translate-y-1">
      <div class="mb-4">${dashCoverImageHtml(c, 'bg-indigo-500/10 border border-indigo-500/20')}</div>
      <h3 class="text-white font-bold text-base mb-1.5 leading-snug line-clamp-2">${dashEscape(c.title)}</h3>
      <p class="text-xs text-slate-400 leading-relaxed mb-4 flex-1 line-clamp-2">${dashEscape(c.description || 'Belum ada deskripsi.')}</p>
      <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">${c.module_count} Modul &middot; ${c.topic_count} Materi</p>
    </a>
  `).join('');
}

function renderRecentActivity(activities) {
  const wrap = document.getElementById('recent-activity-wrap');
  const list = document.getElementById('recent-activity-list');
  if (!wrap || !list) return;

  if (!activities || activities.length === 0) {
    wrap.classList.add('hidden');
    return;
  }

  wrap.classList.remove('hidden');
  list.innerHTML = activities.map((a) => `
    <a href="course.php?slug=${encodeURIComponent(a.course_slug)}" class="flex items-center gap-4 p-4 rounded-xl hover:bg-slate-900/60 transition-colors group">
      <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm text-slate-200 font-medium truncate group-hover:text-blue-400 transition-colors">${dashEscape(a.topic_title)}</p>
        <p class="text-xs text-slate-500 truncate">${dashEscape(a.course_title)}</p>
      </div>
      <span class="text-[11px] text-slate-500 flex-shrink-0">${timeAgo(a.completed_at)}</span>
    </a>
  `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  initHomeView();
});