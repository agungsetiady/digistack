<?php require_once __DIR__ . '/admin/config.php'; ?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiStack - Platform E-Learning Modern</title>
  <meta name="description" content="Kuasai arsitektur REST API, OOP PHP Modern, dan sistem modul interaktif berbasis studi kasus riil industri di DigiStack.">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#eff6ff',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-slate-900 selection:bg-blue-500 selection:text-white flex flex-col relative overflow-x-hidden">

  <!-- Aesthetic Ambient Background Glows -->
  <div class="fixed inset-0 pointer-events-none z-0">
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
    <div class="absolute top-1/3 -right-40 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-cyan-500/15 rounded-full blur-3xl"></div>
  </div>

  <!-- Header / Navbar (White Glassmorphism) -->
  <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-white/70 border-b border-white/20 shadow-sm transition-all">
    <div class="max-w-7xl mx-auto h-16 px-6 flex items-center justify-between">

      <!-- Brand Logo & Home Link -->
      <a href="./" class="flex items-center gap-2 group">
        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-white font-black text-lg shadow-md group-hover:scale-105 transition-transform">
          D
        </div>
        <span class="text-xl font-bold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent tracking-tight">
          DigiStack
        </span>
      </a>

      <div class="hidden sm:flex items-center gap-1">
        <a href="#katalog" class="px-3 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900 rounded-lg hover:bg-slate-100/70 transition-colors">Katalog Course</a>
      </div>

      <!-- Action Button / User Widget (diisi dinamis oleh auth.js) -->
      <div class="flex items-center gap-4">
        <div id="user-profile-widget" class="flex items-center gap-2">
          <button onclick="openAuthModal()" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:text-slate-900 bg-white/80 hover:bg-white border border-slate-200/80 hover:border-slate-300 shadow-sm transition-all hover:shadow">
            Masuk / Daftar
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <main class="flex-1 z-10 flex flex-col items-center justify-center text-center px-4 py-16 max-w-4xl mx-auto">

    <!-- Badge Status -->
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/60 border border-white/40 backdrop-blur-md shadow-sm mb-6 text-xs font-semibold text-blue-700">
      <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
      Platform E-Learning Tech & Architecture
    </div>

    <!-- Heading -->
    <h1 class="text-4xl sm:text-5xl md:text-6xl font-black text-white tracking-tight leading-tight mb-6">
      Tingkatkan Skill Coding <br class="hidden sm:inline" />
      <span class="bg-gradient-to-r from-blue-400 via-indigo-300 to-cyan-300 bg-clip-text text-transparent">
        Hingga Level Advanced
      </span>
    </h1>

    <!-- Description -->
    <p class="text-base sm:text-lg text-slate-300 max-w-2xl mb-10 leading-relaxed">
      Kuasai arsitektur REST API, OOP PHP Modern, dan sistem modul interaktif berbasis studi kasus riil industri.
    </p>

    <!-- Call to Actions -->
    <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto">
      <button id="hero-cta-btn" onclick="handleHeroCta()" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
        Mulai Belajar Sekarang
      </button>

      <a href="#katalog" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 backdrop-blur-md font-semibold text-sm transition-all">
        Lihat Katalog Course
      </a>
    </div>
  </main>

  <!-- Feature Highlights (Glassmorphism Cards, Interactive Hover) -->
  <section class="relative z-10 w-full max-w-6xl mx-auto px-4 pb-16 grid grid-cols-1 sm:grid-cols-3 gap-5">
    <div class="group p-6 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 backdrop-blur-xl shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-blue-500/10">
      <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center mb-4 text-blue-300 group-hover:scale-110 transition-transform">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      </div>
      <h3 class="text-white font-bold mb-1.5">Materi Terstruktur</h3>
      <p class="text-sm text-slate-300/90 leading-relaxed">Kurikulum bertahap dari Course → Module → Topic, disusun berdasarkan studi kasus industri nyata.</p>
    </div>
    <div class="group p-6 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 backdrop-blur-xl shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-indigo-500/10">
      <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center mb-4 text-indigo-300 group-hover:scale-110 transition-transform">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
      </div>
      <h3 class="text-white font-bold mb-1.5">Ditenagai AI</h3>
      <p class="text-sm text-slate-300/90 leading-relaxed">Ringkasan TL;DR otomatis dan bantuan belajar dari provider AI seperti OpenAI, Gemini, dan Groq.</p>
    </div>
    <div class="group p-6 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 backdrop-blur-xl shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-cyan-500/10">
      <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center mb-4 text-cyan-300 group-hover:scale-110 transition-transform">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <h3 class="text-white font-bold mb-1.5">Login Fleksibel</h3>
      <p class="text-sm text-slate-300/90 leading-relaxed">Masuk cepat tanpa password lewat OTP email, atau satu klik dengan akun Google / GitHub.</p>
    </div>
  </section>

  <!-- ===================== Katalog Course (Dinamis dari Database) ===================== -->
  <section id="katalog" class="relative z-10 w-full max-w-6xl mx-auto px-4 pb-24">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
      <div>
        <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Katalog Course</h2>
        <p id="catalog-subtitle" class="text-sm text-slate-300/90 mt-1">Pilih course dan mulai belajar. Progres kamu akan otomatis tersimpan.</p>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div id="catalog-loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php for ($i = 0; $i < 3; $i++): ?>
      <div class="animate-pulse p-6 rounded-2xl bg-white/10 border border-white/10">
        <div class="w-10 h-10 rounded-xl bg-white/10 mb-4"></div>
        <div class="h-4 bg-white/10 rounded w-3/4 mb-3"></div>
        <div class="h-3 bg-white/10 rounded w-full mb-2"></div>
        <div class="h-3 bg-white/10 rounded w-5/6 mb-6"></div>
        <div class="h-9 bg-white/10 rounded-xl w-full"></div>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Empty / Error State -->
    <div id="catalog-empty" class="hidden text-center py-16 px-6 rounded-2xl bg-white/5 border border-white/10">
      <p class="text-slate-300 font-medium">Belum ada course yang tersedia saat ini.</p>
      <p class="text-slate-400 text-sm mt-1">Silakan cek kembali beberapa saat lagi.</p>
    </div>

    <!-- Dynamic Grid Container (diisi oleh course-catalog.js) -->
    <div id="catalog-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5"></div>
  </section>

  <!-- Footer Minimalis -->
  <footer class="z-10 py-6 text-center text-xs text-slate-400 border-t border-white/10 backdrop-blur-sm">
    &copy; <?= date('Y') ?> DigiStack. All rights reserved.
  </footer>

  <!-- Component Auth Modal (Internal Include) -->
  <?php
    $modalPath = __DIR__ . '/components/auth-modal.php';
    if (file_exists($modalPath)) {
        include $modalPath;
    }
  ?>

  <!-- External JS (openAuthModal/closeAuthModal & user-session widget ada di sini) -->
  <script src="assets/js/auth.js"></script>
  <script src="assets/js/course-catalog.js"></script>
</body>
</html>