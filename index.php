<?php require_once __DIR__ . '/admin/config.php';
$base = '/digistack';
?>
<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiStack - Digital Adaptive Learning Platform</title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <link rel="apple-touch-icon" href="<?= $base; ?>/assets/img/aguphia-icon.png">
  <meta name="description" content="Kuasai berbagai keterampilan digital dari dasar hingga mahir dengan DigiStack, platform pembelajaran adaptif yang menyesuaikan materi secara personal sesuai kecepatan belajar Anda.">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              500: '#0284c7',
              600: '#0284c7',
              700: '#0369a1',
            }
          },
          animation: {
            'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            'float': 'float 6s ease-in-out infinite',
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0px)' },
              '50%': { transform: 'translateY(-8px)' },
            }
          }
        }
      }
    }
  </script>

  <style>
    html.light-mode body { background: #f8fafc !important; color: #0f172a !important; }
    html.light-mode header { background: rgba(248,250,252,.88) !important; border-color: #e2e8f0 !important; }
    html.light-mode .text-white { color: #0f172a !important; }
    html.light-mode .text-slate-100 { color: #1e293b !important; }
    html.light-mode .text-slate-200 { color: #334155 !important; }
    html.light-mode .text-slate-300 { color: #475569 !important; }
    html.light-mode .text-slate-400 { color: #64748b !important; }
    html.light-mode .text-slate-500 { color: #64748b !important; }
    html.light-mode .bg-slate-950 { background-color: #f8fafc !important; }
    html.light-mode .bg-slate-900 { background-color: #ffffff !important; }
    html.light-mode .bg-slate-900\/80 { background-color: rgba(255,255,255,.88) !important; }
    html.light-mode .bg-slate-900\/90 { background-color: rgba(255,255,255,.94) !important; }
    html.light-mode .bg-slate-900\/60,
    html.light-mode .bg-slate-900\/40,
    html.light-mode .bg-white\/10 { background-color: rgba(255,255,255,.82) !important; }
    html.light-mode .border-slate-800\/80,
    html.light-mode .border-slate-800\/60,
    html.light-mode .border-white\/10 { border-color: #e2e8f0 !important; }
    html.light-mode .bg-white\/15 { background-color: rgba(255,255,255,.96) !important; }
    html.light-mode footer { background: rgba(255,255,255,.92) !important; border-color: #e2e8f0 !important; }
    html.light-mode .light-grid { opacity: .45; }
    #course-detail-modal {
      transition: opacity .2s ease;
    }

    #course-detail-panel {
      transition: transform .2s ease, opacity .2s ease;
    }

    @media (max-width: 639px) {
      #course-detail-panel {
        width: calc(100% - 24px);
        max-height: 90vh;
        margin: 12px;
        border-radius: 16px;
      }
    }
  </style>
  <script>
    (() => {
      const savedTheme = localStorage.getItem('digistack_theme');
      if (savedTheme === 'light') document.documentElement.classList.add('light-mode');
    })();

    function toggleTheme() {
      const isLight = document.documentElement.classList.toggle('light-mode');
      localStorage.setItem('digistack_theme', isLight ? 'light' : 'dark');
      updateThemeIcon();
    }

    function updateThemeIcon() {
      const button = document.getElementById('theme-toggle');
      if (!button) return;
      const isLight = document.documentElement.classList.contains('light-mode');
      button.innerHTML = isLight
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>'
        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>';
      button.setAttribute('aria-label', isLight ? 'Aktifkan mode malam' : 'Aktifkan mode siang');
      button.title = button.getAttribute('aria-label');
    }

    async function toggleFullscreen() {
      try {
        if (!document.fullscreenElement) {
          await document.documentElement.requestFullscreen();
        } else {
          await document.exitFullscreen();
        }
      } catch (error) {
        console.warn('Fullscreen tidak tersedia:', error);
      }
      updateFullscreenIcon();
    }

    function updateFullscreenIcon() {
      const button = document.getElementById('fullscreen-toggle');
      if (!button) return;
      const active = !!document.fullscreenElement;
      button.innerHTML = active
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m12-6h4a2 2 0 012 2v4M3 15v4a2 2 0 002 2h4m6 0h4a2 2 0 002-2v-4"/></svg>'
        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3H5a2 2 0 00-2 2v3m10-5h3a2 2 0 012 2v3M3 16v3a2 2 0 002 2h3m7 0h3a2 2 0 002-2v-3"/></svg>';
      button.setAttribute('aria-label', active ? 'Keluar dari fullscreen' : 'Masuk fullscreen');
      button.title = button.getAttribute('aria-label');
    }

    document.addEventListener('DOMContentLoaded', () => {
      updateThemeIcon();
      updateFullscreenIcon();
    });
    document.addEventListener('fullscreenchange', updateFullscreenIcon);
  </script>
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-slate-950 selection:bg-blue-500 selection:text-white flex flex-col relative overflow-x-hidden">

  <div class="fixed inset-0 pointer-events-none z-0">
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
    <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-blue-600/15 rounded-full blur-[120px]"></div>
    <div class="absolute top-1/3 -right-40 w-96 h-96 bg-indigo-600/10 rounded-full blur-[120px]"></div>
    <div class="absolute bottom-1/4 -left-40 w-96 h-96 bg-cyan-600/10 rounded-full blur-[120px]"></div>
  </div>

  <header class="sticky top-0 z-40 w-full backdrop-blur-xl bg-slate-950/80 border-b border-slate-800/80 shadow-2xl transition-all">
    <div class="w-full mx-auto h-16 px-6 flex items-center justify-between">
      <a href="<?= $base; ?>/" class="flex items-center gap-2 text-xl font-bold text-white dark:text-white flex-shrink-0" >
        <img src="<?= $base; ?>/assets/img/aguphia-icon.png" class="w-8 h-8 object-contain" alt="DigiStack Logo">
        <span>DigiStack</span>
      </a>

      <nav class="hidden sm:flex items-center gap-6">
        <a href="#katalog" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">Katalog Course</a>
        <a href="#fitur" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Keunggulan</a>
        <a href="#stats" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Ekosistem</a>
      </nav>

      <div class="flex items-center gap-2 sm:gap-3">
        <button id="theme-toggle" type="button" onclick="toggleTheme()" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 hover:text-white bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-slate-600 transition-all" aria-label="Aktifkan mode siang" title="Aktifkan mode siang"></button>
        <button id="fullscreen-toggle" type="button" onclick="toggleFullscreen()" class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-300 hover:text-white bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-slate-600 transition-all" aria-label="Masuk fullscreen" title="Masuk fullscreen"></button>
        <div id="user-profile-widget" class="flex items-center gap-2">
          <button onclick="openAuthModal()" class="px-3 py-2 rounded-xl text-sm font-semibold text-white bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-slate-600 shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98]">
            Masuk
          </button>
        </div>
      </div>
    </div>
  </header>

  <div id="guest-view">
    <main class="flex-1 z-10 flex flex-col items-center justify-center text-center px-4 pt-20 pb-16 w-full mx-auto">
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-900/90 border border-slate-700/80 backdrop-blur-md shadow-xl mb-8 text-xs font-medium text-slate-200 animate-float">
        <span class="flex h-2 w-2 relative">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
        </span>
        <span class="text-slate-400">Pembelajaran Adaptif</span>
        <span class="text-slate-600">•</span>
        <span class="text-blue-400 font-semibold">Digital Adaptive Learning Platform</span>
      </div>

      <h1 class="text-4xl sm:text-6xl md:text-7xl font-black text-white tracking-tight leading-[1.1] mb-8">
        Kuasai Skill Masa Depan <br class="hidden sm:inline" />
        <span class="bg-gradient-to-r from-blue-400 via-indigo-300 to-cyan-300 bg-clip-text text-transparent">
          Hingga Level Profesional
        </span>
      </h1>

      <p class="text-base sm:text-lg text-slate-400 max-w-2xl mb-12 leading-relaxed font-normal">
        Pelajari berbagai keahlian praktis melalui alur belajar terstruktur, modul interaktif, dan studi kasus nyata yang relevan dengan kebutuhan industri.
      </p>

      <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto mb-16">
        <button id="hero-cta-btn" onclick="handleHeroCta()" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-600/25 hover:shadow-blue-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
          <span>Mulai Belajar Sekarang</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>

        <a href="#katalog" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-200 border border-slate-800 hover:border-slate-700 font-semibold text-sm transition-all hover:scale-[1.02]">
          Lihat Katalog Course
        </a>
      </div>

      <div id="stats" class="w-full grid grid-cols-2 md:grid-cols-4 gap-4 p-6 rounded-2xl bg-slate-900/40 border border-slate-800/60 backdrop-blur-xl">
        <div class="text-center p-3">
          <div class="text-2xl font-black text-white">99.8%</div>
          <div class="text-xs text-slate-400 mt-0.5">Uptime Learning Engine</div>
        </div>
        <div class="text-center p-3 border-l border-slate-800/60">
          <div class="text-2xl font-black text-white">100+</div>
          <div class="text-xs text-slate-400 mt-0.5">Materi & Modul Praktis</div>
        </div>
        <div class="text-center p-3 border-l border-slate-800/60">
          <div class="text-2xl font-black text-white">AI Instant</div>
          <div class="text-xs text-slate-400 mt-0.5">Ringkasan TL;DR Otomatis</div>
        </div>
        <div class="text-center p-3 border-l border-slate-800/60">
          <div class="text-2xl font-black text-white">Real-world</div>
          <div class="text-xs text-slate-400 mt-0.5">Studi Kasus di Dunia Nyata</div>
        </div>
      </div>
    </main>

    <section id="fitur" class="relative z-10 w-full mx-auto px-4 py-16">
      <div class="text-center mb-12">
        <h2 class="text-xs font-bold uppercase tracking-widest text-blue-400 mb-2">Mengapa DigiStack?</h2>
        <p class="text-2xl sm:text-3xl font-extrabold text-white">Dirancang untuk Pengalaman Belajar Maksimal</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="group p-8 rounded-2xl bg-slate-900/60 hover:bg-slate-900/90 border border-slate-800/80 hover:border-blue-500/40 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-blue-500/5">
          <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mb-6 text-blue-400 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
          </div>
          <h3 class="text-white font-bold text-lg mb-2">Materi Terstruktur</h3>
          <p class="text-sm text-slate-400 leading-relaxed">Kurikulum bertahap dari Course &rarr; Module &rarr; Topic, disusun rapi mengikuti standar kompetensi industri modern.</p>
        </div>

        <div class="group p-8 rounded-2xl bg-slate-900/60 hover:bg-slate-900/90 border border-slate-800/80 hover:border-indigo-500/40 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-indigo-500/5">
          <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mb-6 text-indigo-400 group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <h3 class="text-white font-bold text-lg mb-2">Ditenagai AI Assistant</h3>
          <p class="text-sm text-slate-400 leading-relaxed">Pahami esensi materi dengan cepat melalui fitur ringkasan TL;DR otomatis yang ditenagai LLM terkini.</p>
        </div>

        <div class="group p-8 rounded-2xl bg-slate-900/60 hover:bg-slate-900/90 border border-slate-800/80 hover:border-cyan-500/40 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-cyan-500/5">
          <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center mb-6 text-cyan-400 group-hover:scale-110 group-hover:bg-cyan-600 group-hover:text-white transition-all duration-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </div>
          <h3 class="text-white font-bold text-lg mb-2">Keamanan & Sesi Seamless</h3>
          <p class="text-sm text-slate-400 leading-relaxed">Sistem autentikasi aman dengan pembaruan progres otomatis tanpa perlu mengulang sesi dari awal.</p>
        </div>
      </div>
    </section>
  </div>

  <div id="member-view" class="hidden relative z-10 w-full mx-auto px-4 pt-12 pb-4">
    <div class="mb-10">
      <h1 id="member-greeting-name" class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-2">Selamat datang kembali!</h1>
      <p id="member-greeting-sub" class="text-sm sm:text-base text-slate-400">Memuat data belajarmu…</p>
    </div>

    <div id="dashboard-loading" class="space-y-6 mb-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 rounded-2xl bg-slate-900/40 border border-slate-800/60 animate-pulse">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="text-center p-3">
          <div class="h-6 bg-slate-800/80 rounded w-1/2 mx-auto mb-2"></div>
          <div class="h-3 bg-slate-800/80 rounded w-3/4 mx-auto"></div>
        </div>
        <?php endfor; ?>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="animate-pulse p-6 rounded-2xl bg-slate-900/40 border border-slate-800/60">
          <div class="w-11 h-11 rounded-xl bg-slate-800/80 mb-5"></div>
          <div class="h-5 bg-slate-800/80 rounded-lg w-3/4 mb-3"></div>
          <div class="h-3 bg-slate-800/80 rounded w-1/2 mb-4"></div>
          <div class="h-1.5 bg-slate-800/80 rounded-full w-full"></div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <div id="dashboard-error" class="hidden text-center py-16 px-6 rounded-2xl bg-slate-900/40 border border-slate-800/60 mb-4">
      <p class="text-white font-semibold text-base">Gagal memuat data dashboard.</p>
      <p class="text-slate-400 text-sm mt-1">Periksa koneksi kamu, lalu muat ulang halaman.</p>
    </div>

    <div id="dashboard-content" class="hidden space-y-14">
      <div id="member-stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 rounded-2xl bg-slate-900/40 border border-slate-800/60 backdrop-blur-xl"></div>
      <section id="continue-learning-wrap">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Lanjutkan Belajar</h2>
        </div>
        <div id="continue-learning-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"></div>
        <div id="continue-learning-empty" class="hidden text-center py-14 px-6 rounded-2xl bg-slate-900/40 border border-slate-800/60">
          <p class="text-white font-semibold text-base">Belum ada course yang sedang berjalan.</p>
          <p class="text-slate-400 text-sm mt-1">Pilih course dari katalog di bawah untuk mulai belajar.</p>
        </div>
      </section>

      <section id="recommended-wrap" class="hidden">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Rekomendasi Untukmu</h2>
            <p class="text-sm text-slate-400 mt-1">Course lain yang sepadan dengan jalur belajarmu.</p>
          </div>
        </div>
        <div id="recommended-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"></div>
      </section>

      <section id="recent-activity-wrap" class="hidden">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Aktivitas Terakhir</h2>
        </div>
        <div id="recent-activity-list" class="rounded-2xl bg-slate-900/40 border border-slate-800/60 divide-y divide-slate-800/60 overflow-hidden"></div>
      </section>

    </div>
  </div>

  <section id="katalog" class="relative z-10 w-full mx-auto px-4 py-16">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-10 pb-6 border-b border-slate-800/80">
      <div>
        <h2 id="catalog-heading" class="text-2xl sm:text-3xl font-black text-white tracking-tight">Katalog Course</h2>
        <p id="catalog-subtitle" class="text-sm text-slate-400 mt-1">Pilih materi yang ingin Anda kuasai. Progres belajar tersimpan secara otomatis.</p>
      </div>
    </div>

    <div id="catalog-loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php for ($i = 0; $i < 3; $i++): ?>
      <div class="animate-pulse p-6 rounded-2xl bg-slate-900/40 border border-slate-800/60">
        <div class="w-12 h-12 rounded-xl bg-slate-800/80 mb-5"></div>
        <div class="h-5 bg-slate-800/80 rounded-lg w-3/4 mb-3"></div>
        <div class="h-3.5 bg-slate-800/80 rounded w-full mb-2"></div>
        <div class="h-3.5 bg-slate-800/80 rounded w-5/6 mb-8"></div>
        <div class="h-11 bg-slate-800/80 rounded-xl w-full"></div>
      </div>
      <?php endfor; ?>
    </div>

    <div id="catalog-empty" class="hidden text-center py-20 px-6 rounded-2xl bg-slate-900/40 border border-slate-800/60">
      <div class="w-16 h-16 bg-slate-800/50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      </div>
      <p class="text-white font-semibold text-base">Belum ada course yang tersedia saat ini.</p>
      <p class="text-slate-400 text-sm mt-1">Silakan cek kembali beberapa saat lagi.</p>
    </div>

    <div id="catalog-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"></div>
  </section>

  <!-- Course Detail Modal -->
  <div id="course-detail-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md hidden flex items-center justify-center p-2 z-[90] opacity-0 transition-opacity duration-300">
    <div id="course-detail-backdrop" class="absolute inset-0"></div>

    <!-- Modal Card (Glassmorphism Concept) -->
    <div id="course-detail-panel" class="bg-white/90 dark:bg-slate-900/90 border border-white/40 dark:border-slate-800 rounded-3xl shadow-2xl backdrop-blur-xl w-full max-w-2xl relative overflow-hidden transition-all duration-300 transform scale-95 translate-y-2 flex flex-col max-h-[90vh] z-10">
      
      <!-- Ambient Inner Light Effect -->
      <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none animate-pulse"></div>
      <div class="absolute -bottom-24 -left-24 w-40 h-40 bg-indigo-400/10 rounded-full blur-2xl pointer-events-none"></div>

      <!-- Close Button -->
      <button id="course-detail-close" type="button" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800/60 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-xl transition z-20" aria-label="Tutup detail course">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- Header -->
      <div class="p-6 sm:p-7 pb-2 border-b border-slate-200/60 dark:border-slate-800/80 flex items-start gap-4 pr-14 relative z-10">
        <div class="min-w-0">
          <h2 id="course-detail-title" class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-snug"></h2>
        </div>
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
          <p id="course-detail-description" class="text-sm leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line"></p>
        </div>
      </div>

      <!-- Sticky Footer CTA -->
      <div class="p-5 sm:p-6 border-t border-slate-200/60 dark:border-slate-800/80 bg-white/50 dark:bg-slate-900/50 backdrop-blur-md relative z-10">
        <button id="course-detail-start" type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 text-sm active:scale-[0.99]">
          Mulai Belajar
        </button>
      </div>

    </div>
  </div>

  <footer class="z-10 py-8 text-center text-xs text-slate-500 border-t border-slate-900 bg-slate-950/90 backdrop-blur-md">
    <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div>&copy; <?= date('Y') ?> DigiStack. All rights reserved.</div>
      <div class="flex items-center gap-6">
        <a href="#" class="hover:text-slate-300 transition-colors">Privacy Policy</a>
        <a href="#" class="hover:text-slate-300 transition-colors">Terms of Service</a>
        <a href="#" class="hover:text-slate-300 transition-colors">Support</a>
      </div>
    </div>
  </footer>

  <?php
    $modalPath = __DIR__ . '/components/auth-modal.php';
    if (file_exists($modalPath)) {
        include $modalPath;
    }
  ?>

  <script src="assets/js/auth.js"></script>
  <script src="assets/js/course-catalog.js"></script>
  <script src="assets/js/dashboard.js"></script>
</body>
</html>