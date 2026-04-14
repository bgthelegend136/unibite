<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Redirect based on role.
if ($u = current_user()) {
    header('Location: ' . (($u['role'] ?? '') === 'admin' ? '/admin.php' : '/home.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - Login</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="nav">
  <a class="nav__brand" href="/index.php">🍴 UniBite</a>
</nav>

<div class="auth-wrap">
  <div class="auth-box">
    <p class="auth-box__brand">&#127869; UniBite</p>
    <p class="auth-box__tagline">Share food, save meals</p>
    <h1>Welcome back</h1>

    <form id="login-form" novalidate>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <p id="login-msg" class="msg" hidden></p>
      <button type="submit" class="btn btn--primary btn--full mt-2">Log in</button>
    </form>

    <p class="text-muted mt-2" style="text-align:center">
      No account? <a href="/register.php">Register</a>
    </p>
  </div>
</div>

<script src="/js/api.js"></script>
<script>
document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg   = document.getElementById('login-msg');
  const btn   = e.target.querySelector('button[type=submit]');
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;

  clearMsg(msg);
  btn.disabled = true;

  const res = await apiPost('/api/auth.php', { action: 'login', email, password });
  btn.disabled = false;

  if (!res.ok) {
    showMsg(msg, res.error || 'Login failed');
    return;
  }

  window.location.href = res.data?.role === 'admin' ? '/admin.php' : '/home.php';
});
</script>
</body>
</html>
