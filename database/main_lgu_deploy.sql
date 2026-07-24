-- Main LGU: SSO hub database — CyberPanel / production import
-- Create the database + DB user in CyberPanel's panel UI first, then import
-- this file into it (phpMyAdmin: select the DB, Import tab, choose this file).

CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS connected_systems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    admin_entry_path VARCHAR(255) NOT NULL,
    sso_consume_path VARCHAR(255) NOT NULL,
    stats_path VARCHAR(255) DEFAULT NULL,
    shared_secret VARCHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sso_launch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    super_admin_id INT NOT NULL,
    system_slug VARCHAR(30) NOT NULL,
    launched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (super_admin_id) REFERENCES super_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 5 connected systems with their real production subdomains.
-- shared_secret values here MUST match SSO_SHARED_SECRET / sso_config.php /
-- sso_credentials.php on each target system's own live deployment — these
-- are the same dev secrets used locally; rotate them for production and
-- update both sides together.
INSERT INTO connected_systems (slug, name, base_url, admin_entry_path, sso_consume_path, stats_path, shared_secret, is_active) VALUES
('roadmon', 'Road Monitoring (RGMAP)', 'https://rgmap.infragovservices.com', '/pages/admin/admin_dashboard.php', '/lgu_staff/sso_consume.php', '/lgu_staff/stats.php', '98e6d66778fb43ef152d69caf81b793ecc55368b9ee8357eac64246e73c7e64e', 1),
('ipms', 'IPMS', 'https://ipms.infragovservices.com', '/superadmin/dashboard.php', '/auth/sso_consume.php', '/stats.php', 'f56d2000a6be7cde816cb174274824462644e2255e9ee39b4946d166a933e490', 1),
('energy', 'Energy', 'https://energy.infragovservices.com', '/dashboard', '/sso/consume', '/api/stats', '400f214e72c54090af8b91ede0c17a23bad10f298b60cfea55d546cb8a44752a', 1),
('cprf', 'CPRF (Facilities Reservation)', 'https://cprf.infragovservices.com', '/dashboard', '/sso/consume', '/api/stats', '6724201881389f70d4d233dcd87caa15d507ebfd56f3fc73e0ad2b1c61e2d825', 1),
('cimm', 'CIMM', 'https://cimm.infragovservices.com', '/lgu-portal/public/admin/employee.php', '/lgu-portal/public/admin/sso_consume.php', '/lgu-portal/public/api/stats.php', '4b846cf9286c6a7d2dbd099b4033552ed162b08559f93624f69d48e1029092a6', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), base_url = VALUES(base_url), stats_path = VALUES(stats_path);
