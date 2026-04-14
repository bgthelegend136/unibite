<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$u = current_user();

$allergens = db()->query('SELECT id, name FROM allergens ORDER BY name')->fetchAll();
$edit_id = (int)($_GET['id'] ?? 0);

if ($edit_id > 0) {
    $stmt = db()->prepare("
        SELECT id
        FROM listings
        WHERE id = ?
          AND provider_id = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ");
    $stmt->execute([$edit_id, current_user_id()]);
    if (!$stmt->fetch()) {
        header('Location: /my-listings.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - <?= $edit_id > 0 ? 'Edit Listing' : 'Post Food' ?></title>
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

<main class="container listing-page">
  <div class="page-head">
    <div>
      <p class="eyebrow"><?= $edit_id > 0 ? 'Edit your listing' : 'Share surplus food' ?></p>
      <h1><?= $edit_id > 0 ? 'Update listing' : 'Post food' ?></h1>
      <p class="text-muted">Pick a map location, set pickup time, and choose any allergens that apply.</p>
    </div>
    <a class="btn btn--ghost" href="/my-listings.php">Back to my listings</a>
  </div>

  <div class="listing-layout">
    <section class="card">
      <p id="listing-msg" class="msg" hidden></p>
      <form id="listing-form" enctype="multipart/form-data" novalidate>
        <input type="hidden" id="listing-id" name="id" value="<?= $edit_id > 0 ? $edit_id : '' ?>">
        <div class="form-group">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" required>
        </div>
        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="4"></textarea>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="portions_total">Total portions</label>
            <input type="number" id="portions_total" name="portions_total" min="1" step="1" required>
          </div>
          <div class="form-group">
            <label for="pickup_time">Pickup time</label>
            <input type="datetime-local" id="pickup_time" name="pickup_time" required>
          </div>
        </div>
        <div class="form-group">
          <label for="pickup_location">Pickup location</label>
          <input type="text" id="pickup_location" name="pickup_location" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="pickup_lat">Latitude</label>
            <input type="text" id="pickup_lat" name="pickup_lat" readonly required>
          </div>
          <div class="form-group">
            <label for="pickup_lng">Longitude</label>
            <input type="text" id="pickup_lng" name="pickup_lng" readonly required>
          </div>
        </div>
        <div class="form-group">
          <label for="photo">Photo <span class="text-muted">(optional)</span></label>
          <input type="file" id="photo" name="photo" accept="image/*">
          <div id="photo-preview" class="photo-preview" hidden></div>
        </div>
        <div class="form-group">
          <label>Allergens</label>
          <div class="allergen-grid">
            <?php foreach ($allergens as $a): ?>
              <label class="check-pill">
                <input type="checkbox" name="allergens[]" value="<?= (int)$a['id'] ?>">
                <span><?= htmlspecialchars($a['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" class="btn btn--primary btn--full" id="listing-submit">
          <?= $edit_id > 0 ? 'Update listing' : 'Create listing' ?>
        </button>
      </form>
    </section>

    <aside class="card listing-map-card">
      <div class="section-head">
        <h2>Pick a spot</h2>
        <p class="text-muted">Click the map to place the pickup pin.</p>
      </div>
      <div id="listing-map" class="listing-map"></div>
    </aside>
  </div>
</main>

<script>
  window.UNIBITE_LISTING_EDIT_ID = <?= $edit_id > 0 ? $edit_id : 'null' ?>;
</script>
<script src="/js/api.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/app.js"></script>
<script src="/js/listing.js"></script>
</body>
</html>
