<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();
require_login_api();
require_role('admin', 'api');

$action = trim($_POST['action'] ?? '');

match ($action) {
    'dashboard' => handle_dashboard_stats(),
    default => json_response(false, null, 'Unknown action'),
};

function handle_dashboard_stats(): void
{
    $pdo = db();

    $sharedStmt = $pdo->query("
        SELECT COUNT(*) AS total_shared_last_month
        FROM requests
        WHERE status = 'picked_up'
          AND picked_up_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    ");
    $shared = $sharedStmt->fetch();

    $donorStmt = $pdo->query("
        SELECT
            u.id,
            u.username,
            COUNT(*) AS shared_count
        FROM requests r
        INNER JOIN listings l ON l.id = r.listing_id
        INNER JOIN users u ON u.id = l.provider_id
        WHERE r.status = 'picked_up'
        GROUP BY u.id, u.username
        ORDER BY shared_count DESC, u.username ASC
        LIMIT 1
    ");
    $donor = $donorStmt->fetch() ?: null;

    $mealsStmt = $pdo->query("
        SELECT
            l.id,
            l.title,
            u.username AS provider_username,
            ROUND(AVG(rt.score), 2) AS average_rating,
            COUNT(rt.id) AS rating_count
        FROM ratings rt
        INNER JOIN requests r ON r.id = rt.request_id
        INNER JOIN listings l ON l.id = r.listing_id
        INNER JOIN users u ON u.id = l.provider_id
        GROUP BY l.id, l.title, u.username
        HAVING COUNT(rt.id) > 0
        ORDER BY AVG(rt.score) DESC, COUNT(rt.id) DESC, l.title ASC
    ");

    $meals = array_map(
        static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'provider_username' => $row['provider_username'],
                'average_rating' => (float)$row['average_rating'],
                'rating_count' => (int)$row['rating_count'],
            ];
        },
        $mealsStmt->fetchAll()
    );

    json_response(true, [
        'total_shared_last_month' => (int)($shared['total_shared_last_month'] ?? 0),
        'top_donor' => $donor ? [
            'id' => (int)$donor['id'],
            'username' => $donor['username'],
            'shared_count' => (int)$donor['shared_count'],
        ] : null,
        'highest_rated_meals' => $meals,
    ]);
}
