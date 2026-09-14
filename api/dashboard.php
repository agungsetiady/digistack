<?php
// api/dashboard.php
// GET (WAJIB LOGIN)
// Mengembalikan data personalisasi untuk halaman index.php saat user sudah login:
// - ringkasan statistik belajar
// - course yang sedang berjalan ("Lanjutkan Belajar") beserta materi selanjutnya
// - rekomendasi course yang belum diikuti
// - aktivitas belajar terakhir

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
$userId = (int) $user['sub'];

try {
    // ------------------------------------------------------------------
    // 1. Profil singkat user
    // ------------------------------------------------------------------
    $userStmt = $pdo->prepare("SELECT id, name, email, avatar, created_at FROM users WHERE id = :uid LIMIT 1");
    $userStmt->execute([':uid' => $userId]);
    $userRow = $userStmt->fetch();

    if (!$userRow) {
        http_response_code(404);
        echo json_encode(["status" => "fail", "message" => "User tidak ditemukan."]);
        exit;
    }

    // ------------------------------------------------------------------
    // 2. Semua course published + progres milik user (dipakai berkali-kali di bawah)
    // ------------------------------------------------------------------
    $courseStmt = $pdo->prepare("
        SELECT
            c.id, c.title, c.slug, c.description, c.icon, c.cover_image,
            COUNT(DISTINCT m.id)  AS module_count,
            COUNT(DISTINCT t.id)  AS topic_count,
            ce.id                 AS enrollment_id,
            ce.enrolled_at        AS enrolled_at
        FROM courses c
        LEFT JOIN modules m ON m.course_id = c.id
        LEFT JOIN topics t  ON t.module_id = m.id
        LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.user_id = :uid
        WHERE c.is_published = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $courseStmt->execute([':uid' => $userId]);
    $allCourses = $courseStmt->fetchAll();

    // Progres per topic milik user, dikelompokkan per course_id
    $progStmt = $pdo->prepare("
        SELECT m.course_id, utp.topic_id, utp.completed_at
        FROM user_topic_progress utp
        INNER JOIN topics t ON t.id = utp.topic_id
        INNER JOIN modules m ON m.id = t.module_id
        WHERE utp.user_id = :uid AND utp.is_completed = 1
    ");
    $progStmt->execute([':uid' => $userId]);
    $progressRows = $progStmt->fetchAll();

    $completedTopicIdsByCourse = [];
    $lastActivityByCourse      = [];
    $totalCompletedTopics      = 0;

    foreach ($progressRows as $p) {
        $cid = (int) $p['course_id'];
        $completedTopicIdsByCourse[$cid][] = (int) $p['topic_id'];
        if (!isset($lastActivityByCourse[$cid]) || $p['completed_at'] > $lastActivityByCourse[$cid]) {
            $lastActivityByCourse[$cid] = $p['completed_at'];
        }
        $totalCompletedTopics++;
    }

    // Susun ulang $allCourses jadi array asosiatif ringkas dengan progres terhitung
    $courses = [];
    foreach ($allCourses as $c) {
        $cid          = (int) $c['id'];
        $topicCount   = (int) $c['topic_count'];
        $completedIds = $completedTopicIdsByCourse[$cid] ?? [];
        $completed    = count($completedIds);

        $courses[$cid] = [
            'id'                => $cid,
            'title'             => $c['title'],
            'slug'              => $c['slug'],
            'description'       => $c['description'],
            'icon'              => $c['icon'],
            'cover_image'       => $c['cover_image'],
            'module_count'      => (int) $c['module_count'],
            'topic_count'       => $topicCount,
            'is_enrolled'       => $c['enrollment_id'] !== null,
            'enrolled_at'       => $c['enrolled_at'],
            'completed_topics'  => $completed,
            'progress_percent'  => $topicCount > 0 ? (int) round(($completed / $topicCount) * 100) : 0,
            'last_activity'     => $lastActivityByCourse[$cid] ?? null,
            'completed_topic_ids' => $completedIds,
        ];
    }

    // ------------------------------------------------------------------
    // 3. "Lanjutkan Belajar" -> course yang diikuti & belum 100%
    // ------------------------------------------------------------------
    $inProgress = array_filter($courses, function ($c) {
        return $c['is_enrolled'] && $c['topic_count'] > 0 && $c['progress_percent'] < 100;
    });

    usort($inProgress, function ($a, $b) {
        // Urutkan berdasarkan aktivitas terakhir (terbaru dulu), lalu waktu enroll
        $aTime = $a['last_activity'] ?? $a['enrolled_at'];
        $bTime = $b['last_activity'] ?? $b['enrolled_at'];
        return strcmp((string) $bTime, (string) $aTime);
    });

    // Ambil topic selanjutnya (belum selesai, urutan paling awal) untuk tiap course in-progress
    $continueLearning = [];
    foreach (array_slice($inProgress, 0, 3) as $c) {
        $nextTopicStmt = $pdo->prepare("
            SELECT t.id, t.title, t.slug, t.estimated_read_time, m.title AS module_title
            FROM topics t
            INNER JOIN modules m ON m.id = t.module_id
            WHERE m.course_id = :cid
            " . (!empty($c['completed_topic_ids']) ? "AND t.id NOT IN (" . implode(',', array_map('intval', $c['completed_topic_ids'])) . ")" : "") . "
            ORDER BY m.order_position ASC, t.order_position ASC, t.id ASC
            LIMIT 1
        ");
        $nextTopicStmt->execute([':cid' => $c['id']]);
        $nextTopic = $nextTopicStmt->fetch();

        $continueLearning[] = [
            'id'               => $c['id'],
            'title'            => $c['title'],
            'slug'             => $c['slug'],
            'icon'             => $c['icon'],
            'progress_percent' => $c['progress_percent'],
            'completed_topics' => $c['completed_topics'],
            'topic_count'      => $c['topic_count'],
            'next_topic'       => $nextTopic ? [
                'id'                   => (int) $nextTopic['id'],
                'title'                => $nextTopic['title'],
                'slug'                 => $nextTopic['slug'],
                'module_title'         => $nextTopic['module_title'],
                'estimated_read_time'  => (int) $nextTopic['estimated_read_time'],
            ] : null,
        ];
    }

    // ------------------------------------------------------------------
    // 4. Rekomendasi -> course published yang belum diikuti user
    //    (fallback: course yang sudah 100% selesai, untuk "pendalaman lanjutan")
    // ------------------------------------------------------------------
    $notEnrolled = array_values(array_filter($courses, function ($c) {
        return !$c['is_enrolled'];
    }));
    usort($notEnrolled, function ($a, $b) {
        return $b['id'] <=> $a['id']; // course terbaru lebih dulu
    });

    $recommended = array_map(function ($c) {
        $desc = trim((string) $c['description']);
        if (mb_strlen($desc) > 140) {
            $desc = mb_substr($desc, 0, 140) . '…';
        }
        return [
            'id'           => $c['id'],
            'title'        => $c['title'],
            'slug'         => $c['slug'],
            'icon'         => $c['icon'],
            'description'  => $desc,
            'module_count' => $c['module_count'],
            'topic_count'  => $c['topic_count'],
        ];
    }, array_slice($notEnrolled, 0, 4));

    // ------------------------------------------------------------------
    // 5. Statistik ringkas untuk kartu overview
    // ------------------------------------------------------------------
    $enrolledCourses  = array_values(array_filter($courses, fn($c) => $c['is_enrolled']));
    $completedCourses = array_values(array_filter($enrolledCourses, fn($c) => $c['topic_count'] > 0 && $c['progress_percent'] >= 100));

    $minutesStmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.estimated_read_time), 0) AS total_minutes
        FROM user_topic_progress utp
        INNER JOIN topics t ON t.id = utp.topic_id
        WHERE utp.user_id = :uid AND utp.is_completed = 1
    ");
    $minutesStmt->execute([':uid' => $userId]);
    $totalMinutes = (int) $minutesStmt->fetchColumn();

    $stats = [
        'enrolled_courses'  => count($enrolledCourses),
        'completed_courses' => count($completedCourses),
        'completed_topics'  => $totalCompletedTopics,
        'total_minutes'     => $totalMinutes,
    ];

    // ------------------------------------------------------------------
    // 6. Aktivitas terakhir (5 materi terakhir yang diselesaikan)
    // ------------------------------------------------------------------
    $activityStmt = $pdo->prepare("
        SELECT utp.completed_at, t.title AS topic_title, t.slug AS topic_slug,
               c.title AS course_title, c.slug AS course_slug
        FROM user_topic_progress utp
        INNER JOIN topics t ON t.id = utp.topic_id
        INNER JOIN modules m ON m.id = t.module_id
        INNER JOIN courses c ON c.id = m.course_id
        WHERE utp.user_id = :uid AND utp.is_completed = 1
        ORDER BY utp.completed_at DESC
        LIMIT 5
    ");
    $activityStmt->execute([':uid' => $userId]);
    $recentActivity = array_map(function ($r) {
        return [
            'topic_title'  => $r['topic_title'],
            'course_title' => $r['course_title'],
            'course_slug'  => $r['course_slug'],
            'completed_at' => $r['completed_at'],
        ];
    }, $activityStmt->fetchAll());

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data"   => [
            "user" => [
                "name"         => $userRow['name'],
                "email"        => $userRow['email'],
                "avatar"       => $userRow['avatar'],
                "member_since" => $userRow['created_at'],
            ],
            "stats"             => $stats,
            "continue_learning" => $continueLearning,
            "recommended"       => $recommended,
            "recent_activity"   => $recentActivity,
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mengambil data dashboard."]);
}