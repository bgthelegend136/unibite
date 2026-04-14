-- UniBite schema
-- Run: mysql -u root -p unibite < db/schema.sql

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) UNIQUE NOT NULL,
  email         VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','admin') NOT NULL DEFAULT 'student',
  points        INT NOT NULL DEFAULT 5,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS allergens (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS listings (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  provider_id        INT NOT NULL,
  title              VARCHAR(200) NOT NULL,
  description        TEXT,
  photo_path         VARCHAR(255) NULL,
  portions_total     INT NOT NULL,
  portions_available INT NOT NULL,
  pickup_location    VARCHAR(255) NOT NULL,
  pickup_lat         DECIMAL(10,7) NOT NULL,
  pickup_lng         DECIMAL(10,7) NOT NULL,
  pickup_time        DATETIME NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_by_owner_at DATETIME NULL,
  FOREIGN KEY (provider_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS listing_allergens (
  listing_id  INT NOT NULL,
  allergen_id INT NOT NULL,
  PRIMARY KEY (listing_id, allergen_id),
  FOREIGN KEY (listing_id)  REFERENCES listings(id)  ON DELETE CASCADE,
  FOREIGN KEY (allergen_id) REFERENCES allergens(id)
);

-- requests.status lifecycle:
--   pending -> approved | rejected
--   approved -> picked_up | no_show
CREATE TABLE IF NOT EXISTS requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  listing_id   INT NOT NULL,
  consumer_id  INT NOT NULL,
  status       ENUM('pending','approved','rejected','picked_up','no_show') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at  DATETIME NULL,
  picked_up_at DATETIME NULL,
  no_show_at   DATETIME NULL,
  FOREIGN KEY (listing_id)  REFERENCES listings(id),
  FOREIGN KEY (consumer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS ratings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL UNIQUE,
  score      TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_score CHECK (score BETWEEN 1 AND 5),
  FOREIGN KEY (request_id) REFERENCES requests(id)
);

-- point_events: audit log + idempotency guard.
-- For non-initial events: UNIQUE(user_id, request_id, reason) prevents double-applying.
-- For initial (request_id IS NULL): enforced by app logic (WHERE NOT EXISTS) on insert.
CREATE TABLE IF NOT EXISTS point_events (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  request_id INT NULL,
  delta      INT NOT NULL,
  reason     ENUM('initial','request_approved','pickup_base','pickup_bonus','no_show','rating_timeout') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event (user_id, request_id, reason),
  FOREIGN KEY (user_id)    REFERENCES users(id),
  FOREIGN KEY (request_id) REFERENCES requests(id)
);
