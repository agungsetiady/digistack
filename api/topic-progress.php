<?php
// api/topic-progress.php
// POST { topic_id: number, completed: boolean }
// Menandai topik selesai/belum untuk user yang sedang login.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "fail", "message" => "Method tidak diizinkan."]);
    exit;
}

$user   = require_auth();
$userId = $user['sub'];

$data      = json_decode(file_get_contents("php://input"));
$topicId   = isset($data->topic_id) ? (int) $data->topic_id : 0;
$completed = !isset($data->completed) || (bool) $data->completed; // default true

if ($topicId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Parameter topic_id wajib diisi."]);
    exit;
}

try {
    // Pastikan topik valid & course-nya published
    $check = $pdo->prepare("
        SELECT t.id, m.course_id
        FROM topics t
        INNER JOIN modules m ON m.id = t.module_id
        INNER JOIN courses c ON c.id = m.course_id
        WHERE t.id = :tid AND c.is_published = 1
        LIMIT 1
    ");
    $check->execute([':tid' => $topicId]);
    $topicRow = $check->fetch();

    if (!$topicRow) {
        http_response_code(404);
        echo json_encode(["status" => "fail", "message" => "Materi tidak ditemukan."]);
        exit;
    }

    $existing = $pdo->prepare("SELECT id FROM user_topic_progress WHERE user_id = :uid AND topic_id = :tid LIMIT 1");
    $existing->execute([':uid' => $userId, ':tid' => $topicId]);
    $row = $existing->fetch();

    $completedAt = $completed ? date('Y-m-d H:i:s') : null;

    if ($row) {
        $upd = $pdo->prepare("UPDATE user_topic_progress SET is_completed = :done, completed_at = :ts WHERE id = :id");
        $upd->execute([
            ':done' => $completed ? 1 : 0,
            ':ts'   => $completedAt,
            ':id'   => $row['id'],
        ]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO user_topic_progress (user_id, topic_id, is_completed, completed_at)
            VALUES (:uid, :tid, :done, :ts)
        ");
        $ins->execute([
            ':uid'  => $userId,
            ':tid'  => $topicId,
            ':done' => $completed ? 1 : 0,
            ':ts'   => $completedAt,
        ]);
    }

    // Hitung ulang progres keseluruhan course untuk dikembalikan ke client
    $courseId = (int) $topicRow['course_id'];
    $totalStmt = $pdo->prepare("
        SELECT COUNT(*) FROM topics t INNER JOIN modules m ON m.id = t.module_id WHERE m.course_id = :cid
    ");
    $totalStmt->execute([':cid' => $courseId]);
    $total = (int) $totalStmt->fetchColumn();

    $doneStmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_topic_progress utp
        INNER JOIN topics t ON t.id = utp.topic_id
        INNER JOIN modules m ON m.id = t.module_id
        WHERE m.course_id = :cid AND utp.user_id = :uid AND utp.is_completed = 1
    ");
    $doneStmt->execute([':cid' => $courseId, ':uid' => $userId]);
    $done = (int) $doneStmt->fetchColumn();

    http_response_code(200);
    echo json_encode([
        "status"    => "success",
        "message"   => $completed ? "Materi ditandai selesai." : "Materi ditandai belum selesai.",
        "completed" => $completed,
        "course_progress" => [
            "completed_topics" => $done,
            "total_topics"     => $total,
            "progress_percent" => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan progres."]);
}