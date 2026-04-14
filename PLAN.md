# UniBite — Implementation Phases

## Phase 1: Scaffold + DB + Auth ✅
`db/schema.sql`, `db/seed.sql`, `includes/`, `api/auth.php`, `index.php`, `register.php`, `css/style.css`, `js/api.js`, `js/app.js`

## Phase 2: Listing CRUD
`api/listings.php` (create/update/delete/list/get), `create-listing.php`, `my-listings.php`
- Allergen checkboxes (EU-14 from DB)
- Optional photo upload to `uploads/`
- Leaflet location picker (lat/lng required)
- 48h expiry filter in all SQL queries

## Phase 3: Consumer Feed
`feed.php`, `js/feed.js`, `js/map.js`, `listing.php`
- Card grid + Leaflet map with markers
- Active (normal) vs Inactive (greyed) visual styles
- Distance filter/sort via browser geolocation + Haversine

## Phase 4: Request System
`api/requests.php` (send/list/approve/reject), `requests.php`, `js/requests.js`, `my-requests.php`
- Guard: ≥1 point, no duplicate active request for same listing
- Approve: portions_available--, consumer points--
- Reject: no point change

## Phase 5: Pickup + No-Show
Extend `api/requests.php` (pickup/noshow actions)
- Pickup: provider points++, record `picked_up_at`
- No-show: consumer points--, record `no_show_at`
- `profile.php` (points display)

## Phase 6: Ratings
`api/ratings.php`, star widget in `my-requests.php`, `js/ratings.js`
- Provider bonus +1 if score > 3
- Lazy 48h timeout penalty via `apply_rating_timeouts()` in `includes/helpers.php`

## Phase 7: Admin Dashboard
`api/stats.php`, `admin.php`
- Portions shared last month
- Top donor (most pickups)
- Best-rated meals (avg score)

## Phase 8: Polish
Responsive CSS, form validation, error toasts, demo seed data for screenshots

---

## Schema (7 tables)
`users`, `allergens`, `listings`, `listing_allergens`, `requests`, `ratings`, `point_events`
See `db/schema.sql`.

## API Endpoints
All POST + `action` param:
- `api/auth.php`: login, register, logout
- `api/listings.php`: list, get, create, update, delete
- `api/requests.php`: send, list, approve, reject, pickup, noshow
- `api/ratings.php`: submit
- `api/stats.php`: dashboard
