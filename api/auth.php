<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();
$action = trim($_POST['action'] ?? '');

match ($action) {
    'login'    => handle_login(),
    'register' => handle_register(),
    'logout'   => handle_logout(),
    default    => json_response(false, null, 'Unknown action'),
};

// ---------------------------------------------------------------

function handle_login(): void {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(false, null, 'Email and password are required');
    }

    $stmt = db()->prepare('SELECT id, username, email, role, points, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(false, null, 'Invalid credentials');
    }

    // Apply any pending rating-timeout penalties
    require_once __DIR__ . '/../includes/helpers.php';
    apply_rating_timeouts((int)$user['id']);

    // Reload points after possible penalty
    $stmt = db()->prepare('SELECT id, username, email, role, points FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $fresh = $stmt->fetch();

    $_SESSION['user'] = $fresh;
    json_response(true, [
        'id'       => (int)$fresh['id'],
        'username' => $fresh['username'],
        'role'     => $fresh['role'],
        'points'   => (int)$fresh['points'],
    ]);
}

function handle_register(): void {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        json_response(false, null, 'All fields are required');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(false, null, 'Invalid email address');
    }
    if (strlen($password) < 6) {
        json_response(false, null, 'Password must be at least 6 characters');
    }

    $pdo = db();

    // Check uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        json_response(false, null, 'Username or email already taken');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $pdo->beginTransaction();

        $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$username, $email, $hash]);
        $new_id = (int)$pdo->lastInsertId();

        // Insert initial point event — guarded with WHERE NOT EXISTS to be idempotent
        $pdo->prepare("
            INSERT INTO point_events (user_id, request_id, delta, reason)
            SELECT :uid, NULL, 5, 'initial'
            WHERE NOT EXISTS (
                SELECT 1 FROM point_events
                WHERE user_id = :uid2 AND reason = 'initial' AND request_id IS NULL
            )
        ")->execute([':uid' => $new_id, ':uid2' => $new_id]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        json_response(false, null, 'Registration failed. Try a different username/email.');
    }

    $stmt = $pdo->prepare('SELECT id, username, email, role, points FROM users WHERE id = ?');
    $stmt->execute([$new_id]);
    $_SESSION['user'] = $stmt->fetch();

    json_response(true, [
        'id'       => $new_id,
        'username' => $username,
        'role'     => 'student',
        'points'   => 5,
    ]);
}

function handle_logout(): void {
    $_SESSION = [];
    session_destroy();
    json_response(true, null);
}
