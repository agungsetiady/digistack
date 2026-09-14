<!-- components/auth-modal.php -->
<div id="auth-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md hidden items-center justify-center p-4 z-50 opacity-0 transition-opacity duration-300">
  
  <!-- Modal Card (Glassmorphism Concept) -->
  <div id="auth-modal-card" class="bg-white/90 dark:bg-slate-900/90 border border-white/40 dark:border-slate-800 rounded-3xl shadow-2xl backdrop-blur-xl w-full max-w-md p-7 relative overflow-hidden transition-all duration-300 transform scale-95 translate-y-2">
    
    <!-- Ambient Inner Light Effect -->
    <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none animate-pulse"></div>
    <div class="absolute -bottom-24 -left-24 w-40 h-40 bg-indigo-400/10 rounded-full blur-2xl pointer-events-none"></div>

    <!-- Close Button -->
    <button type="button" onclick="closeAuthModal()" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800/60 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-xl transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>

    <!-- Header Title -->
    <div class="mb-6">
      <div class="w-10 h-10 rounded-2xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400 mb-3 font-bold text-lg">
        D
      </div>
      <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Masuk ke DigiStack</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Pilih metode autentikasi untuk mengakses modul.</p>
    </div>

    <!-- Alert Message Container -->
    <div id="auth-alert" class="hidden mb-5 p-3.5 rounded-2xl text-xs font-semibold backdrop-blur-md transition-all"></div>

    <!-- OAuth Buttons Section -->
    <div class="space-y-3 mb-6">
      <button type="button" onclick="handleOAuth('google')" class="w-full flex items-center justify-center gap-3 bg-white dark:bg-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700/80 py-3 rounded-xl font-semibold text-sm shadow-sm transition-all hover:shadow hover:border-slate-300">
        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
        Lanjutkan dengan Google
      </button>

      <button type="button" onclick="handleOAuth('github')" class="w-full flex items-center justify-center gap-3 bg-white dark:bg-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700/80 py-3 rounded-xl font-semibold text-sm shadow-sm transition-all hover:shadow hover:border-slate-300">
        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
        Lanjutkan dengan GitHub
      </button>
    </div>

    <!-- Separator Line -->
    <div class="relative flex py-2 items-center mb-6">
      <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
      <span class="flex-shrink mx-4 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Atau Email OTP</span>
      <div class="flex-grow border-t border-slate-200 dark:border-slate-800"></div>
    </div>

    <!-- Step 1: Form Input Email -->
    <form id="step-email-form" onsubmit="submitEmail(event)">
      <div class="mb-4">
        <label for="user-email" class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Alamat Email</label>
        <input type="email" id="user-email" required placeholder="nama@email.com" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 transition">
      </div>
      <button type="submit" id="btn-send-otp" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 text-sm">
        Kirim Kode OTP
      </button>
    </form>

    <!-- Step 2: Form Input OTP (Default Hidden) -->
    <form id="step-otp-form" class="hidden" onsubmit="verifyOTP(event)">
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 bg-slate-100 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200/50 dark:border-slate-700/50">
        Kode 6-digit dikirim ke: <span id="target-email" class="font-bold text-slate-800 dark:text-slate-200"></span>
      </p>
      
      <div class="mb-4">
        <label for="otp-code" class="block text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-2">Masukkan Kode OTP</label>
        <input type="text" id="otp-code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required placeholder="000000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none text-center tracking-[0.6em] text-2xl font-mono text-slate-800 dark:text-slate-100 placeholder-slate-400 transition">
      </div>
      
      <button type="submit" id="btn-verify-otp" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 text-sm">
        Verifikasi & Masuk
      </button>
      
      <button type="button" onclick="resetAuthStep()" class="w-full text-xs font-semibold text-blue-600 dark:text-blue-400 mt-4 hover:underline text-center block">
        &larr; Ubah Alamat Email
      </button>
    </form>

  </div>
</div>