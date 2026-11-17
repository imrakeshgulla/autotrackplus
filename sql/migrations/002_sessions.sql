CREATE TABLE IF NOT EXISTS app_sessions (
  id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  data MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX ix_session_updated (updated_at)
) ENGINE=InnoDB;
