-- One-time fix for a connected_systems table that got seeded with the local
-- XAMPP base_url values (http://localhost/...) instead of the real
-- *.infragovservices.com subdomains. Safe to run repeatedly.
UPDATE connected_systems SET base_url = 'https://rgmap.infragovservices.com' WHERE slug = 'roadmon';
UPDATE connected_systems SET base_url = 'https://ipms.infragovservices.com' WHERE slug = 'ipms';
UPDATE connected_systems SET base_url = 'https://energy.infragovservices.com' WHERE slug = 'energy';
UPDATE connected_systems SET base_url = 'https://cprf.infragovservices.com' WHERE slug = 'cprf';
UPDATE connected_systems SET base_url = 'https://cimm.infragovservices.com' WHERE slug = 'cimm';

SELECT slug, name, base_url FROM connected_systems;
