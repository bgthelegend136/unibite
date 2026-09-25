-- UniBite demo data v2
-- Load AFTER schema.sql + seed.sql. Safe to re-run: wipes user-generated rows
-- (listings, requests, ratings, non-initial point events) and re-inserts a
-- scenario that covers every requirement of the assignment plus edge cases.
--
--   mysql -u root unibite < db/demo_v2.sql
--
-- Accounts (password test123 for all students, admin123 for admin):
--   bob    provider with most deliveries  -> Top Donor
--   maria  second provider, also consumes bob's food (same account, both roles)
--   alice  consumer with requests in every state
--   nikos  consumer left with 0 points (cannot request)
--
-- What to show (see comments next to each row for details):
--   Feed        active, last-portion, no-allergen, sold-out (grey), far away (~7.5 km)
--   Hidden      one listing older than 48 h, one soft-deleted by its owner
--   Requests    pending, approved, rejected, picked_up, no_show
--   Ratings     5, 5, 5, 4, 3 (no bonus), 2; tie on 5.0 broken by rating count
--   Penalty     maria has a pickup 49 h ago with no rating -> loses 1 point on login
--   Stats       one pickup 40 days ago: counts for Top Donor, not for "last month"

START TRANSACTION;

-- ------------------------------------------------------------------
-- 1. Reset user-generated content (keeps users, allergens, initial events).
-- ------------------------------------------------------------------
DELETE FROM ratings;
DELETE FROM point_events WHERE reason <> 'initial';
DELETE FROM requests;
DELETE FROM listing_allergens;
DELETE FROM listings;

-- ------------------------------------------------------------------
-- 2. Extra demo students (same password hash as alice/bob: test123).
-- ------------------------------------------------------------------
INSERT IGNORE INTO users (username, email, password_hash, role, points) VALUES
  ('maria', 'maria@student.local',
   '$2y$10$W6FEQ9X5DYSQddUtQAbIPuZJfqU7Ss.UrmaAq9dZRq3uCCccF6roG', 'student', 5),
  ('nikos', 'nikos@student.local',
   '$2y$10$W6FEQ9X5DYSQddUtQAbIPuZJfqU7Ss.UrmaAq9dZRq3uCCccF6roG', 'student', 5);

SET @ALICE = (SELECT id FROM users WHERE username = 'alice');
SET @BOB   = (SELECT id FROM users WHERE username = 'bob');
SET @MARIA = (SELECT id FROM users WHERE username = 'maria');
SET @NIKOS = (SELECT id FROM users WHERE username = 'nikos');

-- Initial +5 for every student. request_id is NULL, so UNIQUE cannot stop
-- duplicates; WHERE NOT EXISTS does (same approach as api/auth.php).
INSERT INTO point_events (user_id, request_id, delta, reason)
SELECT u.id, NULL, 5, 'initial'
FROM users u
WHERE u.username IN ('alice', 'bob', 'maria', 'nikos')
  AND NOT EXISTS (
    SELECT 1 FROM point_events pe
    WHERE pe.user_id = u.id AND pe.reason = 'initial' AND pe.request_id IS NULL
  );

-- ------------------------------------------------------------------
-- 3. Listings. Reference point for distances: centre of Athens (37.9715, 23.7257).
-- ------------------------------------------------------------------

-- L1 bob: active, several portions left, with photo and allergens.
INSERT INTO listings (provider_id, title, description, photo_path, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Σπιτικά μακαρόνια με κιμά', 'Φρεσκομαγειρεμένη μπολονέζ, μερίδες γενναίες.',
        'uploads/35b9cfdcadcf9f26.png', 6, 4, 'Κτίριο Πληροφορικής, εστία Α', 37.9715000, 23.7257000,
        DATE_ADD(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR));
SET @L1 = LAST_INSERT_ID();

-- L2 bob: exactly ONE portion and two pending requests -> approve both live,
-- the second fails with "No portions available". No allergens (optional field).
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Φακές με ρύζι', 'Vegan, χωρίς γλουτένη. Μένει μία μερίδα.',
        1, 1, 'Φιλοσοφική, δωμάτιο 12', 37.9685000, 23.7180000,
        DATE_ADD(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR));
SET @L2 = LAST_INSERT_ID();

-- L3 bob: sold out (0 portions) -> "inactive", greyed out, button "Sold out".
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Παστίτσιο', 'Έτοιμο σε ταψί, φέρτε δικό σας σκεύος.',
        2, 0, 'Πολυτεχνείο, καντίνα', 37.9787000, 23.7333000,
        DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 10 HOUR));
SET @L3 = LAST_INSERT_ID();

-- L4 maria: second provider (~4.5 km away), with photo. Best-rated meal (5.0 from 2 ratings).
INSERT INTO listings (provider_id, title, description, photo_path, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@MARIA, 'Γεμιστά', 'Ντομάτες και πιπεριές με ρύζι, στο φούρνο.',
        'uploads/679283be9bd46bb4.png', 5, 3, 'Πανεπιστημιούπολη Ζωγράφου, στάση λεωφορείου', 37.9680000, 23.7760000,
        DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR));
