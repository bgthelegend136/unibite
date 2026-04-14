<?php
// Shared server-side utilities

/**
 * Send a JSON response and exit.
 */
function json_response(bool $ok, mixed $data = null, string $error = ''): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error]);
    exit;
}

/**
 * Require a POST request; send 405 otherwise.
 */
function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        json_response(false, null, 'Method not allowed');
    }
}

/**
 * Return trimmed string or null if blank.
 */
function str_or_null(string $val): ?string {
    $v = trim($val);
    return $v === '' ? null : $v;
}

/**
 * Format the cached points balance for UI labels.
 */
function format_points_label(int $points): string {
    return $points . ' ' . ($points === 1 ? 'pt' : 'pts');
}

/**
 * Apply pending rating-timeout penalties for the given user.
 * Called lazily on login and on my-requests page load.
 * Condition: request picked_up_at + 48h < NOW, no rating, no existing timeout event.
 */
function apply_rating_timeouts(int $user_id): void {
    $pdo = db();
    // Find eligible requests
    $stmt = $pdo->prepare("
        SELECT r.id AS request_id
        FROM   requests r
        LEFT JOIN ratings rt ON rt.request_id = r.id
        LEFT JOIN point_events pe
               ON pe.request_id = r.id
              AND pe.user_id    = r.consumer_id
              AND pe.reason     = 'rating_timeout'
        WHERE  r.consumer_id  = :uid
          AND  r.status       = 'picked_up'
          AND  r.picked_up_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND  rt.id          IS NULL
          AND  pe.id          IS NULL
    ");
    $stmt->execute([':uid' => $user_id]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("
                INSERT INTO point_events (user_id, request_id, delta, reason)
                VALUES (:uid, :rid, -1, 'rating_timeout')
            ")->execute([':uid' => $user_id, ':rid' => $row['request_id']]);
            $pdo->prepare("
                UPDATE users SET points = points - 1 WHERE id = :uid
            ")->execute([':uid' => $user_id]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Duplicate key = already applied; safe to ignore.
        }
    }
}
