-- One-time migration for databases created before stats_path existed.
ALTER TABLE connected_systems ADD COLUMN IF NOT EXISTS stats_path VARCHAR(255) DEFAULT NULL;

UPDATE connected_systems SET stats_path = '/lgu_staff/stats.php' WHERE slug = 'roadmon';
UPDATE connected_systems SET stats_path = '/stats.php' WHERE slug = 'ipms';
UPDATE connected_systems SET stats_path = '/api/stats' WHERE slug = 'energy';
UPDATE connected_systems SET stats_path = '/api/stats' WHERE slug = 'cprf';
UPDATE connected_systems SET stats_path = '/lgu-portal/public/api/stats.php' WHERE slug = 'cimm';
