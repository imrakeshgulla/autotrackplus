-- Additive migration. Run through bin/migrate.php (resumable and repeat-safe).
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN password_changed_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN password_setup_authorized_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS password_tokens (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  purpose ENUM('password') NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_password_token_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS auth_rate_limits (
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX ix_rate_expiry (expires_at)
) ENGINE=InnoDB;
