<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

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
  <title>UniBite - Register</title>
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
    <h1>Create account</h1>

    <form id="reg-form" novalidate>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
      </div>
      <div class="form-group">
        <label for="password">Password <span class="text-muted">(min 6 chars)</span></label>
        <input type="password" id="password" name="password" autocomplete="new-password" required minlength="6">
      </div>
      <p id="reg-msg" class="msg" hidden></p>
      <button type="submit" class="btn btn--primary btn--full mt-2">Register</button>
    </form>

    <p class="text-muted mt-2" style="text-align:center">
      Already have an account? <a href="/index.php">Log in</a>
    </p>
    <p class="text-muted mt-1" style="text-align:center; font-size:.8rem">
      New accounts start with <strong>5 points</strong> to get you going.
    </p>
  </div>
</div>

<script src="/js/api.js"></script>
<script>
document.getElementById('reg-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg  = document.getElementById('reg-msg');
  const btn  = e.target.querySelector('button[type=submit]');
  const username = document.getElementById('username').value.trim();
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;

  clearMsg(msg);

  if (!username || !email || !password) {
    showMsg(msg, 'All fields are required'); return;
  }
  if (password.length < 6) {
    showMsg(msg, 'Password must be at least 6 characters'); return;
  }

  btn.disabled = true;
  const res = await apiPost('/api/auth.php', { action: 'register', username, email, password });
  btn.disabled = false;

  if (!res.ok) {
    showMsg(msg, res.error || 'Registration failed');
    return;
  }
  showMsg(msg, 'Account created! Redirecting…', 'success');
  setTimeout(() => { window.location.href = '/home.php'; }, 800);
});
</script>
</body>
</html>
