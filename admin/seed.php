<?php
require_once __DIR__ . '/config.php';

try {
    // Data admin baru
    $name     = 'Administrator';
    $email    = 'admin@gmail.com';
    $password = 'admin321';
    $role     = 'admin';

    // Hash password secara aman
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Cek apakah email sudah terdaftar
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update password jika user sudah ada
        $update = $pdo->prepare("UPDATE admins SET password = :password, role = :role, updated_at = NOW() WHERE email = :email");
        $update->execute([
            'password' => $hashed_password,
            'role'     => $role,
            'email'    => $email
        ]);
        echo "✅ User <b>{$email}</b> sudah ada, password berhasil diperbarui!";
    } else {
        // Insert user baru
        $insert = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (:name, :email, :password, :role)");
        $insert->execute([
            'name'     => $name,
            'email'    => $email,
            'password' => $hashed_password,
            'role'     => $role
        ]);
        echo "✅ User Admin berhasil dibuat!";
    }

    echo "<br><br><b>Kredensial Login:</b><br>";
    echo "Email: <code>{$email}</code><br>";
    echo "Password: <code>{$password}</code><br>";
    echo "<a href='" . admin_url('login') . "'>Ke Halaman Login</a>";

} catch (\PDOException $e) {
    die("❌ Gagal membuat user: " . $e->getMessage());
}