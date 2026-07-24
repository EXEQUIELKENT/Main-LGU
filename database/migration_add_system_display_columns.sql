-- One-time migration for databases created before icon/theme_color/short_tag existed.
ALTER TABLE connected_systems
  ADD COLUMN IF NOT EXISTS icon VARCHAR(50) NOT NULL DEFAULT 'fa-server',
  ADD COLUMN IF NOT EXISTS theme_color VARCHAR(20) NOT NULL DEFAULT 'blue',
  ADD COLUMN IF NOT EXISTS short_tag VARCHAR(20) NOT NULL DEFAULT '';

UPDATE connected_systems SET icon = 'fa-road', theme_color = 'orange', short_tag = 'RGMAP' WHERE slug = 'roadmon';
UPDATE connected_systems SET icon = 'fa-hard-hat', theme_color = 'blue', short_tag = 'IPMS' WHERE slug = 'ipms';
UPDATE connected_systems SET icon = 'fa-leaf', theme_color = 'teal', short_tag = 'ECM' WHERE slug = 'energy';
UPDATE connected_systems SET icon = 'fa-calendar-check', theme_color = 'purple', short_tag = 'CPRF' WHERE slug = 'cprf';
UPDATE connected_systems SET icon = 'fa-tools', theme_color = 'rose', short_tag = 'CIMM' WHERE slug = 'cimm';
