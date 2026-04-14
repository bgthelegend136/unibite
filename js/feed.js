/**
 * Feed rendering for list, map, and listing detail pages.
 */
document.addEventListener('DOMContentLoaded', () => {
  const feedList = document.getElementById('feed-list');
  const feedMapEl = document.getElementById('feed-map');
  const feedMsg = document.getElementById('feed-msg');
  const detailEl = document.getElementById('listing-detail');
  const detailMsg = document.getElementById('listing-detail-msg');

  const btnLocation = document.getElementById('btn-location');
  const btnResetLocation = document.getElementById('btn-reset-location');
  const distanceFilter = document.getElementById('distance-filter');
  const sortDistance = document.getElementById('sort-distance');
  const btnListView = document.getElementById('btn-list-view');
  const btnMapView = document.getElementById('btn-map-view');
  const listView = document.getElementById('feed-list-view');
  const mapView = document.getElementById('feed-map-view');
  const locationLabel = document.getElementById('feed-location-label');

  const isFeedPage = !!feedList;
  const isDetailPage = !!detailEl;
  const currentUserId = Number(window.UNIBITE_FEED_USER?.id || 0);
  let listings = [];
  let location = null;
  let map = null;
  let markers = [];
  let locationMarker = null;
  let locationCircle = null;
  const requestedListingIds = new Set();

  function setMsg(el, message, type = 'error') {
    if (!el) return;
    if (!message) {
      clearMsg(el);
      return;
    }
    showMsg(el, message, type);
  }

  function setLocationLabel() {
    if (!locationLabel) return;
    if (!location) {
      locationLabel.textContent = 'No location selected.';
      return;
    }
    locationLabel.textContent = 'Distance from ' + location.label;
  }

  function computeListingsWithDistance() {
    return listings.map((listing) => {
      const distanceKm = location
        ? window.UniBiteMap.haversineKm(location.lat, location.lng, listing.pickup_lat, listing.pickup_lng)
        : null;
      return { ...listing, distance_km: distanceKm };
    });
  }

  function applyFilters(rows) {
    let filtered = rows.filter((listing) => {
      if (!distanceFilter || !distanceFilter.value || !Number.isFinite(listing.distance_km)) return true;
      return listing.distance_km <= Number(distanceFilter.value);
    });

    if (sortDistance && sortDistance.checked && location) {
      filtered = filtered.slice().sort((a, b) => {
        const da = Number.isFinite(a.distance_km) ? a.distance_km : Number.POSITIVE_INFINITY;
        const db = Number.isFinite(b.distance_km) ? b.distance_km : Number.POSITIVE_INFINITY;
        return da - db;
      });
    }

    return filtered;
  }

  function statusLabel(listing) {
    if (listing.listing_status === 'inactive') return 'Inactive';
    if (listing.listing_status === 'active') return 'Active';
    return 'Expired';
  }

  function renderCard(listing) {
    const link = '/listing.php?id=' + listing.id;
    const photo = listing.photo_url
      ? '<img src="' + listing.photo_url + '" alt="' + window.UniBiteMap.escapeHtml(listing.title) + '" onerror="this.outerHTML=\'<div class=\\\'listing-card__placeholder\\\'>Meal</div>\'">'
      : '<div class="listing-card__placeholder">Meal</div>';
    const dist = Number.isFinite(listing.distance_km)
      ? '<span class="listing-distance">' + window.UniBiteMap.formatDistance(listing.distance_km) + '</span>'
      : '';
    const allergens = listing.allergens ? '<p class="listing-allergens text-muted">' + window.UniBiteMap.escapeHtml(listing.allergens) + '</p>' : '';

    const requestButton = renderRequestButton(listing);

    return '' +
      '<article class="card listing-card listing-card--' + listing.listing_status + '">' +
        '<div class="listing-card__media">' + photo + '</div>' +
        '<div class="listing-card__body">' +
          '<div class="listing-card__top">' +
            '<div>' +
              '<h2>' + window.UniBiteMap.escapeHtml(listing.title) + '</h2>' +
              '<p class="listing-card__meta text-muted">' + window.UniBiteMap.escapeHtml(listing.pickup_location) + '</p>' +
            '</div>' +
            '<span class="status-pill status-pill--' + listing.listing_status + '">' + statusLabel(listing) + '</span>' +
          '</div>' +
          '<p class="text-muted">' + window.UniBiteMap.escapeHtml(listing.description || 'No description added.') + '</p>' +
          allergens +
          '<div class="listing-meta">' +
            '<div><dt>Portions</dt><dd>' + listing.portions_available + ' / ' + listing.portions_total + '</dd></div>' +
            '<div><dt>Pickup</dt><dd>' + window.UniBiteMap.escapeHtml(listing.pickup_time) + '</dd></div>' +
            (dist ? '<div class="listing-meta--full"><dt>Distance</dt><dd>' + dist + '</dd></div>' : '') +
          '</div>' +
          '<div class="listing-actions">' +
            '<a class="btn btn--ghost" href="' + link + '">View details</a>' +
            requestButton +
          '</div>' +
        '</div>' +
      '</article>';
  }

  function requestButtonState(listing) {
    if (requestedListingIds.has(listing.id)) {
      return { label: 'Requested', disabled: true, cls: 'btn btn--ghost' };
    }
    if (listing.provider_id === currentUserId) {
      return { label: 'Your listing', disabled: true, cls: 'btn btn--ghost' };
    }
    if (listing.listing_status !== 'active') {
      return { label: 'Sold out', disabled: true, cls: 'btn btn--ghost' };
    }
    return { label: 'Request portion', disabled: false, cls: 'btn btn--primary js-request-listing' };
  }

  function renderRequestButton(listing) {
    const state = requestButtonState(listing);
    return '<button type="button" class="' + state.cls + '" data-listing-id="' + listing.id + '"' + (state.disabled ? ' disabled' : '') + '>' + state.label + '</button>';
  }

  function updateRequestButtons(listingId, label) {
    document.querySelectorAll('.js-request-listing[data-listing-id="' + listingId + '"]').forEach((button) => {
      button.textContent = label;
      button.disabled = true;
      button.classList.remove('btn--primary');
      button.classList.add('btn--ghost');
      button.classList.remove('js-request-listing');
    });
  }

  function renderMap(rows) {
    if (!feedMapEl || typeof L === 'undefined') return;
    if (!map) {
      const fallback = rows.length ? [rows[0].pickup_lat, rows[0].pickup_lng] : [37.9838, 23.7275];
      map = window.UniBiteMap.createMap(feedMapEl, fallback, rows.length ? 13 : 12);
      map.on('click', (e) => {
        setSelectedLocation(e.latlng.lat, e.latlng.lng, 'map pin');
      });
    }
    markers.forEach((marker) => marker.remove());
    markers = [];
    if (locationMarker) {
      locationMarker.remove();
      locationMarker = null;
    }
    if (locationCircle) {
      locationCircle.remove();
      locationCircle = null;
    }
    rows.forEach((listing) => {
      markers.push(window.UniBiteMap.addListingMarker(map, listing));
    });
    if (location) {
      locationCircle = L.circle([location.lat, location.lng], {
        radius: 1000,
        color: '#e8630a',
        fillColor: '#e8630a',
        fillOpacity: 0.08,
      }).addTo(map);
      locationMarker = L.marker([location.lat, location.lng]).addTo(map);
      locationMarker.bindPopup('<div class="map-popup"><strong>Selected location</strong><br>' + window.UniBiteMap.escapeHtml(location.label) + '</div>');
    }
  }

  function renderFeed() {
    if (!feedList) return;
    const rows = applyFilters(computeListingsWithDistance());
    if (!rows.length) {
      feedList.innerHTML = '<div class="card empty-state"><h2>No meals found</h2><p class="text-muted">Try clearing the distance filter or using a different location.</p></div>';
      renderMap([]);
      return;
    }
    feedList.innerHTML = rows.map(renderCard).join('');
    renderMap(rows);
  }

  async function loadListings() {
    if (!isFeedPage && !isDetailPage) return;
    const res = await apiPost('/api/listings.php', { action: 'list' });
    if (!res.ok) {
      setMsg(feedMsg || detailMsg, res.error || 'Could not load listings');
      return;
    }
    listings = res.data || [];
    if (isFeedPage) {
      renderFeed();
    }
  }

  function toggleView(which) {
    if (!listView || !mapView) return;
    const listActive = which === 'list';
    listView.hidden = !listActive;
    mapView.hidden = listActive;
    if (btnListView && btnMapView) {
      btnListView.className = 'btn ' + (listActive ? 'btn--primary' : 'btn--ghost');
      btnMapView.className = 'btn ' + (!listActive ? 'btn--primary' : 'btn--ghost');
    }
    if (!listActive && map && location && listings.length) {
      map.setView([location.lat, location.lng], 13);
      setTimeout(() => map.invalidateSize(), 0);
    } else if (!listActive && map) {
      setTimeout(() => map.invalidateSize(), 0);
    }
  }

  function setSelectedLocation(lat, lng, label) {
    location = { lat, lng, label };
    setLocationLabel();
    renderFeed();
  }

  function useBrowserLocation() {
    if (!navigator.geolocation) {
      setMsg(feedMsg, 'Geolocation is not available in this browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setSelectedLocation(pos.coords.latitude, pos.coords.longitude, 'your current location');
      },
      () => setMsg(feedMsg, 'Unable to read your location. Try again or clear the filter.')
    );
  }

  function bindUI() {
    if (btnLocation) {
      btnLocation.addEventListener('click', useBrowserLocation);
    }
    if (btnResetLocation) {
      btnResetLocation.addEventListener('click', () => {
        location = null;
        if (locationLabel) locationLabel.textContent = 'No location selected.';
        renderFeed();
      });
    }
    if (distanceFilter) distanceFilter.addEventListener('change', renderFeed);
    if (sortDistance) sortDistance.addEventListener('change', renderFeed);
    if (btnListView) btnListView.addEventListener('click', () => toggleView('list'));
    if (btnMapView) btnMapView.addEventListener('click', () => toggleView('map'));
    document.addEventListener('click', handleRequestClick);
  }

  async function handleRequestClick(e) {
    const button = e.target.closest('.js-request-listing');
    if (!button) return;

    const listingId = Number(button.dataset.listingId || 0);
    if (!listingId) return;

    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = 'Sending...';

    const res = await apiPost('/api/requests.php', {
      action: 'send',
      listing_id: listingId,
    });

    if (!res.ok) {
      button.disabled = false;
      button.textContent = originalLabel;
      setMsg(feedMsg || detailMsg, res.error || 'Unable to send request');
      return;
    }

    requestedListingIds.add(listingId);
    updateRequestButtons(listingId, 'Requested');
    setMsg(feedMsg || detailMsg, 'Request sent', 'success');
  }

  async function loadDetail() {
    if (!detailEl) return;
    const listingId = Number(window.UNIBITE_LISTING_ID || detailEl.dataset.listingId || 0);
    if (!listingId) {
      setMsg(detailMsg, 'Listing not found');
      detailEl.innerHTML = '<div class="empty-state"><h2>Listing not found</h2></div>';
      return;
    }

    const res = await apiPost('/api/listings.php', { action: 'get', id: listingId });
    if (!res.ok) {
      setMsg(detailMsg, res.error || 'Listing not found');
      detailEl.innerHTML = '<div class="empty-state"><h2>Listing not found</h2><p class="text-muted">This listing may have expired.</p></div>';
      return;
    }

    const listing = res.data;
    const photo = listing.photo_url
      ? '<img class="listing-detail__photo" src="' + listing.photo_url + '" alt="' + window.UniBiteMap.escapeHtml(listing.title) + '" onerror="this.outerHTML=\'<div class=\\\'listing-detail__photo listing-detail__photo--placeholder\\\'>Meal</div>\'">'
      : '<div class="listing-detail__photo listing-detail__photo--placeholder">Meal</div>';
    const allergens = listing.allergens ? '<p class="text-muted">' + window.UniBiteMap.escapeHtml(listing.allergens) + '</p>' : '<p class="text-muted">No allergens listed.</p>';
    const requestButton = renderRequestButton(listing);

    detailEl.innerHTML = '' +
      '<div class="listing-detail__hero">' +
        photo +
        '<div class="listing-detail__intro">' +
          '<span class="status-pill status-pill--' + listing.listing_status + '">' + statusLabel(listing) + '</span>' +
          '<h1>' + window.UniBiteMap.escapeHtml(listing.title) + '</h1>' +
          '<p class="text-muted">' + window.UniBiteMap.escapeHtml(listing.description || 'No description added.') + '</p>' +
          '<p class="text-muted"><strong>Pickup:</strong> ' + window.UniBiteMap.escapeHtml(listing.pickup_location) + '</p>' +
          '<p class="text-muted"><strong>Time:</strong> ' + window.UniBiteMap.escapeHtml(listing.pickup_time) + '</p>' +
          '<p class="text-muted"><strong>Portions:</strong> ' + listing.portions_available + ' / ' + listing.portions_total + '</p>' +
          allergens +
          '<div class="listing-actions">' + requestButton + '</div>' +
        '</div>' +
      '</div>' +
      '<div id="listing-detail-map" class="listing-detail__map"></div>';

    const mapEl = document.getElementById('listing-detail-map');
    if (mapEl) {
      const map = window.UniBiteMap.createMap(mapEl, [listing.pickup_lat, listing.pickup_lng], 15);
      window.UniBiteMap.addListingMarker(map, listing, { openPopup: true });
    }
  }

  if (isFeedPage || isDetailPage) {
    bindUI();
  }

  if (isFeedPage) {
    loadListings().then(() => {
      if (btnListView && btnMapView) {
        toggleView('list');
      }
      setLocationLabel();
    });
  }

  if (isDetailPage) {
    loadDetail();
  }
});
