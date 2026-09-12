<!-- Toast Notification Container -->
<div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    
    const isSuccess = type === 'success';
    const bgColor = isSuccess ? 'bg-slate-900 border-emerald-500/50 text-emerald-400' : 'bg-slate-900 border-rose-500/50 text-rose-400';
    const icon = isSuccess ? 'bx-check-circle' : 'bx-error-circle';

    toast.className = `pointer-events-auto flex items-center gap-3 py-3 px-4 rounded-xl border ${bgColor} text-xs font-semibold shadow-2xl backdrop-blur-xl transform transition-all duration-300 translate-y-5 opacity-0`;
    toast.innerHTML = `<i class='bx ${icon} text-lg'></i><span>${message}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-y-5', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-5', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>