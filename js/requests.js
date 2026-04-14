/**
 * Requests pages: incoming provider requests and outgoing consumer requests.
 */
document.addEventListener('DOMContentLoaded', () => {
  const listEl = document.getElementById('requests-list');
  const msgEl = document.getElementById('requests-msg');
  if (!listEl) return;

  const scope = listEl.dataset.requestScope || 'incoming';

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function setMsg(message, type = 'error') {
    if (!msgEl) return;
    if (!message) {
      clearMsg(msgEl);
      return;
    }
    showMsg(msgEl, message, type);
  }

  function statusLabel(status) {
    switch (status) {
      case 'pending': return 'Pending';
      case 'approved': return 'Approved';
      case 'rejected': return 'Rejected';
      case 'picked_up': return 'Picked up';
      case 'no_show': return 'No-show';
      default: return status || 'Unknown';
    }
  }

  function statusClass(status) {
    if (status === 'approved') return 'request-status--approved';
    if (status === 'rejected') return 'request-status--rejected';
    if (status === 'picked_up') return 'request-status--picked_up';
    if (status === 'no_show') return 'request-status--no_show';
    return 'request-status--pending';
  }

  function timestampLabel(label, value) {
    return value ? label + ': ' + escapeHtml(value) : '';
  }

  function requestStatusNote(row) {
    switch (row.request_status) {
      case 'pending':
        return 'Waiting for provider review.';
      case 'approved':
        return timestampLabel('Approved at', row.approved_at) || 'Approved and awaiting pickup.';
      case 'picked_up':
        return timestampLabel('Picked up at', row.picked_up_at) || 'Pickup confirmed.';
      case 'no_show':
        return timestampLabel('Marked no-show at', row.no_show_at) || 'Marked as no-show.';
      case 'rejected':
        return 'Rejected by provider.';
      default:
        return '';
    }
  }

  function renderRatingSection(row) {
    if (scope !== 'outgoing') return '';

    if (row.rating_score) {
      return '<div class="request-rating request-rating--saved">' +
        '<p class="request-rating__label">Your rating</p>' +
        '<p class="request-rating__value">' + escapeHtml(String(row.rating_score)) + ' / 5</p>' +
      '</div>';
    }

    if (row.request_status !== 'picked_up') return '';

    return '' +
      '<form class="request-rating request-rating--form js-rating-form" data-request-id="' + row.request_id + '">' +
        '<p class="request-rating__label">Rate this pickup</p>' +
        '<div class="request-rating__choices" role="group" aria-label="Rate pickup">' +
          '<button type="submit" class="btn btn--ghost js-rating-choice" name="score" value="1" title="1 star">&#9733;</button>' +
          '<button type="submit" class="btn btn--ghost js-rating-choice" name="score" value="2" title="2 stars">&#9733;&#9733;</button>' +
          '<button type="submit" class="btn btn--ghost js-rating-choice" name="score" value="3" title="3 stars">&#9733;&#9733;&#9733;</button>' +
          '<button type="submit" class="btn btn--ghost js-rating-choice" name="score" value="4" title="4 stars">&#9733;&#9733;&#9733;&#9733;</button>' +
          '<button type="submit" class="btn btn--ghost js-rating-choice" name="score" value="5" title="5 stars">&#9733;&#9733;&#9733;&#9733;&#9733;</button>' +
        '</div>' +
      '</form>';
  }

  function listingStatusText(status) {
    if (status === 'active') return 'Active';
    if (status === 'inactive') return 'Inactive';
    return 'Expired';
  }

  function renderIncomingRow(row) {
    let actions = '';
    if (row.request_status === 'pending') {
      actions = '<div class="request-card__actions">' +
        '<button type="button" class="btn btn--primary js-request-action" data-request-id="' + row.request_id + '" data-request-action="approve">Approve</button>' +
        '<button type="button" class="btn btn--ghost js-request-action" data-request-id="' + row.request_id + '" data-request-action="reject">Reject</button>' +
      '</div>';
    } else if (row.request_status === 'approved') {
      actions = '<div class="request-card__actions">' +
        '<button type="button" class="btn btn--primary js-request-action" data-request-id="' + row.request_id + '" data-request-action="pickup">Mark picked up</button>' +
        '<button type="button" class="btn btn--ghost js-request-action" data-request-id="' + row.request_id + '" data-request-action="noshow">Mark no-show</button>' +
      '</div>';
    }

    return '' +
      '<article class="card request-card request-card--' + row.request_status + '">' +
        '<div class="request-card__top">' +
          '<div>' +
            '<p class="request-card__eyebrow">Incoming request</p>' +
            '<h2>' + escapeHtml(row.listing_title) + '</h2>' +
            '<p class="text-muted">From ' + escapeHtml(row.consumer_username) + '</p>' +
          '</div>' +
          '<span class="status-pill ' + statusClass(row.request_status) + '">' + statusLabel(row.request_status) + '</span>' +
        '</div>' +
        '<p class="text-muted">' + escapeHtml(row.listing_description || 'No description added.') + '</p>' +
        '<p class="text-muted request-card__status-note">' + escapeHtml(requestStatusNote(row)) + '</p>' +
        '<dl class="request-meta">' +
          '<div><dt>Pickup</dt><dd>' + escapeHtml(row.pickup_time) + '</dd></div>' +
          '<div><dt>Listing</dt><dd>' + listingStatusText(row.listing_status) + '</dd></div>' +
          '<div><dt>Portions</dt><dd>' + row.portions_available + ' / ' + row.portions_total + '</dd></div>' +
          '<div><dt>Requested</dt><dd>' + escapeHtml(row.request_created_at) + '</dd></div>' +
        '</dl>' +
        actions +
      '</article>';
  }

  function renderOutgoingRow(row) {
    return '' +
      '<article class="card request-card request-card--' + row.request_status + '">' +
        '<div class="request-card__top">' +
          '<div>' +
            '<p class="request-card__eyebrow">Outgoing request</p>' +
            '<h2>' + escapeHtml(row.listing_title) + '</h2>' +
            '<p class="text-muted">From ' + escapeHtml(row.provider_username) + '</p>' +
          '</div>' +
          '<span class="status-pill ' + statusClass(row.request_status) + '">' + statusLabel(row.request_status) + '</span>' +
        '</div>' +
        '<p class="text-muted">' + escapeHtml(row.listing_description || 'No description added.') + '</p>' +
        '<p class="text-muted request-card__status-note">' + escapeHtml(requestStatusNote(row)) + '</p>' +
        '<dl class="request-meta">' +
          '<div><dt>Pickup</dt><dd>' + escapeHtml(row.pickup_time) + '</dd></div>' +
          '<div><dt>Listing</dt><dd>' + listingStatusText(row.listing_status) + '</dd></div>' +
          '<div><dt>Portions</dt><dd>' + row.portions_available + ' / ' + row.portions_total + '</dd></div>' +
          '<div><dt>Requested</dt><dd>' + escapeHtml(row.request_created_at) + '</dd></div>' +
        '</dl>' +
        renderRatingSection(row) +
      '</article>';
  }

  function renderRows(rows) {
    if (!rows.length) {
      const emptyMsg = scope === 'incoming'
        ? '<h2>No incoming requests</h2><p class="text-muted">Once students request your food, their requests will appear here.</p>'
        : '<h2>No outgoing requests</h2><p class="text-muted">Browse the <a href="/feed.php">food feed</a> and request a meal to get started.</p>';
      listEl.innerHTML = '<div class="empty-state">' + emptyMsg + '</div>';
      return;
    }

    listEl.innerHTML = rows.map((row) => scope === 'incoming' ? renderIncomingRow(row) : renderOutgoingRow(row)).join('');
  }

  async function loadRequests() {
    setMsg('');
    listEl.innerHTML = '<div class="empty-state"><h2>Loading requests...</h2></div>';

    const res = await apiPost('/api/requests.php', {
      action: 'list',
      scope,
    });

    if (!res.ok) {
      setMsg(res.error || 'Could not load requests');
      listEl.innerHTML = '<div class="empty-state"><h2>Unable to load requests</h2></div>';
      return;
    }

    renderRows(res.data || []);
  }

  async function handleActionClick(e) {
    const button = e.target.closest('.js-request-action');
    if (!button) return;

    const requestId = Number(button.dataset.requestId || 0);
    const action = button.dataset.requestAction;
    if (!requestId || !action) return;

    const original = button.textContent;
    button.disabled = true;
    button.textContent = 'Working...';
    setMsg('');

    const res = await apiPost('/api/requests.php', {
      action,
      request_id: requestId,
    });

    if (!res.ok) {
      button.disabled = false;
      button.textContent = original;
      setMsg(res.error || 'Request update failed');
      return;
    }

    const successMessage = {
      approve: 'Request approved',
      reject: 'Request rejected',
      pickup: 'Pickup recorded',
      noshow: 'No-show recorded',
    }[action] || 'Request updated';

    if (typeof res.data?.current_user_points !== 'undefined' && window.UniBiteUI?.setNavPoints) {
      window.UniBiteUI.setNavPoints(res.data.current_user_points);
    }

    setMsg(successMessage, 'success');
    await loadRequests();
  }

  async function handleRatingSubmit(e) {
    const form = e.target.closest('.js-rating-form');
    if (!form) return;

    e.preventDefault();

    const submitter = e.submitter;
    const requestId = Number(form.dataset.requestId || 0);
    const score = Number(submitter?.value || 0);
    if (!requestId || score < 1 || score > 5) return;

    const buttons = Array.from(form.querySelectorAll('button'));
    buttons.forEach((button) => {
      button.disabled = true;
    });
    setMsg('');

    const res = await apiPost('/api/ratings.php', {
      action: 'submit',
      request_id: requestId,
      score,
    });

    if (!res.ok) {
      buttons.forEach((button) => {
        button.disabled = false;
      });
      setMsg(res.error || 'Rating submission failed');
      return;
    }

    setMsg('Rating submitted', 'success');
    await loadRequests();
  }

  document.addEventListener('click', handleActionClick);
  document.addEventListener('submit', handleRatingSubmit);
  loadRequests();
});
