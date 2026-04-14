<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();

$action = trim($_POST['action'] ?? '');

match ($action) {
    'submit' => handle_submit_rating(),
    default => json_response(false, null, 'Unknown action'),
};

function rating_error(string $message, ?Throwable $e = null): void
{
    if ($e) {
        error_log('[ratings.php] ' . $message . ': ' . $e->getMessage());
    }

    json_response(false, null, $message);
}

function handle_submit_rating(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can submit ratings');
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    $score = (int)($_POST['score'] ?? 0);
    if ($request_id < 1) {
        json_response(false, null, 'Request id is required');
    }
    if ($score < 1 || $score > 5) {
        json_response(false, null, 'Score must be an integer from 1 to 5');
    }

    $consumer_id = current_user_id();
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.id AS request_id,
                r.consumer_id,
                r.status AS request_status,
                l.provider_id,
                rt.id AS rating_id
            FROM requests r
            INNER JOIN listings l ON l.id = r.listing_id
            LEFT JOIN ratings rt ON rt.request_id = r.id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            json_response(false, null, 'Request not found');
        }

        if ((int)$row['consumer_id'] !== $consumer_id) {
            $pdo->rollBack();
            json_response(false, null, 'You can only rate your own requests');
        }

        if ($row['request_status'] !== 'picked_up') {
            $pdo->rollBack();
            json_response(false, null, 'Only picked up requests can be rated');
        }

        if ($row['rating_id']) {
            $pdo->rollBack();
            json_response(false, null, 'Rating already submitted');
        }

        $stmt = $pdo->prepare('INSERT INTO ratings (request_id, score) VALUES (?, ?)');
        $stmt->execute([$request_id, $score]);

        if ($score > 3) {
            $stmt = $pdo->prepare("
                INSERT INTO point_events (user_id, request_id, delta, reason)
                VALUES (?, ?, 1, 'pickup_bonus')
            ");
            $stmt->execute([(int)$row['provider_id'], $request_id]);

            $stmt = $pdo->prepare("
                UPDATE users
                SET points = points + 1
                WHERE id = ?
            ");
            $stmt->execute([(int)$row['provider_id']]);
        }

        $pdo->commit();
        json_response(true, [
            'request_id' => $request_id,
            'score' => $score,
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e->getCode() === '23000') {
            json_response(false, null, 'Rating already submitted');
        }

        rating_error('Failed to submit rating', $e);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        rating_error('Failed to submit rating', $e);
    }
}
