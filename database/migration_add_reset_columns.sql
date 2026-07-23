-- One-time migration for databases created before reset_token / failed_login_attempts existed.
-- Safe to run even if some/all columns already exist.
ALTER TABLE super_admins
  ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS failed_login_attempts INT NOT NULL DEFAULT 0;
