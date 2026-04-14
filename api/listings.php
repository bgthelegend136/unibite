<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();

$action = trim($_POST['action'] ?? '');

match ($action) {
    'list'   => handle_listings_list(),
    'get'    => handle_listing_get(),
    'create' => handle_listing_create(),
    'update' => handle_listing_update(),
    'delete' => handle_listing_delete(),
    default  => json_response(false, null, 'Unknown action'),
};

function listing_status_expr(): string {
    return "CASE
        WHEN l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) AND l.portions_available > 0 THEN 'active'
        WHEN l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) AND l.portions_available = 0 THEN 'inactive'
        ELSE 'expired'
    END";
}

function normalize_allergens(mixed $value): array {
    if (!is_array($value)) {
        $value = $value === null || $value === '' ? [] : [$value];
    }

    $ids = array_map('intval', $value);
    $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

    return array_values(array_unique($ids));
}

function validate_listing_input(array $input): array {
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $portions_total = (int)($input['portions_total'] ?? 0);
    $pickup_location = trim((string)($input['pickup_location'] ?? ''));
    $pickup_lat = trim((string)($input['pickup_lat'] ?? ''));
    $pickup_lng = trim((string)($input['pickup_lng'] ?? ''));
    $pickup_time = trim((string)($input['pickup_time'] ?? ''));

    if ($title === '' || $portions_total < 1 || $pickup_location === '' || $pickup_lat === '' || $pickup_lng === '' || $pickup_time === '') {
        json_response(false, null, 'Title, portions, pickup location, pickup coordinates, and pickup time are required');
    }
    if (!is_numeric($pickup_lat) || !is_numeric($pickup_lng)) {
        json_response(false, null, 'Pickup coordinates must be valid numbers');
    }

    $ts = strtotime($pickup_time);
    if ($ts === false) {
        json_response(false, null, 'Pickup time must be a valid date and time');
    }

    return [
        'title' => $title,
        'description' => $description,
        'portions_total' => $portions_total,
        'pickup_location' => $pickup_location,
        'pickup_lat' => $pickup_lat,
        'pickup_lng' => $pickup_lng,
        'pickup_time' => date('Y-m-d H:i:s', $ts),
    ];
}

function store_uploaded_photo(array $file): ?string {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $upload_dir = __DIR__ . '/../uploads';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        json_response(false, null, 'Unable to create upload directory');
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        json_response(false, null, 'Photo must be a JPG, PNG, GIF, or WEBP file');
    }

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $upload_dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        json_response(false, null, 'Failed to save uploaded photo');
    }

    return 'uploads/' . $name;
}

