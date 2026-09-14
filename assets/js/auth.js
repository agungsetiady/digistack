// assets/js/auth.js

// 1. Deteksi BASE_URL secara presisi (Support Root & Subfolder)
const getBaseUrl = () => {
  const pathParts = window.location.pathname.split('/').filter(Boolean);
  // Jika berjalan di subfolder (misal: /digistack/...)
  // Kita ambil folder pertama jika bukan file .php atau routing khusus
  const isSubfolder = pathParts.length > 0 && !pathParts[0].includes('.php');
  const appPath = isSubfolder ? `/${pathParts[0]}` : '';
  return `${window.location.origin}${appPath}/api`;
};

const API_BASE_URL = getBaseUrl();

// 2. Control Modal UI (dengan animasi fade + scale)
function openAuthModal() {
  const modal = document.getElementById('auth-modal');
  const card = document.getElementById('auth-modal-card');
  if (!modal) return;

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.style.overflow = 'hidden'; // Lock scroll

  // Trigger transition di frame berikutnya
  requestAnimationFrame(() => {
    modal.classList.remove('opacity-0');
    if (card) card.classList.remove('scale-95', 'translate-y-2');
  });
}

function closeAuthModal() {
  const modal = document.getElementById('auth-modal');
  const card = document.getElementById('auth-modal-card');
  if (!modal) return;

  modal.classList.add('opacity-0');
  if (card) card.classList.add('scale-95', 'translate-y-2');

  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = ''; // Restore scroll
    resetAuthStep();
  }, 250);
}

// Tutup modal dengan tombol ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('auth-modal');
    if (modal && !modal.classList.contains('hidden')) closeAuthModal();
  }
});

function resetAuthStep() {
  const emailForm = document.getElementById('step-email-form');
  const otpForm = document.getElementById('step-otp-form');
  const alertBox = document.getElementById('auth-alert');

  if (emailForm) emailForm.classList.remove('hidden');
  if (otpForm) otpForm.classList.add('hidden');
  if (alertBox) {
    alertBox.classList.add('hidden');
    alertBox.className = 'hidden mb-5 p-3.5 rounded-2xl text-xs font-semibold backdrop-blur-md transition-all';
  }
  
  const otpInput = document.getElementById('otp-code');
  if (otpInput) otpInput.value = '';
}

// 3. Helper Alert/Notification Box
function showAlert(message, type = 'error') {
  const alertBox = document.getElementById('auth-alert');
  if (!alertBox) return;

  alertBox.innerText = message;
  alertBox.classList.remove('hidden');

  if (type === 'success') {
    alertBox.className = 'mb-5 p-3.5 rounded-2xl text-xs font-semibold bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 block';
  } else {
    alertBox.className = 'mb-5 p-3.5 rounded-2xl text-xs font-semibold bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 block';
  }
}

// 4. Handler OAuth Google & GitHub
function handleOAuth(provider) {
  window.location.href = `${API_BASE_URL}/auth/${provider}/redirect`;
}

function base64UrlDecode(str) {
  let base64 = str.replace(/-/g, '+').replace(/_/g, '/');
  const padLength = base64.length % 4;
  if (padLength === 2) base64 += '==';
  else if (padLength === 3) base64 += '=';
  else if (padLength !== 0) throw new Error('Base64url tidak valid.');

  const binary = atob(base64);
  const percentEncoded = binary
    .split('')
    .map((c) => '%' + c.charCodeAt(0).toString(16).padStart(2, '0'))
    .join('');
  return decodeURIComponent(percentEncoded);
}

