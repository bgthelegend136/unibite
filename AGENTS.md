# UniBite — Agent Rules

## Tech Stack
- PHP multi-page app + vanilla JavaScript, MySQL
- No frameworks, no SPA, no npm

## Architecture
- PHP pages do server-side rendering
- `api/` endpoints handle JSON (all POST + `action` param)
- All business logic on the server; JS handles fetch + DOM only
- Single CSS file (`css/style.css`), mobile-first responsive

## Roles
- `student`: can create listings AND request/rate portions
- `admin`: stats/oversight only
- Access control: check `$_SESSION['user']` + ownership (provider_id == user_id), not role enum

## Point System
| Event | Who | Delta |
|-------|-----|-------|
| Registration | new user | +5 |
| Request approved | consumer | −1 |
| Pickup confirmed | provider | +1 |
| Rating > 3 | provider | +1 bonus |
| No-show | consumer | −1 |
| Rating timeout (48 h after pickup, no rating) | consumer | −1 |

- `users.points` = cached balance; `point_events` = audit log
- All point changes: INSERT into `point_events` + UPDATE `users.points` in a transaction
- Idempotency: UNIQUE(user_id, request_id, reason) for non-initial events
- Initial event (request_id IS NULL): guarded with `WHERE NOT EXISTS` in app logic

## Listing Lifecycle (derived — no status column)
- **Active**: `created_at + 48h > NOW()` AND `portions_available > 0` → shown in feed
- **Inactive**: `created_at + 48h > NOW()` AND `portions_available = 0` → shown greyed
- **Expired**: `created_at + 48h ≤ NOW()` → hidden from feed, kept for stats

## Duplicate Request Prevention
Check in app logic before INSERT:
```sql
SELECT id FROM requests
WHERE listing_id = ? AND consumer_id = ? AND status IN ('pending','approved')
```
Return error if any row found.

## API Convention
- All: `POST /api/*.php` with `action` param
- Response: `{ "ok": bool, "data": ..., "error": "..." }`
- Auth via PHP sessions (`session_start()` in `includes/auth.php`)

## Code Style
- PDO prepared statements everywhere
- Validate on server; client-side validation is UX enhancement only
- Keep files small and focused; no over-abstraction
- `pickup_lat` and `pickup_lng` are NOT NULL — required for map
