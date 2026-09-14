<?php
// api/course-detail.php
// GET ?slug=xxx  atau  ?id=123
// Mengembalikan struktur lengkap course (modules + topics) beserta progres user yang login.
// Endpoint ini WAJIB login (dipakai oleh course.php untuk memuat sidebar).

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

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($slug === '' && $id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "fail", "message" => "Parameter slug atau id wajib diisi."]);
    exit;
}

try {
    if ($slug !== '') {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE slug = :slug AND is_published = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id AND is_published = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
    }
    $course = $stmt->fetch();

    if (!$course) {
        http_response_code(404);
        echo json_encode(["status" => "fail", "message" => "Course tidak ditemukan atau belum dipublikasikan."]);
        exit;
    }

    $courseId = (int) $course['id'];

    // Auto-enroll user ke course ini (idempotent, tabel tidak punya unique key jadi cek manual dulu)
    $checkEnroll = $pdo->prepare("SELECT id FROM course_enrollments WHERE user_id = :uid AND course_id = :cid LIMIT 1");
    $checkEnroll->execute([':uid' => $userId, ':cid' => $courseId]);
    if (!$checkEnroll->fetch()) {
        $ins = $pdo->prepare("INSERT INTO course_enrollments (user_id, course_id) VALUES (:uid, :cid)");
        $ins->execute([':uid' => $userId, ':cid' => $courseId]);
    }

    // Ambil semua module
    $modStmt = $pdo->prepare("
        SELECT id, title, slug, description, order_position
        FROM modules
        WHERE course_id = :cid
        ORDER BY order_position ASC, id ASC
    ");
    $modStmt->execute([':cid' => $courseId]);
    $modules = $modStmt->fetchAll();

    // Ambil semua topic dalam course ini sekaligus (hindari N+1 query)
    $topicStmt = $pdo->prepare("
        SELECT t.id, t.module_id, t.title, t.slug, t.order_position, t.estimated_read_time
        FROM topics t
        INNER JOIN modules m ON m.id = t.module_id
        WHERE m.course_id = :cid
        ORDER BY m.order_position ASC, t.order_position ASC, t.id ASC
    ");
    $topicStmt->execute([':cid' => $courseId]);
    $topics = $topicStmt->fetchAll();

    // Topic yang sudah diselesaikan user ini
    $progStmt = $pdo->prepare("
        SELECT utp.topic_id
        FROM user_topic_progress utp
        INNER JOIN topics t ON t.id = utp.topic_id
        INNER JOIN modules m ON m.id = t.module_id
        WHERE m.course_id = :cid AND utp.user_id = :uid AND utp.is_completed = 1
    ");
    $progStmt->execute([':cid' => $courseId, ':uid' => $userId]);
    $completedIds = array_map('intval', array_column($progStmt->fetchAll(), 'topic_id'));

    // Kelompokkan topics per module_id
    $topicsByModule = [];
    foreach ($topics as $t) {
        $tid = (int) $t['id'];
        $topicsByModule[(int) $t['module_id']][] = [
            'id'                   => $tid,
            'title'                => $t['title'],
            'slug'                 => $t['slug'],
            'order_position'       => (int) $t['order_position'],
            'estimated_read_time'  => (int) $t['estimated_read_time'],
            'completed'            => in_array($tid, $completedIds, true),
        ];
    }

    $resultModules = [];
    foreach ($modules as $m) {
        $mid = (int) $m['id'];
        $resultModules[] = [
            'id'             => $mid,
            'title'          => $m['title'],
            'slug'           => $m['slug'],
            'description'    => $m['description'],
            'order_position' => (int) $m['order_position'],
            'topics'         => $topicsByModule[$mid] ?? [],
        ];
    }

    $totalTopics    = count($topics);
    $completedCount = count($completedIds);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data"   => [
            "id"                => $courseId,
            "title"             => $course['title'],
            "slug"              => $course['slug'],
            "description"       => $course['description'],
            "icon"              => $course['icon'],
            "cover_image"       => $course['cover_image'],
            "modules"           => $resultModules,
            "total_topics"      => $totalTopics,
            "completed_topics"  => $completedCount,
            "progress_percent"  => $totalTopics > 0 ? (int) round(($completedCount / $totalTopics) * 100) : 0,
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mengambil data course."]);
}