function is_local_request(): bool {
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

function listing_error_response(string $fallback, Throwable $e): void {
    error_log('[listings.php] ' . $fallback . ': ' . $e->getMessage());

    $message = $fallback;
    if (is_local_request()) {
        $message .= ': ' . $e->getMessage();
    }

    json_response(false, null, $message);
}

function listing_payload(array $row, bool $with_allergens = false): array {
    $payload = [
        'id' => (int)$row['id'],
        'provider_id' => (int)$row['provider_id'],
        'provider_username' => $row['provider_username'] ?? null,
        'title' => $row['title'],
        'description' => $row['description'],
        'photo_path' => $row['photo_path'],
        'photo_url' => $row['photo_path'] ? '/' . ltrim($row['photo_path'], '/') : null,
        'portions_total' => (int)$row['portions_total'],
        'portions_available' => (int)$row['portions_available'],
        'pickup_location' => $row['pickup_location'],
        'pickup_lat' => (float)$row['pickup_lat'],
        'pickup_lng' => (float)$row['pickup_lng'],
        'pickup_time' => $row['pickup_time'],
        'created_at' => $row['created_at'],
        'listing_status' => $row['listing_status'],
    ];

    if ($with_allergens) {
        $payload['allergens'] = $row['allergens'] ?? [];
        $payload['allergen_ids'] = $row['allergen_ids'] ?? [];
    }

    return $payload;
}

function fetch_public_listing_by_id(int $id): ?array {
    $pdo = db();
    $sql = "
        SELECT
            l.*,
            u.username AS provider_username,
            " . listing_status_expr() . " AS listing_status,
            (
                SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ')
                FROM listing_allergens la
                JOIN allergens a ON a.id = la.allergen_id
                WHERE la.listing_id = l.id
            ) AS allergens
        FROM listings l
        JOIN users u ON u.id = l.provider_id
        WHERE l.id = :id
          AND l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND l.deleted_by_owner_at IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $allergenRows = $pdo->prepare('
        SELECT allergen_id
        FROM listing_allergens
        WHERE listing_id = ?
        ORDER BY allergen_id
    ');
    $allergenRows->execute([$id]);
    $row['allergen_ids'] = array_map('intval', array_column($allergenRows->fetchAll(), 'allergen_id'));

    return listing_payload($row, true);
}

function fetch_owner_listing_by_id(int $id, int $provider_id): ?array {
    $pdo = db();
    $sql = "
        SELECT
            l.*,
            u.username AS provider_username,
            " . listing_status_expr() . " AS listing_status,
            (
                SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ')
                FROM listing_allergens la
                JOIN allergens a ON a.id = la.allergen_id
                WHERE la.listing_id = l.id
            ) AS allergens
        FROM listings l
        JOIN users u ON u.id = l.provider_id
        WHERE l.id = :id
          AND l.provider_id = :provider_id
          AND l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND l.deleted_by_owner_at IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':provider_id' => $provider_id,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $allergenRows = $pdo->prepare('
        SELECT allergen_id
        FROM listing_allergens
        WHERE listing_id = ?
        ORDER BY allergen_id
    ');
    $allergenRows->execute([$id]);
    $row['allergen_ids'] = array_map('intval', array_column($allergenRows->fetchAll(), 'allergen_id'));

    return listing_payload($row, true);
}

function handle_listings_list(): void {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT
            l.*,
            u.username AS provider_username,
            " . listing_status_expr() . " AS listing_status,
            (
                SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ')
                FROM listing_allergens la
                JOIN allergens a ON a.id = la.allergen_id
                WHERE la.listing_id = l.id
            ) AS allergens
        FROM listings l
        JOIN users u ON u.id = l.provider_id
        WHERE l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
          AND l.deleted_by_owner_at IS NULL
        ORDER BY l.created_at DESC
    ");

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = listing_payload($row);
    }

    json_response(true, $rows);
}

function handle_listing_get(): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        json_response(false, null, 'Listing id is required');
    }

    $context = trim($_POST['context'] ?? '');
    if ($context === 'edit') {
        require_login_api();
        $listing = fetch_owner_listing_by_id($id, current_user_id());
    } else {
        $listing = fetch_public_listing_by_id($id);
    }

    if (!$listing) {
        json_response(false, null, 'Listing not found');
    }

    json_response(true, $listing);
}

