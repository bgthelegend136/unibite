<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$u = current_user();
$id = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - Listing</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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

<main class="container listing-detail-page">
  <p id="listing-detail-msg" class="msg" hidden></p>
  <div id="listing-detail" class="listing-detail card" data-listing-id="<?= $id ?>"></div>
</main>

<script>
  window.UNIBITE_FEED_USER = {
    id: <?= (int)$u['id'] ?>,
    username: <?= json_encode($u['username']) ?>,
  };
  window.UNIBITE_LISTING_ID = <?= $id ?>;
</script>
<script src="/js/api.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/app.js"></script>
<script src="/js/map.js"></script>
<script src="/js/feed.js"></script>
</body>
</html>
