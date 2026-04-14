<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();

$action = trim($_POST['action'] ?? '');

match ($action) {
    'send' => handle_send_request(),
    'list' => handle_list_requests(),
    'approve' => handle_approve_request(),
    'reject' => handle_reject_request(),
    'pickup' => handle_pickup_request(),
    'noshow' => handle_noshow_request(),
    default => json_response(false, null, 'Unknown action'),
};

function request_error(string $message, ?Throwable $e = null): void
{
    if ($e) {
        error_log('[requests.php] ' . $message . ': ' . $e->getMessage());
        if (is_local_request()) {
            $message .= ': ' . $e->getMessage();
        }
    }

    json_response(false, null, $message);
}

function is_local_request(): bool
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $serverName = (string)($_SERVER['SERVER_NAME'] ?? '');
    $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    foreach ([$host, $serverName, $remoteAddr] as $value) {
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1') || $value === '::1') {
            return true;
        }
    }

    return false;
}

function is_expired_listing(array $listing): bool
{
    return strtotime((string)$listing['created_at']) <= strtotime('-48 hours');
}

function request_listing_status_sql(string $alias = 'l'): string
{
    return "CASE
        WHEN {$alias}.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 'expired'
        WHEN {$alias}.portions_available = 0 THEN 'inactive'
        ELSE 'active'
    END";
}

function request_point_event_exists(PDO $pdo, int $request_id, int $user_id, string $reason): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM point_events
        WHERE user_id = ?
          AND request_id = ?
          AND reason = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $request_id, $reason]);

    return (bool)$stmt->fetchColumn();
}

function handle_send_request(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can request portions');
    }

    $consumer_id = current_user_id();
    $listing_id = (int)($_POST['listing_id'] ?? 0);
    if ($listing_id < 1) {
        json_response(false, null, 'Listing id is required');
    }

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, provider_id, portions_available, created_at FROM listings WHERE id = ? AND deleted_by_owner_at IS NULL');
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch();
    if (!$listing) {
        json_response(false, null, 'Listing not found');
    }

    if ((int)$listing['provider_id'] === $consumer_id) {
        json_response(false, null, 'You cannot request your own listing');
    }

    if (is_expired_listing($listing)) {
        json_response(false, null, 'This listing has expired');
    }

    if ((int)$listing['portions_available'] < 1) {
        json_response(false, null, 'No portions available');
    }

    $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ?');
    $stmt->execute([$consumer_id]);
    $consumer = $stmt->fetch();
    if (!$consumer) {
        json_response(false, null, 'Consumer not found');
    }

    if ((int)$consumer['points'] < 1) {
        json_response(false, null, 'You need at least 1 point to request a portion');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM requests
        WHERE listing_id = ?
          AND consumer_id = ?
          AND status IN ('pending', 'approved')
    ");
    $stmt->execute([$listing_id, $consumer_id]);
    if ($stmt->fetch()) {
        json_response(false, null, 'You already have an active request for this listing');
    }

    try {
        $pdo->prepare("INSERT INTO requests (listing_id, consumer_id, status) VALUES (?, ?, 'pending')")
            ->execute([$listing_id, $consumer_id]);
    } catch (Throwable $e) {
        request_error('Failed to create request', $e);
    }

    json_response(true, [
        'listing_id' => $listing_id,
        'consumer_id' => $consumer_id,
    ]);
}