function handle_listing_create(): void {
    require_login_api();

    $uid = current_user_id();
    $data = validate_listing_input($_POST);
    $allergen_ids = normalize_allergens($_POST['allergens'] ?? []);
    $photo_path = null;
    $pdo = db();

    try {
        $pdo->beginTransaction();

        if (!empty($_FILES['photo']['tmp_name'] ?? '')) {
            $photo_path = store_uploaded_photo($_FILES['photo']);
        }

        $stmt = $pdo->prepare('
            INSERT INTO listings
                (provider_id, title, description, photo_path, portions_total, portions_available, pickup_location, pickup_lat, pickup_lng, pickup_time)
            VALUES
                (:provider_id, :title, :description, :photo_path, :portions_total, :portions_available, :pickup_location, :pickup_lat, :pickup_lng, :pickup_time)
        ');
        $stmt->execute([
            ':provider_id' => $uid,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':photo_path' => $photo_path,
            ':portions_total' => $data['portions_total'],
            ':portions_available' => $data['portions_total'],
            ':pickup_location' => $data['pickup_location'],
            ':pickup_lat' => $data['pickup_lat'],
            ':pickup_lng' => $data['pickup_lng'],
            ':pickup_time' => $data['pickup_time'],
        ]);

        $listing_id = (int)$pdo->lastInsertId();

        if ($allergen_ids) {
            $stmt = $pdo->prepare('INSERT INTO listing_allergens (listing_id, allergen_id) VALUES (:listing_id, :allergen_id)');
            foreach ($allergen_ids as $allergen_id) {
                $stmt->execute([
                    ':listing_id' => $listing_id,
                    ':allergen_id' => $allergen_id,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($photo_path) {
            $path = __DIR__ . '/../' . $photo_path;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        listing_error_response('Failed to create listing', $e);
    }

    json_response(true, ['id' => $listing_id]);
}

function handle_listing_update(): void {
    require_login_api();

    $uid = current_user_id();
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        json_response(false, null, 'Listing id is required');
    }

    $existing = fetch_owner_listing_by_id($id, $uid);
    if (!$existing) {
        json_response(false, null, 'Listing not found');
    }

    $data = validate_listing_input($_POST);
    $allergen_ids = normalize_allergens($_POST['allergens'] ?? []);
    $new_photo_path = null;
    $old_photo_path = $existing['photo_path'] ?: null;
    $pdo = db();

    try {
        $pdo->beginTransaction();

        if (!empty($_FILES['photo']['tmp_name'] ?? '')) {
            $new_photo_path = store_uploaded_photo($_FILES['photo']);
        }

        $stmt = $pdo->prepare('
            UPDATE listings
            SET title = :title,
                description = :description,
                photo_path = :photo_path,
                portions_total = :portions_total,
                portions_available = LEAST(portions_available, :portions_available_cap),
                pickup_location = :pickup_location,
                pickup_lat = :pickup_lat,
                pickup_lng = :pickup_lng,
                pickup_time = :pickup_time
            WHERE id = :id AND provider_id = :provider_id
        ');
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':photo_path' => $new_photo_path ?? $old_photo_path,
            ':portions_total' => $data['portions_total'],
            ':portions_available_cap' => $data['portions_total'],
            ':pickup_location' => $data['pickup_location'],
            ':pickup_lat' => $data['pickup_lat'],
            ':pickup_lng' => $data['pickup_lng'],
            ':pickup_time' => $data['pickup_time'],
            ':id' => $id,
            ':provider_id' => $uid,
        ]);

        $pdo->prepare('DELETE FROM listing_allergens WHERE listing_id = ?')->execute([$id]);
        if ($allergen_ids) {
            $stmt = $pdo->prepare('INSERT INTO listing_allergens (listing_id, allergen_id) VALUES (:listing_id, :allergen_id)');
            foreach ($allergen_ids as $allergen_id) {
                $stmt->execute([
                    ':listing_id' => $id,
                    ':allergen_id' => $allergen_id,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($new_photo_path) {
            $path = __DIR__ . '/../' . $new_photo_path;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        listing_error_response('Failed to update listing', $e);
    }

    if ($new_photo_path && $old_photo_path) {
        $old = __DIR__ . '/../' . $old_photo_path;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    json_response(true, ['id' => $id]);
}

function handle_listing_delete(): void {
    require_login_api();

    $uid = current_user_id();
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) {
        json_response(false, null, 'Listing id is required');
    }

    // fetch_owner_listing_by_id already excludes soft-deleted listings
    $listing = fetch_owner_listing_by_id($id, $uid);
    if (!$listing) {
        json_response(false, null, 'Listing not found');
    }

    $pdo = db();
    try {
        $pdo->prepare('
            UPDATE listings
            SET deleted_by_owner_at = NOW()
            WHERE id = ? AND provider_id = ? AND deleted_by_owner_at IS NULL
        ')->execute([$id, $uid]);
    } catch (Throwable $e) {
        listing_error_response('Failed to delete listing', $e);
    }

    json_response(true, null);
}
