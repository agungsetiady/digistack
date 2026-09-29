<?php
// api/topic-content.php
// GET ?id=123 atau GET ?slug=nama-slug-topik
// Mengembalikan konten Markdown lengkap 1 topik. WAJIB login (konten materi ter-proteksi).

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

$user   = require_auth();
$userId = $user['sub'];

// Tangkap parameter id atau slug
$topicParam = $_GET['id'] ?? $_GET['slug'] ?? '';
$topicParam = trim((string) $topicParam);

if ($topicParam === '') {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Parameter ID atau Slug topik wajib diisi."]);
    exit;
}

// Tentukan pencarian berdasarkan ID (jika numerik) atau Slug
$isNumeric = is_numeric($topicParam);

try {
    $whereClause = $isNumeric ? "t.id = :param" : "t.slug = :param";

    $stmt = $pdo->prepare("
        SELECT
            t.id, t.title, t.slug, t.estimated_read_time, t.order_position,
            m.id AS module_id, m.title AS module_title,
            c.id AS course_id, c.title AS course_title, c.slug AS course_slug,
            tc.content_markdown, tc.summary_tldr
        FROM topics t
        INNER JOIN modules m ON m.id = t.module_id
        INNER JOIN courses c ON c.id = m.course_id
        LEFT JOIN topic_contents tc ON tc.topic_id = t.id
        WHERE {$whereClause} AND c.is_published = 1
        LIMIT 1
    ");
    
    $stmt->execute([':param' => $topicParam]);
    $topic = $stmt->fetch();

    if (!$topic) {
        http_response_code(404);
        echo json_encode(["status" => "fail", "message" => "Materi tidak ditemukan."]);
        exit;
    }

    $topicId  = (int) $topic['id'];
    $courseId = (int) $topic['course_id'];

    // Pastikan user terdaftar di course ini (defensive auto-enroll)
    $checkEnroll = $pdo->prepare("SELECT id FROM course_enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
    $checkEnroll->execute([':uid' => $userId, ':cid' => $courseId]);
    if (!$checkEnroll->fetch()) {
        $ins = $pdo->prepare("INSERT INTO course_enrollments (user_id, course_id) VALUES (:uid, :cid)");
        $ins->execute([':uid' => $userId, ':cid' => $courseId]);
    }

    $progStmt = $pdo->prepare("SELECT is_completed FROM user_topic_progress WHERE user_id = :uid AND topic_id = :tid LIMIT 1");
    $progStmt->execute([':uid' => $userId, ':tid' => $topicId]);
    $prog = $progStmt->fetch();

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data"   => [
            "id"                  => $topicId,
            "title"               => $topic['title'],
            "slug"                => $topic['slug'] ?? '', // Tambahkan slug topik di response JSON
            "estimated_read_time" => (int) $topic['estimated_read_time'],
            "module_id"           => (int) $topic['module_id'],
            "module_title"        => $topic['module_title'],
            "course_id"           => $courseId,
            "course_title"        => $topic['course_title'],
            "course_slug"         => $topic['course_slug'],
            "content_markdown"    => $topic['content_markdown'] ?? '',
            "summary_tldr"        => $topic['summary_tldr'] ?? '',
            "completed"           => $prog ? (bool) $prog['is_completed'] : false,
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mengambil konten materi."]);
}