function handle_list_requests(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can view requests');
    }

    $scope = trim($_POST['scope'] ?? 'incoming');
    if (!in_array($scope, ['incoming', 'outgoing'], true)) {
        json_response(false, null, 'Invalid scope');
    }

    $uid = current_user_id();
    $pdo = db();
    $listingStatusSql = request_listing_status_sql('l');

    try {
        if ($scope === 'incoming') {
            $stmt = $pdo->prepare("
                SELECT
                    r.id AS request_id,
                    r.status AS request_status,
                    r.created_at AS request_created_at,
                    r.approved_at,
                    r.picked_up_at,
                    r.no_show_at,
                    rt.score AS rating_score,
                    rt.created_at AS rating_created_at,
                    l.id AS listing_id,
                    l.title AS listing_title,
                    l.description AS listing_description,
                    l.portions_total,
                    l.portions_available,
                    l.pickup_location,
                    l.pickup_time,
                    l.created_at AS listing_created_at,
                    u.id AS consumer_id,
                    u.username AS consumer_username,
                    {$listingStatusSql} AS listing_status
                FROM requests r
                INNER JOIN listings l ON l.id = r.listing_id
                INNER JOIN users u ON u.id = r.consumer_id
                LEFT JOIN ratings rt ON rt.request_id = r.id
                WHERE l.provider_id = :uid
                ORDER BY r.created_at DESC, r.id DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    r.id AS request_id,
                    r.status AS request_status,
                    r.created_at AS request_created_at,
                    r.approved_at,
                    r.picked_up_at,
                    r.no_show_at,
                    rt.score AS rating_score,
                    rt.created_at AS rating_created_at,
                    l.id AS listing_id,
                    l.title AS listing_title,
                    l.description AS listing_description,
                    l.portions_total,
                    l.portions_available,
                    l.pickup_location,
                    l.pickup_time,
                    l.created_at AS listing_created_at,
                    u.id AS provider_id,
                    u.username AS provider_username,
                    {$listingStatusSql} AS listing_status
                FROM requests r
                INNER JOIN listings l ON l.id = r.listing_id
                INNER JOIN users u ON u.id = l.provider_id
                LEFT JOIN ratings rt ON rt.request_id = r.id
                WHERE r.consumer_id = :uid
                ORDER BY r.created_at DESC, r.id DESC
            ");
        }

        $stmt->execute([':uid' => $uid]);
        json_response(true, $stmt->fetchAll());
    } catch (Throwable $e) {
        request_error('Failed to load requests', $e);
    }
}

