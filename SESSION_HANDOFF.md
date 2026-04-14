# Session Handoff

## Phase 1 - COMPLETE

### Files
| File | Purpose |
|------|---------|
| db/schema.sql | 7 tables: users, allergens, listings, listing_allergens, requests, ratings, point_events |
| db/seed.sql | EU-14 allergens, admin + 2 test students, initial point_events |
| includes/db.php | PDO singleton - edit DB_PASS if needed |
| includes/helpers.php | json_response(), apply_rating_timeouts() |
| includes/auth.php | current_user(), require_login(), require_login_api(), require_role(), refresh_session_user() |
| api/auth.php | POST: login, register, logout |
| css/style.css | Mobile-first base styles |
| js/api.js | apiPost(), showMsg(), clearMsg() |
| js/app.js | Nav burger, logout button |
| index.php | Login page to home.php on success |
| register.php | Register page to home.php on success |
| home.php | Landing page with username and points |

### Setup
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS unibite CHARACTER SET utf8mb4;"
mysql -u root -p unibite < db/schema.sql
mysql -u root -p unibite < db/seed.sql
# Edit includes/db.php if DB_PASS is not empty
C:\Users\bgthe\Pictures\xamp\php\php.exe -S localhost:8000
```

### Seed credentials
| User | Email | Password |
|------|-------|----------|
| admin | admin@unibite.local | admin123 |
| alice | alice@student.local | test123 |
| bob | bob@student.local | test123 |

### Known constraints applied
- `role` = student | admin
- `pickup_lat` / `pickup_lng` NOT NULL
- `point_events` idempotency: UNIQUE(user_id, request_id, reason) for non-initial; initial guarded by WHERE NOT EXISTS
- Duplicate active request prevention: app-logic query (Phase 4)

---

## Phase 2 - COMPLETE AND VERIFIED

### Files completed
| File | What it does |
|------|-------------|
| api/listings.php | POST actions: list, get, create, update, delete |
| create-listing.php | Form: title, description, photo (optional), portions, allergens, pickup location + lat/lng, pickup time |
| js/listing.js | Form submit via fetch, allergen checkboxes, Leaflet location picker |
| my-listings.php | Student's own listings with edit/delete buttons and status |

### Files updated
| File | Change |
|------|--------|
| css/style.css | Add listing card styles |
| home.php | Add nav links to My Listings and Post Food |

### Key rules enforced in api/listings.php
- Auth: `require_login_api()` for create/update/delete
- Ownership: check `provider_id = $_SESSION['user']['id']` before update/delete
- 48h expiry filter in `list` and `get`
- Listing state is derived, with no status column
- `pickup_lat` and `pickup_lng` required
- Photo upload: move to `uploads/`, store relative path; skip if no file

### Verification
- PHP syntax checks passed for the Phase 2 PHP files
- Localhost HTTP smoke tests confirmed update works without changing photo, with text-only changes, with allergen changes, and with a new photo
- `api/listings.php` logs caught exceptions and returns a useful localhost error message

---

## Phase 3 - COMPLETE AND VERIFIED

### Files implemented
| File | Purpose |
|------|---------|
| feed.php | Public feed page with list/map toggle and distance controls |
| listing.php | Public listing detail page |
| js/feed.js | Feed rendering, distance filtering/sorting, detail loading |
| js/map.js | Leaflet helpers and Haversine distance helpers |

### UI updates
| File | Change |
|------|--------|
| css/style.css | Feed/listing detail layout and inactive/active card styling |

### Verification
- PHP syntax checks passed for `feed.php`, `listing.php`, and `api/listings.php`
- Feed/list/detail logic stays server-driven for non-expired listing visibility
- Browser smoke verification passed for the new feed and detail pages

---

## Phase 4 - COMPLETE AND VERIFIED

### Files implemented
| File | Purpose |
|------|---------|
| api/requests.php | POST `send`, `list`, `approve`, `reject` actions |
| requests.php | Provider-side incoming requests page |
| my-requests.php | Consumer-side outgoing requests page |
| js/requests.js | Shared request list + approve/reject UI |

### UI updates
| File | Change |
|------|--------|
| feed.php | Request buttons rendered in feed cards |
| listing.php | Request button rendered on listing detail page |
| js/feed.js | Request button click handling and success/error feedback |
| css/style.css | Request page/card styling |

### Verification
- Request creation is server-side validated
- Student must have at least 1 point
- Students cannot request their own listings
- Duplicate active requests are blocked
- Provider request lists show only the current student's listings
- Outgoing request lists show the current student's requests with status
- Approve is transactional, owner-checked, and fails when portions are 0
- Approve now deducts 1 consumer point and inserts `point_events.reason = 'request_approved'`
- Reject is owner-checked and pending-only
- Localhost HTTP smoke tests passed for incoming list, outgoing list, approve, reject, inactive listing transition, and consumer point deduction on approve

---

## Phase 5 - COMPLETE AND VERIFIED

### Files updated
| File | Change |
|------|--------|
| api/requests.php | Added POST `pickup` and `noshow` actions with transactional point events |
| requests.php | Updated provider-side copy for pickup / no-show flow |
| js/requests.js | Added approved-only pickup / no-show buttons and status messaging |
| css/style.css | Added request status styling for `picked_up` and `no_show` |

### Verification
- Only the listing owner can mark pickup or no-show
- Only approved requests can become `picked_up` or `no_show`
- Pickup sets `picked_up_at`, gives the provider +1 point, and inserts `point_events.reason = 'pickup_base'`
- No-show sets `no_show_at`, deducts 1 point from the consumer, and inserts `point_events.reason = 'no_show'`
- Repeated pickup / no-show calls do not double-apply points or duplicate point events
- Localhost HTTP smoke tests passed for pickup, no-show, approved-only guards, provider reward, consumer penalty, timestamps, and point-event idempotency

---

## Phase 6 - COMPLETE AND VERIFIED

### Files implemented
| File | Purpose |
|------|---------|
| api/ratings.php | POST `submit` action for consumer rating submission |

### Files updated
| File | Change |
|------|--------|
| api/requests.php | Request list responses now include rating data |
| my-requests.php | Applies lazy rating-timeout penalties on page load and refreshes points |
| js/requests.js | Outgoing request cards render rating submission UI for `picked_up` requests only |
| css/style.css | Added rating form/display styling |

### Verification
- Only the consumer for a request can submit a rating
- Only `picked_up` requests can be rated
- Ratings are limited to one per request with scores from 1 to 5
- High ratings (`score > 3`) give the provider +1 point and insert `point_events.reason = 'pickup_bonus'`
- Duplicate provider bonus application is prevented
- Lazy rating-timeout penalties apply for `picked_up` requests older than 48 hours with no rating and no existing timeout event
- Rating-timeout penalties deduct 1 point from the consumer and insert `point_events.reason = 'rating_timeout'`
- Repeated `my-requests.php` loads do not duplicate timeout penalties
- Localhost HTTP smoke tests passed for rating submission, duplicate-rating rejection, provider bonus, timeout penalty, and timeout idempotency

---

## Phase 7 - COMPLETE AND VERIFIED

### Files implemented
| File | Purpose |
|------|---------|
| api/stats.php | Admin-only dashboard stats endpoint |
| admin.php | Admin-only dashboard page |

### Files updated
| File | Change |
|------|--------|
| css/style.css | Added admin dashboard layout and stat styling |

### Verification
- `admin.php` is admin-only
- `api/stats.php` is admin-only
- Dashboard returns total picked-up portions in the last month
- Dashboard returns the top donor by successful pickups
- Dashboard returns highest-rated meals ranked by average rating and only includes rated listings
- Localhost HTTP checks passed for admin access, student denial, and dashboard JSON shape

---

## Final Polish - COMPLETE

### Files updated
| File | Change |
|------|--------|
| includes/helpers.php | Added shared singular/plural points label formatter |
| js/app.js | Added shared nav points badge updater for API-driven UI refresh |
| api/requests.php | Pickup action now refreshes the provider session points and returns the updated balance |
| js/requests.js | Pickup success now refreshes the visible nav points badge without a page reload |
| home.php, feed.php, listing.php, create-listing.php, my-listings.php, requests.php, my-requests.php, admin.php | Points labels now use `1 pt` / `N pts` consistently |

### Verification
- Singular/plural points wording is consistent across the main pages
- Pickup updates the provider nav points badge immediately after the API action
- No new feature work or architectural changes were introduced
