-- One-time migration for databases created before secret age tracking
-- existed. Existing rows are backfilled to "now" as the tracking baseline
-- since the real original rotation date isn't known.
ALTER TABLE connected_systems ADD COLUMN IF NOT EXISTS secret_rotated_at TIMESTAMP NULL DEFAULT NULL;
UPDATE connected_systems SET secret_rotated_at = NOW() WHERE secret_rotated_at IS NULL;
