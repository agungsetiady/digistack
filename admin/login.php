<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        // Query disesuaikan ke tabel `admins`
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $user['id'];
            $_SESSION['admin_name']      = $user['name'];
            $_SESSION['admin_email']     = $user['email'];
            $_SESSION['admin_role']      = $user['role'];

            header('Location: ' . admin_url());
            exit;
        } else {
            $error = 'Email atau password tidak sesuai.';
        }
    } else {
        $error = 'Harap isi semua kolom.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Digistack Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-slate-800/80 border border-slate-700/60 backdrop-blur-xl rounded-2xl p-8 shadow-2xl">
        <div class="flex flex-col items-center mb-8">
            <div class="w-20 h-20 flex items-center justify-center text-white text-2xl mb-0">
                <img src="../assets/img/aguphia-icon.png" class="w-20 h-20">
            </div>
            <h1 class="text-xl font-bold tracking-tight">Digistack</h1>
            <p class="text-xs text-slate-400 mt-1">Digital Learning Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2.5">
                <i class='bx bx-error-circle text-lg'></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= admin_url('login') ?>" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email</label>
                <div class="relative">
                    <i class='bx bx-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg'></i>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full pl-10 pr-4 py-2.5 bg-slate-900/60 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <i class='bx bx-lock-alt absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg'></i>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 bg-slate-900/60 border border-slate-700 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2 mt-2">
                <span>Masuk Sekarang</span>
                <i class='bx bx-right-arrow-alt text-xl'></i>
            </button>
        </form>
    </div>

</body>
</html>