<?php
// Session helpers and auth guards

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Return the current user array from session, or null.
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Return current user id or null.
 */
function current_user_id(): ?int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/**
 * Redirect to login if not logged in.
 */
function require_login(): void {
    if (!current_user()) {
        header('Location: /index.php');
        exit;
    }
}

/**
 * Require login and a specific role; redirect or 403 otherwise.
 * $context: 'page' (redirect) or 'api' (JSON 403)
 */
function require_role(string $role, string $context = 'page'): void {
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        if ($context === 'api') {
            http_response_code(403);
            json_response(false, null, 'Forbidden');
        }
        header('Location: /index.php');
        exit;
    }
}

/**
 * Require login for API; sends JSON 401 if not logged in.
 */
function require_login_api(): void {
    if (!current_user()) {
        http_response_code(401);
        json_response(false, null, 'Unauthenticated');
    }
}

/**
 * Refresh the session user data from DB (call after point changes).
 */
function refresh_session_user(): void {
    $uid = current_user_id();
    if (!$uid) return;
    $stmt = db()->prepare('SELECT id, username, email, role, points FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if ($u) $_SESSION['user'] = $u;
}
