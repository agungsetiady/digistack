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

// 2. Control Modal UI
function openAuthModal() {
  const modal = document.getElementById('auth-modal');
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden'; // Lock scroll
  }
}

function closeAuthModal() {
  const modal = document.getElementById('auth-modal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = ''; // Restore scroll
    resetAuthStep();
  }
}

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