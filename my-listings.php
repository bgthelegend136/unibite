<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$u = current_user();

$stmt = db()->prepare("
    SELECT
        l.*,
        CASE
            WHEN l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) AND l.portions_available > 0 THEN 'active'
            WHEN l.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) AND l.portions_available = 0 THEN 'inactive'
            ELSE 'expired'
        END AS listing_status
    FROM listings l
    WHERE l.provider_id = ?
      AND l.deleted_by_owner_at IS NULL
    ORDER BY l.created_at DESC
");
$stmt->execute([current_user_id()]);
$listings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - My Listings</title>
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

<main class="container">
  <div class="page-head">
    <div>
      <p class="eyebrow">Your listings</p>
      <h1>Manage food posts</h1>
      <p class="text-muted">Edit active posts, see inactive ones, and keep expired posts for reference.</p>
    </div>
    <a class="btn btn--primary" href="/create-listing.php">Post food</a>
  </div>

  <p id="my-listings-msg" class="msg" hidden></p>

  <?php if (!$listings): ?>
    <div class="card empty-state">
      <h2>No listings yet</h2>
      <p class="text-muted">Post your first meal to start sharing portions.</p>
      <a class="btn btn--primary mt-2" href="/create-listing.php">Create listing</a>
    </div>
  <?php else: ?>
    <div class="listing-grid">
      <?php foreach ($listings as $listing): ?>
        <article class="card listing-card listing-card--<?= htmlspecialchars($listing['listing_status']) ?>" data-listing-id="<?= (int)$listing['id'] ?>">
          <div class="listing-card__media">
            <?php if (!empty($listing['photo_path'])): ?>
              <img src="/<?= htmlspecialchars(ltrim($listing['photo_path'], '/')) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" onerror="this.outerHTML='<div class=\'listing-card__placeholder\'>Meal</div>'">
            <?php else: ?>
              <div class="listing-card__placeholder">Meal</div>
            <?php endif; ?>
          </div>
          <div class="listing-card__body">
            <div class="listing-card__top">
              <h2><?= htmlspecialchars($listing['title']) ?></h2>
              <span class="status-pill status-pill--<?= htmlspecialchars($listing['listing_status']) ?>">
                <?= htmlspecialchars($listing['listing_status']) ?>
              </span>
            </div>
            <p class="text-muted"><?= nl2br(htmlspecialchars($listing['description'] ?: 'No description added.')) ?></p>
            <dl class="listing-meta">
              <div>
                <dt>Portions</dt>
                <dd><?= (int)$listing['portions_available'] ?> / <?= (int)$listing['portions_total'] ?></dd>
              </div>
              <div>
                <dt>Pickup</dt>
                <dd><?= htmlspecialchars($listing['pickup_time']) ?></dd>
              </div>
              <div class="listing-meta--full">
                <dt>Location</dt>
                <dd><?= htmlspecialchars($listing['pickup_location']) ?></dd>
              </div>
            </dl>
            <div class="listing-actions">
              <a class="btn btn--ghost" href="/create-listing.php?id=<?= (int)$listing['id'] ?>">Edit</a>
              <button type="button" class="btn btn--danger js-delete-listing" data-delete-id="<?= (int)$listing['id'] ?>">Delete</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<script src="/js/api.js"></script>
<script src="/js/app.js"></script>
<script src="/js/listing.js"></script>
</body>
</html>
