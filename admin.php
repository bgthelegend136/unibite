<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
require_role('admin');
$u = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UniBite - Admin</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<nav class="nav">
  <a class="nav__brand" href="/home.php">UniBite</a>
  <ul class="nav__menu" id="nav-menu">
    <li><a href="/admin.php">Admin</a></li>
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

<main class="container admin-page">
  <div class="page-head">
    <div>
      <p class="eyebrow">Oversight</p>
      <h1>Admin dashboard</h1>
      <p class="text-muted">Track recent sharing volume, top donors, and the best-rated meals.</p>
    </div>
  </div>

  <p id="admin-msg" class="msg" hidden></p>

  <section class="admin-grid">
    <article class="card admin-stat-card">
      <p class="admin-stat-card__label">Shared last month</p>
      <h2 id="stat-total-shared">--</h2>
      <p class="text-muted">Completed pickups in the last month.</p>
    </article>

    <article class="card admin-stat-card">
      <p class="admin-stat-card__label">Top donor</p>
      <h2 id="stat-top-donor">--</h2>
      <p id="stat-top-donor-note" class="text-muted">Most successfully shared portions.</p>
    </article>
  </section>

  <section class="card admin-list-card">
    <div class="section-head">
      <h2>Highest-rated meals</h2>
      <p class="text-muted">Listings ranked by average rating.</p>
    </div>
    <div id="admin-rated-meals"></div>
  </section>
</main>

<script src="/js/api.js"></script>
<script src="/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const msg = document.getElementById('admin-msg');
  const totalShared = document.getElementById('stat-total-shared');
  const topDonor = document.getElementById('stat-top-donor');
  const topDonorNote = document.getElementById('stat-top-donor-note');
  const mealsEl = document.getElementById('admin-rated-meals');

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  const res = await apiPost('/api/stats.php', { action: 'dashboard' });
  if (!res.ok) {
    showMsg(msg, res.error || 'Unable to load dashboard');
    return;
  }

  const data = res.data || {};
  totalShared.textContent = String(data.total_shared_last_month ?? 0);

  if (data.top_donor) {
    topDonor.textContent = data.top_donor.username;
    topDonorNote.textContent = data.top_donor.shared_count + ' successful pickups';
  } else {
    topDonor.textContent = 'No data yet';
    topDonorNote.textContent = 'Successful pickups will appear here.';
  }

  const meals = Array.isArray(data.highest_rated_meals) ? data.highest_rated_meals : [];
  if (!meals.length) {
    mealsEl.innerHTML = '<div class=\"empty-state\"><h2>No ratings yet</h2><p class=\"text-muted\">Rated meals will appear here once students leave feedback.</p></div>';
    return;
  }

  mealsEl.innerHTML = '<div class=\"admin-meal-list\">' + meals.map((meal) => {
    return '' +
      '<article class=\"admin-meal-row\">' +
        '<div>' +
          '<h3>' + escapeHtml(meal.title) + '</h3>' +
          '<p class=\"text-muted\">By ' + escapeHtml(meal.provider_username) + '</p>' +
        '</div>' +
        '<div class=\"admin-meal-row__score\">' +
          '<strong>' + escapeHtml(meal.average_rating.toFixed(2)) + '</strong>' +
          '<span class=\"text-muted\">(' + escapeHtml(String(meal.rating_count)) + (meal.rating_count === 1 ? ' rating' : ' ratings') + ')</span>' +
        '</div>' +
      '</article>';
  }).join('') + '</div>';
});
</script>
</body>
</html>