SET @L4 = LAST_INSERT_ID();

-- L5 bob: far away (Piraeus, ~7.5 km): hidden with 1/3/5 km, shown with 10 km.
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Σουβλάκια', 'Χοιρινά καλαμάκια με πίτα.',
        3, 1, 'Πανεπιστήμιο Πειραιά, είσοδος', 37.9420000, 23.6465000,
        DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR));
SET @L5 = LAST_INSERT_ID();

-- L6 bob: created 52 h ago -> "expired": NOT in the feed, but still in the DB
-- and counted in the admin statistics.
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Μουσακάς', 'Κλασικός μουσακάς με μπεσαμέλ.',
        3, 1, 'Νομική, είσοδος', 37.9765000, 23.7340000,
        DATE_SUB(NOW(), INTERVAL 50 HOUR), DATE_SUB(NOW(), INTERVAL 52 HOUR));
SET @L6 = LAST_INSERT_ID();

-- L7 maria: soft-deleted by its owner -> hidden, row kept in the DB.
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at, deleted_by_owner_at)
VALUES (@MARIA, 'Κέικ σοκολάτας', 'Το έσβησα γιατί τελικά το έφαγε η παρέα.',
        4, 4, 'Εστία Ζωγράφου, κοινόχρηστη κουζίνα', 37.9690000, 23.7740000,
        DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR));
SET @L7 = LAST_INSERT_ID();

-- L8 bob: 41 days old. Its pickup (40 days ago) counts for Top Donor
-- but NOT for "portions shared last month".
INSERT INTO listings (provider_id, title, description, portions_total, portions_available,
                      pickup_location, pickup_lat, pickup_lng, pickup_time, created_at)
VALUES (@BOB, 'Φασολάδα', 'Παραδοσιακή, με πολύ σέλινο.',
        2, 1, 'Κτίριο Πληροφορικής, εστία Α', 37.9715000, 23.7257000,
        DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 41 DAY));
SET @L8 = LAST_INSERT_ID();

-- ------------------------------------------------------------------
-- 4. Allergens (looked up by name, so allergen ids do not matter).
-- ------------------------------------------------------------------
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L1, id FROM allergens WHERE name IN ('Gluten', 'Eggs', 'Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L3, id FROM allergens WHERE name IN ('Gluten', 'Eggs', 'Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L4, id FROM allergens WHERE name IN ('Celery');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L5, id FROM allergens WHERE name IN ('Gluten');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L6, id FROM allergens WHERE name IN ('Gluten', 'Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L7, id FROM allergens WHERE name IN ('Gluten', 'Eggs', 'Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L8, id FROM allergens WHERE name IN ('Celery');

-- ------------------------------------------------------------------
-- 5. Requests, ratings and the point events each one produced.
--    Point rules: approve = consumer -1, pickup = provider +1 (pickup_base),
--    rating > 3 = provider +1 (pickup_bonus), no-show = consumer -1.
-- ------------------------------------------------------------------

-- R1 alice -> L1: PENDING. Approve or reject it live.
INSERT INTO requests (listing_id, consumer_id, status, created_at)
VALUES (@L1, @ALICE, 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- R2 alice -> L2 and R3 maria -> L2: both PENDING on the last portion.
INSERT INTO requests (listing_id, consumer_id, status, created_at)
VALUES (@L2, @ALICE, 'pending', DATE_SUB(NOW(), INTERVAL 40 MINUTE));
INSERT INTO requests (listing_id, consumer_id, status, created_at)
VALUES (@L2, @MARIA, 'pending', DATE_SUB(NOW(), INTERVAL 35 MINUTE));

-- R4 alice -> L3: picked up, rated 5 (base + bonus for bob).
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L3, @ALICE, 'picked_up', DATE_SUB(NOW(), INTERVAL 570 MINUTE),
        DATE_SUB(NOW(), INTERVAL 9 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR));
SET @R4 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R4, 5);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@ALICE, @R4, -1, 'request_approved'),
  (@BOB,   @R4,  1, 'pickup_base'),
  (@BOB,   @R4,  1, 'pickup_bonus');

-- R5 nikos -> L3: picked up, rated 4 (base + bonus). L3 is now sold out.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L3, @NIKOS, 'picked_up', DATE_SUB(NOW(), INTERVAL 9 HOUR),
        DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 7 HOUR));
SET @R5 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R5, 4);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@NIKOS, @R5, -1, 'request_approved'),
  (@BOB,   @R5,  1, 'pickup_base'),
  (@BOB,   @R5,  1, 'pickup_bonus');

-- R6 maria -> L1: picked up, rated 2 (base only). maria consumes bob's food.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L1, @MARIA, 'picked_up', DATE_SUB(NOW(), INTERVAL 170 MINUTE),
        DATE_SUB(NOW(), INTERVAL 150 MINUTE), DATE_SUB(NOW(), INTERVAL 2 HOUR));
SET @R6 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R6, 2);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@MARIA, @R6, -1, 'request_approved'),
  (@BOB,   @R6,  1, 'pickup_base');

