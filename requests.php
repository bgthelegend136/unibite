<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$u = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - Requests</title>
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

<main class="container request-page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Incoming requests</p>
      <h1>Requests for my listings</h1>
      <p class="text-muted">Approve or reject pending requests, then confirm pickups or no-shows for approved requests.</p>
    </div>
    <div class="request-page__switch">
      <a class="btn btn--ghost" href="/my-requests.php">Outgoing requests</a>
    </div>
  </div>

  <p id="requests-msg" class="msg" hidden></p>
  <section class="request-list card" id="requests-list" data-request-scope="incoming"></section>
</main>

<script>
  window.UNIBITE_REQUEST_PAGE = 'incoming';
</script>
<script src="/js/api.js"></script>
<script src="/js/app.js"></script>
<script src="/js/requests.js"></script>
</body>
</html>
