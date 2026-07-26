-- One-time migration for databases created before multi-admin invites
-- existed.
ALTER TABLE super_admins
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS invite_token VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS invite_token_expires DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS invited_by INT DEFAULT NULL;