-- R7 nikos -> L1: approved, then NO-SHOW (nikos -1 twice).
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, no_show_at)
VALUES (@L1, @NIKOS, 'no_show', DATE_SUB(NOW(), INTERVAL 160 MINUTE),
        DATE_SUB(NOW(), INTERVAL 150 MINUTE), DATE_SUB(NOW(), INTERVAL 1 HOUR));
SET @R7 = LAST_INSERT_ID();
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@NIKOS, @R7, -1, 'request_approved'),
  (@NIKOS, @R7, -1, 'no_show');

-- R8 nikos -> L4 (maria's): picked up, rated 5.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L4, @NIKOS, 'picked_up', DATE_SUB(NOW(), INTERVAL 110 MINUTE),
        DATE_SUB(NOW(), INTERVAL 90 MINUTE), DATE_SUB(NOW(), INTERVAL 1 HOUR));
SET @R8 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R8, 5);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@NIKOS, @R8, -1, 'request_approved'),
  (@MARIA, @R8,  1, 'pickup_base'),
  (@MARIA, @R8,  1, 'pickup_bonus');

-- R9 bob -> L4 (maria's): picked up, rated 5. bob is also a consumer.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L4, @BOB, 'picked_up', DATE_SUB(NOW(), INTERVAL 115 MINUTE),
        DATE_SUB(NOW(), INTERVAL 108 MINUTE), DATE_SUB(NOW(), INTERVAL 90 MINUTE));
SET @R9 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R9, 5);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@BOB,   @R9, -1, 'request_approved'),
  (@MARIA, @R9,  1, 'pickup_base'),
  (@MARIA, @R9,  1, 'pickup_bonus');

-- R10 alice -> L5: APPROVED. Mark it "Picked up" or "No-show" live.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at)
VALUES (@L5, @ALICE, 'approved', DATE_SUB(NOW(), INTERVAL 50 MINUTE), DATE_SUB(NOW(), INTERVAL 40 MINUTE));
SET @R10 = LAST_INSERT_ID();
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@ALICE, @R10, -1, 'request_approved');

-- R11 maria -> L5: picked up 20 minutes ago, NOT rated yet -> the star form
-- shows in maria's "My requests". Rate it live.
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L5, @MARIA, 'picked_up', DATE_SUB(NOW(), INTERVAL 55 MINUTE),
        DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE));
SET @R11 = LAST_INSERT_ID();
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@MARIA, @R11, -1, 'request_approved'),
  (@BOB,   @R11,  1, 'pickup_base');

-- R12 nikos -> L6 (expired listing): picked up 50 h ago, rated 5.
-- L6 has 5.0 from ONE rating, so it ranks below L4 (5.0 from two).
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L6, @NIKOS, 'picked_up', DATE_SUB(NOW(), INTERVAL 3090 MINUTE),
        DATE_SUB(NOW(), INTERVAL 51 HOUR), DATE_SUB(NOW(), INTERVAL 50 HOUR));
SET @R12 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R12, 5);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@NIKOS, @R12, -1, 'request_approved'),
  (@BOB,   @R12,  1, 'pickup_base'),
  (@BOB,   @R12,  1, 'pickup_bonus');

-- R13 maria -> L6: picked up 49 h ago and NEVER rated.
-- When maria logs in (or opens "My requests"), she loses 1 point (rating_timeout).
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L6, @MARIA, 'picked_up', DATE_SUB(NOW(), INTERVAL 3100 MINUTE),
        DATE_SUB(NOW(), INTERVAL 51 HOUR), DATE_SUB(NOW(), INTERVAL 49 HOUR));
SET @R13 = LAST_INSERT_ID();
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@MARIA, @R13, -1, 'request_approved'),
  (@BOB,   @R13,  1, 'pickup_base');

-- R14 alice -> L8: picked up 40 days ago, rated 3 -> no bonus (3 does not "exceed" 3).
INSERT INTO requests (listing_id, consumer_id, status, created_at, approved_at, picked_up_at)
VALUES (@L8, @ALICE, 'picked_up', DATE_SUB(NOW(), INTERVAL 983 HOUR),
        DATE_SUB(NOW(), INTERVAL 962 HOUR), DATE_SUB(NOW(), INTERVAL 40 DAY));
SET @R14 = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R14, 3);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@ALICE, @R14, -1, 'request_approved'),
  (@BOB,   @R14,  1, 'pickup_base');

-- R15 alice -> L7 (later deleted by maria): REJECTED. No points move.
INSERT INTO requests (listing_id, consumer_id, status, created_at)
VALUES (@L7, @ALICE, 'rejected', DATE_SUB(NOW(), INTERVAL 5 HOUR));

-- ------------------------------------------------------------------
-- 6. Cached users.points = sum of the point history (they must always agree).
--    Expected: bob 14, maria 6 (5 after her penalty), alice 2, nikos 0.
-- ------------------------------------------------------------------
UPDATE users
SET points = (SELECT COALESCE(SUM(pe.delta), 0) FROM point_events pe WHERE pe.user_id = users.id)
WHERE username IN ('alice', 'bob', 'maria', 'nikos');

COMMIT;

SELECT username, points FROM users WHERE role = 'student' ORDER BY points DESC;
