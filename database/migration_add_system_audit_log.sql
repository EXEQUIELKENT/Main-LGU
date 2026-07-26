-- One-time migration for databases created before the Connected Systems
-- audit log existed.
CREATE TABLE IF NOT EXISTS system_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    super_admin_id INT NOT NULL,
    action VARCHAR(30) NOT NULL,
    system_slug VARCHAR(30) NOT NULL,
    system_name VARCHAR(100) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (super_admin_id) REFERENCES super_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
