-- One-time migration for databases created before the public citizen
-- dashboard's department cards read their descriptions from this table
-- instead of being hand-written HTML.
ALTER TABLE connected_systems ADD COLUMN IF NOT EXISTS public_tagline VARCHAR(255) DEFAULT NULL;