function handle_approve_request(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can manage requests');
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    if ($request_id < 1) {
        json_response(false, null, 'Request id is required');
    }

    $owner_id = current_user_id();
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.id AS request_id,
                r.status AS request_status,
                r.consumer_id,
                r.listing_id,
                l.provider_id,
                l.portions_available,
                l.created_at,
                u.points AS consumer_points
            FROM requests r
            INNER JOIN listings l ON l.id = r.listing_id
            INNER JOIN users u ON u.id = r.consumer_id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            json_response(false, null, 'Request not found');
        }

        if ((int)$row['provider_id'] !== $owner_id) {
            $pdo->rollBack();
            json_response(false, null, 'You can only manage requests for your own listings');
        }

        if ($row['request_status'] !== 'pending') {
            $pdo->rollBack();
            json_response(false, null, 'Only pending requests can be approved');
        }

        if (is_expired_listing($row)) {
            $pdo->rollBack();
            json_response(false, null, 'This listing has expired');
        }

        if ((int)$row['portions_available'] < 1) {
            $pdo->rollBack();
            json_response(false, null, 'No portions available');
        }

        if ((int)$row['consumer_points'] < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Consumer no longer has enough points to approve this request');
        }

        $stmt = $pdo->prepare("
            UPDATE requests
            SET status = 'approved',
                approved_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$request_id]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Only pending requests can be approved');
        }

        $stmt = $pdo->prepare("
            UPDATE listings
            SET portions_available = portions_available - 1
            WHERE id = ?
              AND portions_available > 0
        ");
        $stmt->execute([(int)$row['listing_id']]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'No portions available');
        }

        $stmt = $pdo->prepare("
            INSERT INTO point_events (user_id, request_id, delta, reason)
            VALUES (?, ?, -1, 'request_approved')
        ");
        $stmt->execute([(int)$row['consumer_id'], $request_id]);

        $stmt = $pdo->prepare("
            UPDATE users
            SET points = points - 1
            WHERE id = ?
              AND points > 0
        ");
        $stmt->execute([(int)$row['consumer_id']]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Consumer no longer has enough points to approve this request');
        }

        $pdo->commit();
        json_response(true, [
            'request_id' => $request_id,
            'status' => 'approved',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        request_error('Failed to approve request', $e);
    }
}

function handle_reject_request(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can manage requests');
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    if ($request_id < 1) {
        json_response(false, null, 'Request id is required');
    }

    $owner_id = current_user_id();
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.id AS request_id,
                r.status AS request_status,
                l.provider_id
            FROM requests r
            INNER JOIN listings l ON l.id = r.listing_id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            json_response(false, null, 'Request not found');
        }

        if ((int)$row['provider_id'] !== $owner_id) {
            $pdo->rollBack();
            json_response(false, null, 'You can only manage requests for your own listings');
        }

        if ($row['request_status'] !== 'pending') {
            $pdo->rollBack();
            json_response(false, null, 'Only pending requests can be rejected');
        }

        $stmt = $pdo->prepare("
            UPDATE requests
            SET status = 'rejected'
            WHERE id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$request_id]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Only pending requests can be rejected');
        }

        $pdo->commit();
        json_response(true, [
            'request_id' => $request_id,
            'status' => 'rejected',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        request_error('Failed to reject request', $e);
    }
}

function handle_pickup_request(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can manage requests');
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    if ($request_id < 1) {
        json_response(false, null, 'Request id is required');
    }

    $owner_id = current_user_id();
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.id AS request_id,
                r.status AS request_status,
                r.consumer_id,
                r.listing_id,
                l.provider_id,
                u.points AS provider_points
            FROM requests r
            INNER JOIN listings l ON l.id = r.listing_id
            INNER JOIN users u ON u.id = l.provider_id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            json_response(false, null, 'Request not found');
        }

        if ((int)$row['provider_id'] !== $owner_id) {
            $pdo->rollBack();
            json_response(false, null, 'You can only manage requests for your own listings');
        }

        if ($row['request_status'] === 'picked_up') {
            if (request_point_event_exists($pdo, $request_id, $owner_id, 'pickup_base')) {
                $pdo->commit();
                json_response(true, [
                    'request_id' => $request_id,
                    'status' => 'picked_up',
                ]);
            }

            $pdo->rollBack();
            json_response(false, null, 'Pickup already recorded');
        }

        if ($row['request_status'] !== 'approved') {
            $pdo->rollBack();
            json_response(false, null, 'Only approved requests can be marked picked up');
        }

        $stmt = $pdo->prepare("
            UPDATE requests
            SET status = 'picked_up',
                picked_up_at = NOW()
            WHERE id = ?
              AND status = 'approved'
        ");
        $stmt->execute([$request_id]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Only approved requests can be marked picked up');
        }

        $stmt = $pdo->prepare("
            INSERT INTO point_events (user_id, request_id, delta, reason)
            VALUES (?, ?, 1, 'pickup_base')
        ");
        $stmt->execute([(int)$row['provider_id'], $request_id]);

        $stmt = $pdo->prepare("
            UPDATE users
            SET points = points + 1
            WHERE id = ?
        ");
        $stmt->execute([(int)$row['provider_id']]);

        if ((int)$row['provider_id'] === $owner_id) {
            refresh_session_user();
        }

        $pdo->commit();
        json_response(true, [
            'request_id' => $request_id,
            'status' => 'picked_up',
            'current_user_points' => (int)(current_user()['points'] ?? 0),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        request_error('Failed to record pickup', $e);
    }
}

function handle_noshow_request(): void
{
    require_login_api();

    $user = current_user();
    if (($user['role'] ?? '') !== 'student') {
        json_response(false, null, 'Only students can manage requests');
    }

    $request_id = (int)($_POST['request_id'] ?? 0);
    if ($request_id < 1) {
        json_response(false, null, 'Request id is required');
    }

    $owner_id = current_user_id();
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.id AS request_id,
                r.status AS request_status,
                r.consumer_id,
                r.listing_id,
                l.provider_id,
                u.points AS consumer_points
            FROM requests r
            INNER JOIN listings l ON l.id = r.listing_id
            INNER JOIN users u ON u.id = r.consumer_id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            json_response(false, null, 'Request not found');
        }

        if ((int)$row['provider_id'] !== $owner_id) {
            $pdo->rollBack();
            json_response(false, null, 'You can only manage requests for your own listings');
        }

        if ($row['request_status'] === 'no_show') {
            if (request_point_event_exists($pdo, $request_id, (int)$row['consumer_id'], 'no_show')) {
                $pdo->commit();
                json_response(true, [
                    'request_id' => $request_id,
                    'status' => 'no_show',
                ]);
            }

            $pdo->rollBack();
            json_response(false, null, 'No-show already recorded');
        }

        if ($row['request_status'] !== 'approved') {
            $pdo->rollBack();
            json_response(false, null, 'Only approved requests can be marked no-show');
        }

        $stmt = $pdo->prepare("
            UPDATE requests
            SET status = 'no_show',
                no_show_at = NOW()
            WHERE id = ?
              AND status = 'approved'
        ");
        $stmt->execute([$request_id]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            json_response(false, null, 'Only approved requests can be marked no-show');
        }

        $stmt = $pdo->prepare("
            INSERT INTO point_events (user_id, request_id, delta, reason)
            VALUES (?, ?, -1, 'no_show')
        ");
        $stmt->execute([(int)$row['consumer_id'], $request_id]);

        $stmt = $pdo->prepare("
            UPDATE users
            SET points = points - 1
            WHERE id = ?
        ");
        $stmt->execute([(int)$row['consumer_id']]);

        $pdo->commit();
        json_response(true, [
            'request_id' => $request_id,
            'status' => 'no_show',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        request_error('Failed to record no-show', $e);
    }
}
