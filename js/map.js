/**
 * Leaflet and distance helpers shared by feed and listing detail pages.
 */
(function () {
  function toRad(value) {
    return (Number(value) * Math.PI) / 180;
  }

  function haversineKm(aLat, aLng, bLat, bLng) {
    const r = 6371;
    const dLat = toRad(bLat - aLat);
    const dLng = toRad(bLng - aLng);
    const lat1 = toRad(aLat);
    const lat2 = toRad(bLat);
    const sinLat = Math.sin(dLat / 2);
    const sinLng = Math.sin(dLng / 2);
    const h = sinLat * sinLat + Math.cos(lat1) * Math.cos(lat2) * sinLng * sinLng;
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(h)));
  }

  function formatDistance(km) {
    if (!Number.isFinite(km)) return '';
    if (km < 1) return Math.round(km * 1000) + ' m';
    return km.toFixed(km < 10 ? 1 : 0) + ' km';
  }

  function createMap(el, center, zoom) {
    const map = L.map(el).setView(center, zoom || 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    return map;
  }

  function addListingMarker(map, listing, options = {}) {
    const marker = L.marker([listing.pickup_lat, listing.pickup_lng]).addTo(map);
    const link = '/listing.php?id=' + listing.id;
    const label = '<div class="map-popup"><strong>' + escapeHtml(listing.title) + '</strong><br>' +
      escapeHtml(listing.pickup_location) + '<br><a href="' + link + '">View details</a></div>';
    marker.bindPopup(label);
    if (options.openPopup) marker.openPopup();
    return marker;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  window.UniBiteMap = {
    haversineKm,
    formatDistance,
    createMap,
    addListingMarker,
    escapeHtml,
  };
})();
