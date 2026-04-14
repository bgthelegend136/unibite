-- UniBite seed data
-- Run after schema.sql

-- EU-14 allergens
INSERT IGNORE INTO allergens (name) VALUES
  ('Gluten'),
  ('Crustaceans'),
  ('Eggs'),
  ('Fish'),
  ('Peanuts'),
  ('Soybeans'),
  ('Milk'),
  ('Nuts'),
  ('Celery'),
  ('Mustard'),
  ('Sesame'),
  ('Sulphur dioxide / Sulphites'),
  ('Lupin'),
  ('Molluscs');

-- Admin user  (password: admin123)
INSERT IGNORE INTO users (username, email, password_hash, role, points)
VALUES ('admin', 'admin@unibite.local',
        '$2y$10$tF2GPHzECi2U9cuwg75AIuSA0zinKrhFdpgXycVLHfSMXe6yVj.NG', 'admin', 0);

-- Test students  (password: test123)
INSERT IGNORE INTO users (username, email, password_hash, role, points)
VALUES
  ('alice', 'alice@student.local',
   '$2y$10$W6FEQ9X5DYSQddUtQAbIPuZJfqU7Ss.UrmaAq9dZRq3uCCccF6roG', 'student', 5),
  ('bob',   'bob@student.local',
   '$2y$10$W6FEQ9X5DYSQddUtQAbIPuZJfqU7Ss.UrmaAq9dZRq3uCCccF6roG', 'student', 5);

-- Initial point_events for test students
INSERT IGNORE INTO point_events (user_id, request_id, delta, reason)
  SELECT id, NULL, 5, 'initial' FROM users WHERE username = 'alice';
INSERT IGNORE INTO point_events (user_id, request_id, delta, reason)
  SELECT id, NULL, 5, 'initial' FROM users WHERE username = 'bob';
