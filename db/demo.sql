-- UniBite demo data
-- Optional: load AFTER schema.sql + seed.sql to reproduce the scenario
-- shown in docs/screenshots/. Re-running is safe — it wipes existing
-- user-generated rows first, then re-inserts the fixed scenario.
--
--   mysql -u root unibite < db/demo.sql
--
-- Provider: bob. Consumer: alice. Coordinates are around central Athens.

START TRANSACTION;

-- 1. Reset user-content tables (keeps users + allergens from seed.sql).
DELETE FROM ratings;
DELETE FROM point_events WHERE reason <> 'initial';
DELETE FROM requests;
DELETE FROM listing_allergens;
DELETE FROM listings;
UPDATE users SET points = 5 WHERE username IN ('alice','bob');

-- 2. Listings by bob.
INSERT INTO listings
  (provider_id, title, description, portions_total, portions_available,
   pickup_location, pickup_lat, pickup_lng, pickup_time)
SELECT id, 'Σπιτικά μακαρόνια με κιμά', 'Φρεσκο-μαγειρεμένη bolognese, μερίδες γενναίες.',
       6, 4, 'Κτίριο Πληροφορικής, εστία Α', 37.9715000, 23.7257000,
       DATE_ADD(NOW(), INTERVAL 3 HOUR)
FROM users WHERE username = 'bob';
SET @L1 = LAST_INSERT_ID();

INSERT INTO listings
  (provider_id, title, description, portions_total, portions_available,
   pickup_location, pickup_lat, pickup_lng, pickup_time)
SELECT id, 'Φακές με ρύζι', 'Vegan, χωρίς γλουτένη.',
       4, 4, 'Φιλοσοφική, δωμάτιο 12', 37.9685000, 23.7180000,
       DATE_ADD(NOW(), INTERVAL 5 HOUR)
FROM users WHERE username = 'bob';
SET @L2 = LAST_INSERT_ID();

INSERT INTO listings
  (provider_id, title, description, portions_total, portions_available,
   pickup_location, pickup_lat, pickup_lng, pickup_time)
SELECT id, 'Παστίτσιο', 'Έτοιμο σε ταψί, παίρνετε με σκεύος.',
       8, 0, 'Πολυτεχνείο, καντίνα', 37.9787000, 23.7833000,
       DATE_ADD(NOW(), INTERVAL 6 HOUR)
FROM users WHERE username = 'bob';
SET @L3 = LAST_INSERT_ID();

INSERT INTO listings
  (provider_id, title, description, portions_total, portions_available,
   pickup_location, pickup_lat, pickup_lng, pickup_time)
SELECT id, 'Χωριάτικο ψωμί + τυρί', 'Πρωινό για όποιον θέλει, μέχρι να τελειώσει.',
       5, 3, 'Νομική, είσοδος', 37.9765000, 23.7340000,
       DATE_ADD(NOW(), INTERVAL 1 HOUR)
FROM users WHERE username = 'bob';
SET @L4 = LAST_INSERT_ID();

-- 3. Allergens (look up by name so this works regardless of allergen IDs).
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L1, id FROM allergens WHERE name IN ('Gluten','Eggs','Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L3, id FROM allergens WHERE name IN ('Gluten','Eggs','Milk');
INSERT INTO listing_allergens (listing_id, allergen_id)
  SELECT @L4, id FROM allergens WHERE name IN ('Gluten','Milk');

-- 4. Capture user IDs.
SET @ALICE = (SELECT id FROM users WHERE username = 'alice');
SET @BOB   = (SELECT id FROM users WHERE username = 'bob');

-- 5. Requests.
-- alice → L1: pending (no point movement yet).
INSERT INTO requests (listing_id, consumer_id, status)
  VALUES (@L1, @ALICE, 'pending');

-- alice → L2: approved (consumer −1).
INSERT INTO requests (listing_id, consumer_id, status, approved_at)
  VALUES (@L2, @ALICE, 'approved', DATE_SUB(NOW(), INTERVAL 1 HOUR));
SET @R_APPROVED = LAST_INSERT_ID();
INSERT INTO point_events (user_id, request_id, delta, reason)
  VALUES (@ALICE, @R_APPROVED, -1, 'request_approved');

-- alice → L4: picked_up + rated 5/5 (consumer −1, provider +1 base, +1 bonus).
INSERT INTO requests (listing_id, consumer_id, status, approved_at, picked_up_at)
  VALUES (@L4, @ALICE, 'picked_up',
          DATE_SUB(NOW(), INTERVAL 4 HOUR),
          DATE_SUB(NOW(), INTERVAL 2 HOUR));
SET @R_PICKED = LAST_INSERT_ID();
INSERT INTO ratings (request_id, score) VALUES (@R_PICKED, 5);
INSERT INTO point_events (user_id, request_id, delta, reason) VALUES
  (@ALICE, @R_PICKED, -1, 'request_approved'),
  (@BOB,   @R_PICKED,  1, 'pickup_base'),
  (@BOB,   @R_PICKED,  1, 'pickup_bonus');

-- 6. Reconcile cached points.points to match point_events sum.
UPDATE users
  SET points = (SELECT COALESCE(SUM(delta), 0) FROM point_events WHERE user_id = users.id)
  WHERE username IN ('alice','bob');

COMMIT;

SELECT username, points FROM users WHERE username IN ('alice','bob');
