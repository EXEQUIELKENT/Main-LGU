-- One-time migration for databases created before locked_until existed.
ALTER TABLE super_admins ADD COLUMN IF NOT EXISTS locked_until DATETIME DEFAULT NULL;
