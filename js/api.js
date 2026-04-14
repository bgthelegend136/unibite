/**
 * Thin fetch wrapper.
 * All API calls are POST with FormData or URLSearchParams.
 * Returns parsed { ok, data, error }.
 */

async function apiPost(endpoint, params = {}) {
  const body = new URLSearchParams(params);
  const res = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body,
  });
  return res.json();
}

/**
 * Show an inline message in a target element.
 * type: 'error' | 'success'
 */
function showMsg(el, msg, type = 'error') {
  el.textContent = msg;
  el.className = 'msg msg--' + type;
  el.hidden = false;
}

function clearMsg(el) {
  el.textContent = '';
  el.hidden = true;
}