function getStoredUser() {
  try {
    const raw = localStorage.getItem('digistack_user');
    const token = localStorage.getItem('digistack_token');
    if (!raw || !token) return null;

    // Cek token belum expired (baca payload JWT tanpa perlu verifikasi
    // signature di client — verifikasi asli tetap di server tiap request API).
    const parts = token.split('.');
    if (parts.length === 3) {
      const payload = JSON.parse(base64UrlDecode(parts[1]));
      if (payload.exp && Date.now() / 1000 > payload.exp) {
        clearSession();
        return null;
      }
    }
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

function clearSession() {
  localStorage.removeItem('digistack_token');
  localStorage.removeItem('digistack_user');
}

function logoutUser() {
  clearSession();
  window.location.reload();
}

function initials(name) {
  if (!name) return '?';
  return name.trim().charAt(0).toUpperCase();
}

// 4c. Render tombol "Masuk" ATAU widget profil user di header ---------
function renderAuthWidget() {
  const widget = document.getElementById('user-profile-widget');
  if (!widget) return;

  const user = getStoredUser();

  if (!user) {
    widget.innerHTML = `
      <button onclick="openAuthModal()" class="px-3 py-1 rounded-xl text-sm font-semibold text-slate-700 hover:text-slate-900 bg-white hover:bg-white/90 border border-slate-200/80 hover:border-slate-300 shadow-sm transition-all hover:shadow">
        Masuk
      </button>`;
    return;
  }

  const avatarHtml = user.avatar
    ? `<img src="${user.avatar}" alt="${user.name}" class="w-8 h-8 rounded-full object-cover ring-2 ring-white/60">`
    : `<div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white flex items-center justify-center text-xs font-bold ring-2 ring-white/60">${initials(user.name)}</div>`;

  widget.innerHTML = `
    <div class="relative">
      <button onclick="toggleUserMenu(event)" id="user-menu-trigger" class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 shadow-sm transition-all hover:shadow">
        ${avatarHtml}
        <span class="text-sm font-semibold text-slate-700 max-w-[110px] truncate">${user.name}</span>
        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-52 rounded-2xl bg-white/90 backdrop-blur-xl border border-white/40 shadow-2xl overflow-hidden z-50">
        <div class="px-4 py-3 border-b border-slate-100">
          <p class="text-sm font-bold text-slate-800 truncate">${user.name}</p>
          <p class="text-xs text-slate-500 truncate">${user.email}</p>
        </div>
        <button onclick="logoutUser()" class="w-full text-left px-4 py-2.5 text-sm font-medium text-rose-600 hover:bg-rose-50 transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Keluar
        </button>
      </div>
    </div>`;
}

function toggleUserMenu(e) {
  e.stopPropagation();
  const dropdown = document.getElementById('user-menu-dropdown');
  if (dropdown) dropdown.classList.toggle('hidden');
}

document.addEventListener('click', () => {
  const dropdown = document.getElementById('user-menu-dropdown');
  if (dropdown) dropdown.classList.add('hidden');
});

// 4d. Tampilkan pesan error dari redirect OAuth (mis. GitHub belum dikonfigurasi)
function showOAuthErrorFromQuery() {
  const params = new URLSearchParams(window.location.search);
  const err = params.get('auth_error');
  if (!err) return;

  openAuthModal();
  showAlert(decodeURIComponent(err), 'error');

  // Bersihkan query string supaya pesan tidak muncul lagi saat refresh
  params.delete('auth_error');
  const newUrl = window.location.pathname + (params.toString() ? `?${params}` : '');
  window.history.replaceState({}, document.title, newUrl);
}

document.addEventListener('DOMContentLoaded', () => {
  renderAuthWidget();
  showOAuthErrorFromQuery();
});

// 5. Submit Email & Request OTP
async function submitEmail(event) {
  event.preventDefault();
  const emailInput = document.getElementById('user-email');
  const btn = document.getElementById('btn-send-otp');
  const email = emailInput.value.trim();

  if (!email) return;

  btn.disabled = true;
  btn.innerHTML = `
    <span class="inline-flex items-center gap-2">
      <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Mengirim OTP...
    </span>
  `;

  try {
    const response = await fetch(`${API_BASE_URL}/auth/send-otp`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email })
    });

    const result = await response.json();

    if (response.ok) {
      document.getElementById('target-email').innerText = email;
      document.getElementById('step-email-form').classList.add('hidden');
      document.getElementById('step-otp-form').classList.remove('hidden');
      
      // Jika mode testing lokal (debug_otp ada di response)
      if (result.debug_otp) {
        console.log(`[DEBUG OTP]: ${result.debug_otp}`);
      }
      
      showAlert('Kode OTP berhasil dikirim ke email kamu.', 'success');
    } else {
      showAlert(result.message || 'Gagal mengirim OTP. Silakan coba lagi.');
    }
  } catch (error) {
    showAlert('Gagal terhubung ke server backend.');
  } finally {
    btn.disabled = false;
    btn.innerText = 'Kirim Kode OTP';
  }
}

// 6. Verify OTP Code
async function verifyOTP(event) {
  event.preventDefault();
  const email = document.getElementById('user-email').value.trim();
  const otp = document.getElementById('otp-code').value.trim();
  const btn = document.getElementById('btn-verify-otp');

  if (!otp || otp.length !== 6) {
    showAlert('Masukkan 6 digit kode OTP yang valid.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = `
    <span class="inline-flex items-center gap-2">
      <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Memverifikasi...
    </span>
  `;

  try {
    const response = await fetch(`${API_BASE_URL}/auth/verify-otp`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, otp })
    });

    const result = await response.json();

    if (response.ok && result.token) {
      localStorage.setItem('digistack_token', result.token);
      localStorage.setItem('digistack_user', JSON.stringify(result.user));
      showAlert('Autentikasi berhasil! Mengalihkan...', 'success');
      
      setTimeout(() => {
        window.location.reload();
      }, 800);
    } else {
      showAlert(result.message || 'Kode OTP tidak cocok atau sudah kadaluarsa.');
    }
  } catch (error) {
    showAlert('Terjadi kesalahan saat verifikasi OTP.');
  } finally {
    btn.disabled = false;
    btn.innerText = 'Verifikasi & Masuk';
  }
}

// Close Modal when clicking outside container
document.addEventListener('click', (e) => {
  const modal = document.getElementById('auth-modal');
  if (e.target === modal) {
    closeAuthModal();
  }
});