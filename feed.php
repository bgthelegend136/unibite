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
  <title>UniBite - Feed</title>
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

<main class="container feed-page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Available meals</p>
      <h1>Food feed</h1>
      <p class="text-muted">Use your location or pick a point on the map to sort meals by distance.</p>
    </div>
  </div>

  <section class="card feed-controls">
    <div class="feed-controls__row">
      <button type="button" class="btn btn--primary" id="btn-location">Use my location</button>
      <button type="button" class="btn btn--ghost" id="btn-reset-location">Clear location</button>
      <label class="feed-control">
        <span class="feed-control__label">Max distance</span>
        <select id="distance-filter">
          <option value="">Any</option>
          <option value="1">1 km</option>
          <option value="3">3 km</option>
          <option value="5">5 km</option>
          <option value="10">10 km</option>
        </select>
      </label>
      <label class="check-pill feed-sort-pill">
        <input type="checkbox" id="sort-distance">
        <span>Sort nearest first</span>
      </label>
    </div>
    <p id="feed-location-label" class="text-muted">No location selected.</p>
  </section>

  <div class="feed-layout">
    <section class="card">
      <div class="feed-view-toggle">
        <button type="button" class="btn btn--primary" id="btn-list-view">List view</button>
        <button type="button" class="btn btn--ghost" id="btn-map-view">Map view</button>
      </div>

      <p id="feed-msg" class="msg" hidden></p>

      <div id="feed-list-view">
        <div id="feed-list" class="listing-grid"></div>
      </div>

      <div id="feed-map-view" hidden>
        <div id="feed-map" class="feed-map"></div>
      </div>
    </section>

    <aside class="card feed-sidebar">
      <div class="section-head">
        <h2>How it works</h2>
      </div>
      <ul class="feed-notes">
        <li>Active meals have portions available.</li>
        <li>Inactive meals are still visible but greyed out.</li>
        <li>Expired meals stay hidden from the feed.</li>
      </ul>
    </aside>
  </div>
</main>

<script>
  window.UNIBITE_FEED_USER = {
    id: <?= (int)$u['id'] ?>,
    username: <?= json_encode($u['username']) ?>,
  };
</script>
<script src="/js/api.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/app.js"></script>
<script src="/js/map.js"></script>
<script src="/js/feed.js"></script>
</body>
</html>
