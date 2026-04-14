<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$u = current_user();
if (($u['role'] ?? '') === 'admin') {
    header('Location: /admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="nav">
  <a class="nav__brand" href="/home.php">UniBite</a>
  <ul class="nav__menu" id="nav-menu">
    <li><a href="/feed.php">Feed</a></li>
    <li><a href="/create-listing.php">Post Food</a></li>
    <li><a href="/my-listings.php">My Listings</a></li>
    <li><a href="/requests.php">Incoming Requests</a></li>
    <li><a href="/my-requests.php">My Requests</a></li>
    <li><span class="nav__points">&#11088; <?= htmlspecialchars(format_points_label((int)$u['points'])) ?></span></li>
    <li><a href="#" id="btn-logout">Log out</a></li>
  </ul>
  <button class="nav__burger" id="nav-burger" aria-expanded="false" aria-label="Menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
</nav>

<main class="container home-container">
  <div class="home-hero card">
    <p class="home-hero__icon">&#127869;</p>
    <h1 class="home-hero__title">Welcome, <?= htmlspecialchars($u['username']) ?>!</h1>
    <p class="home-hero__tagline">Share food, save meals</p>
    <div class="home-hero__points">
      <span class="nav__points">&#11088; <?= htmlspecialchars(format_points_label((int)$u['points'])) ?></span>
    </div>
    <a class="btn btn--primary btn--lg" href="/feed.php">Browse available food</a>
  </div>

  <div class="home-grid">
    <a class="card home-action-card" href="/create-listing.php">
      <span class="home-action-card__icon">&#129379;</span>
      <h2>Post food</h2>
      <p class="text-muted">Share surplus portions with your campus community.</p>
    </a>
    <a class="card home-action-card" href="/my-listings.php">
      <span class="home-action-card__icon">&#128203;</span>
      <h2>My listings</h2>
      <p class="text-muted">Manage your active food posts and track requests.</p>
    </a>
    <a class="card home-action-card" href="/requests.php">
      <span class="home-action-card__icon">&#128229;</span>
      <h2>Incoming requests</h2>
      <p class="text-muted">Review and respond to requests from other students.</p>
    </a>
    <a class="card home-action-card" href="/my-requests.php">
      <span class="home-action-card__icon">&#128228;</span>
      <h2>My requests</h2>
      <p class="text-muted">Track food you've requested and leave ratings.</p>
    </a>
  </div>
</main>

<script src="/js/api.js"></script>
<script src="/js/app.js"></script>
</body>
</html>
