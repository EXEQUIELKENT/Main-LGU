-- Main LGU: SSO hub database
CREATE DATABASE IF NOT EXISTS infr_lgu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE infr_lgu;

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
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    icon VARCHAR(50) NOT NULL DEFAULT 'fa-server',
    theme_color VARCHAR(20) NOT NULL DEFAULT 'blue',
    short_tag VARCHAR(20) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sso_launch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    super_admin_id INT NOT NULL,
    system_slug VARCHAR(30) NOT NULL,
    launched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    FOREIGN KEY (super_admin_id) REFERENCES super_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 5 connected systems (local XAMPP URLs; update base_url when deployed to *.infragovservices.com)
INSERT INTO connected_systems (slug, name, base_url, admin_entry_path, sso_consume_path, stats_path, shared_secret, is_active, icon, theme_color, short_tag) VALUES
('roadmon', 'Road Monitoring (RGMAP)', 'http://localhost/lg-road-monitoring', '/pages/admin/admin_dashboard.php', '/lgu_staff/sso_consume.php', '/lgu_staff/stats.php', '98e6d66778fb43ef152d69caf81b793ecc55368b9ee8357eac64246e73c7e64e', 1, 'fa-road', 'orange', 'RGMAP'),
('ipms', 'IPMS', 'http://localhost/ipms_lgu', '/superadmin/dashboard.php', '/auth/sso_consume.php', '/stats.php', 'f56d2000a6be7cde816cb174274824462644e2255e9ee39b4946d166a933e490', 1, 'fa-hard-hat', 'blue', 'IPMS'),
('energy', 'Energy', 'http://localhost/Lgu1-energy', '/dashboard', '/sso/consume', '/api/stats', '400f214e72c54090af8b91ede0c17a23bad10f298b60cfea55d546cb8a44752a', 1, 'fa-leaf', 'teal', 'ECM'),
('cprf', 'CPRF (Facilities Reservation)', 'http://localhost/facilities-reservation-system1', '/dashboard', '/sso/consume', '/api/stats', '6724201881389f70d4d233dcd87caa15d507ebfd56f3fc73e0ad2b1c61e2d825', 1, 'fa-calendar-check', 'purple', 'CPRF'),
('cimm', 'CIMM', 'http://localhost/LGU', '/lgu-portal/public/admin/employee.php', '/lgu-portal/public/admin/sso_consume.php', '/lgu-portal/public/api/stats.php', '4b846cf9286c6a7d2dbd099b4033552ed162b08559f93624f69d48e1029092a6', 1, 'fa-tools', 'rose', 'CIMM'),
('urbanplanning', 'Urban Planning & Development', 'http://localhost/lgu-urban-planning-capstone/lgu-urban-planning', '/admin/index.php', '/sso_consume.php', NULL, 'e935e6cd6041b49b2c8aaa3a04fd7370e2266974799c0a8a250845f9e5d3ec97', 1, 'fa-map-location-dot', 'indigo', 'UPAD')
ON DUPLICATE KEY UPDATE name = VALUES(name), stats_path = VALUES(stats_path), icon = VALUES(icon), theme_color = VALUES(theme_color), short_tag = VALUES(short_tag);
