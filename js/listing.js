/**
 * Listing CRUD interactions: Leaflet picker, create/update form, delete buttons.
 */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('listing-form');
  const msg = document.getElementById('listing-msg');
  const submitBtn = document.getElementById('listing-submit');
  const editId = window.UNIBITE_LISTING_EDIT_ID || null;
  const mapEl = document.getElementById('listing-map');

  const latInput = document.getElementById('pickup_lat');
  const lngInput = document.getElementById('pickup_lng');
  const photoInput = document.getElementById('photo');
  const photoPreview = document.getElementById('photo-preview');

  let map = null;
  let marker = null;

  function setPoint(lat, lng) {
    if (!latInput || !lngInput) return;
    latInput.value = Number(lat).toFixed(7);
    lngInput.value = Number(lng).toFixed(7);
    if (marker) {
      marker.setLatLng([lat, lng]);
    }
    if (map) {
      map.setView([lat, lng], Math.max(map.getZoom(), 15));
    }
  }

  function initMap() {
    if (!mapEl || typeof L === 'undefined') return;

    const defaultCenter = [37.9838, 23.7275];
    const startLat = parseFloat(latInput?.value || '');
    const startLng = parseFloat(lngInput?.value || '');
    const hasStart = Number.isFinite(startLat) && Number.isFinite(startLng);

    map = L.map(mapEl).setView(hasStart ? [startLat, startLng] : defaultCenter, hasStart ? 15 : 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    marker = L.marker(hasStart ? [startLat, startLng] : defaultCenter, { draggable: true }).addTo(map);
    if (!hasStart) {
      setPoint(defaultCenter[0], defaultCenter[1]);
    }

    map.on('click', (e) => setPoint(e.latlng.lat, e.latlng.lng));
    marker.on('dragend', (e) => {
      const pos = e.target.getLatLng();
      setPoint(pos.lat, pos.lng);
    });
  }

  async function loadListing(id) {
    if (!id || !form) return;

    const res = await apiPost('/api/listings.php', { action: 'get', context: 'edit', id });
    if (!res.ok) {
      showMsg(msg, res.error || 'Could not load listing');
      submitBtn.disabled = true;
      return;
    }

    const data = res.data;
    document.getElementById('title').value = data.title || '';
    document.getElementById('description').value = data.description || '';
    document.getElementById('portions_total').value = data.portions_total || '';
    document.getElementById('pickup_location').value = data.pickup_location || '';
    document.getElementById('pickup_lat').value = data.pickup_lat || '';
    document.getElementById('pickup_lng').value = data.pickup_lng || '';

    if (data.pickup_time) {
      document.getElementById('pickup_time').value = String(data.pickup_time).replace(' ', 'T').slice(0, 16);
    }

    const selected = new Set((data.allergen_ids || []).map(Number));
    document.querySelectorAll('input[name="allergens[]"]').forEach((cb) => {
      cb.checked = selected.has(Number(cb.value));
    });

    if (data.photo_url && photoPreview) {
      photoPreview.hidden = false;
      photoPreview.innerHTML = '<img src="' + data.photo_url + '" alt="Current photo">';
    }

    if (Number.isFinite(parseFloat(data.pickup_lat)) && Number.isFinite(parseFloat(data.pickup_lng))) {
      setPoint(data.pickup_lat, data.pickup_lng);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form) return;

    clearMsg(msg);
    submitBtn.disabled = true;

    const fd = new FormData(form);
    fd.set('action', editId ? 'update' : 'create');
    if (editId) {
      fd.set('id', editId);
    }
    if (!photoInput || !photoInput.files || !photoInput.files[0]) {
      fd.delete('photo');
    }

    const requiredFields = [
      'id',
      'title',
      'description',
      'portions_total',
      'pickup_location',
      'pickup_lat',
      'pickup_lng',
      'pickup_time',
    ];
    const missingField = requiredFields.find((name) => editId && !String(fd.get(name) || '').trim());
    if (missingField) {
      submitBtn.disabled = false;
      showMsg(msg, 'Missing field: ' + missingField);
      return;
    }

    const res = await fetch('/api/listings.php', {
      method: 'POST',
      body: fd,
    }).then((r) => r.json());

    submitBtn.disabled = false;

    if (!res.ok) {
      showMsg(msg, res.error || 'Unable to save listing');
      return;
    }

    window.location.href = '/my-listings.php';
  }

  async function handleDeleteClick(button) {
    const id = button.dataset.deleteId;
    if (!id) return;
    if (!window.confirm('Delete this listing?')) return;

    const res = await apiPost('/api/listings.php', { action: 'delete', id });
    if (!res.ok) {
      const msgEl = document.getElementById('my-listings-msg');
      if (msgEl) showMsg(msgEl, res.error || 'Unable to delete listing');
      return;
    }

    window.location.reload();
  }

  if (mapEl) {
    initMap();
  }

  if (form) {
    form.addEventListener('submit', handleSubmit);
    if (editId) {
      loadListing(editId);
    }
  }

  document.querySelectorAll('.js-delete-listing').forEach((button) => {
    button.addEventListener('click', () => handleDeleteClick(button));
  });

  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0];
      if (!file) {
        if (photoPreview.dataset.keepExisting !== 'true') {
          photoPreview.hidden = true;
          photoPreview.innerHTML = '';
        }
        return;
      }

      const reader = new FileReader();
      reader.onload = () => {
        photoPreview.hidden = false;
        photoPreview.dataset.keepExisting = 'false';
        photoPreview.innerHTML = '<img src="' + reader.result + '" alt="Selected photo preview">';
      };
      reader.readAsDataURL(file);
    });
  }
});
