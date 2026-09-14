<?php require_once __DIR__ . '/admin/config.php'; ?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DigiStack - Platform E-Learning Modern</title>
  
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

      <!-- Action Button -->
      <div class="flex items-center gap-4">
        <button onclick="openAuthModal()" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:text-slate-900 bg-white/80 hover:bg-white border border-slate-200/80 hover:border-slate-300 shadow-sm transition-all hover:shadow">
          Masuk / Daftar
        </button>
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
      <button onclick="openAuthModal()" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
        Mulai Belajar Sekarang
      </button>
      
      <a href="course/rest-api-php" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 backdrop-blur-md font-semibold text-sm transition-all">
        Lihat Sample Course
      </a>
    </div>
  </main>

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

  <!-- Fallback Safety Script for Auth Modal Trigger -->
  <script>
    function openAuthModal() {
      const modal = document.getElementById('auth-modal');
      if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      } else {
        alert('Modal autentikasi belum siap atau file components/auth-modal.php belum dimuat.');
      }
    }
  </script>

  <!-- External JS (Dipanggil Secara Relatif Langsung dari Root Folder) -->
  <script src="assets/js/auth.js"></script>
</body>
</html>