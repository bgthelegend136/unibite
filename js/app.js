/**
 * Common UI: nav toggle, logout.
 */
document.addEventListener('DOMContentLoaded', () => {
  function formatPointsLabel(points) {
    const value = Number(points || 0);
    return value + ' ' + (value === 1 ? 'pt' : 'pts');
  }

  window.UniBiteUI = window.UniBiteUI || {};
  window.UniBiteUI.setNavPoints = function setNavPoints(points) {
    const badge = document.querySelector('.nav__points');
    if (!badge) return;
    badge.textContent = '⭐ ' + formatPointsLabel(points);
  };

  // Hamburger nav toggle (mobile)
  const burger = document.getElementById('nav-burger');
  const navMenu = document.getElementById('nav-menu');
  if (burger && navMenu) {
    burger.addEventListener('click', () => {
      const open = navMenu.dataset.open === 'true';
      navMenu.dataset.open = open ? 'false' : 'true';
      burger.setAttribute('aria-expanded', String(!open));
    });
  }

  // Logout button
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      await apiPost('/api/auth.php', { action: 'logout' });
      window.location.href = '/index.php';
    });
  }
});
