<?php
// api/courses.php
// GET: Daftar course yang sudah dipublikasikan (untuk katalog di index.php).
// Autentikasi bersifat opsional -- jika user login, response menyertakan progres belajarnya.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/middleware.php';

try {
    $stmt = $pdo->query("
        SELECT
            c.id, c.title, c.slug, c.description, c.icon, c.cover_image, c.created_at,
            COUNT(DISTINCT m.id) AS module_count,
            COUNT(DISTINCT t.id) AS topic_count
        FROM courses c
        LEFT JOIN modules m ON m.course_id = c.id
        LEFT JOIN topics t ON t.module_id = m.id
        WHERE c.is_published = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $courses = $stmt->fetchAll();

    $user = optional_auth();

    foreach ($courses as &$course) {
        $course['id']           = (int) $course['id'];
        $course['module_count'] = (int) $course['module_count'];
        $course['topic_count']  = (int) $course['topic_count'];

        // Ringkas deskripsi panjang agar rapi ditampilkan sebagai card.
        $desc = trim((string) $course['description']);
        if (mb_strlen($desc) > 180) {
            $desc = mb_substr($desc, 0, 180) . '…';
        }
        $course['description'] = $desc;

        $course['progress_percent']  = 0;
        $course['completed_topics']  = 0;
        $course['is_enrolled']       = false;

        if ($user && $course['topic_count'] > 0) {
            $userId = $user['sub'];

            $enrollStmt = $pdo->prepare("SELECT id FROM course_enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
            $enrollStmt->execute([':uid' => $userId, ':cid' => $course['id']]);
            $course['is_enrolled'] = (bool) $enrollStmt->fetch();

            $progStmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_topic_progress utp
                INNER JOIN topics t ON t.id = utp.topic_id
                INNER JOIN modules m ON m.id = t.module_id
                WHERE m.course_id = :cid AND utp.user_id = :uid AND utp.is_completed = 1
            ");
            $progStmt->execute([':cid' => $course['id'], ':uid' => $userId]);
            $completed = (int) $progStmt->fetchColumn();

            $course['completed_topics']  = $completed;
            $course['progress_percent']  = (int) round(($completed / $course['topic_count']) * 100);
        }
    }
    unset($course);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data"   => $courses
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Gagal mengambil data course."
    ]);
}