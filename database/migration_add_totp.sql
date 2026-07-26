-- One-time migration for databases created before 2FA (TOTP) existed.
ALTER TABLE super_admins
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(32) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS totp_confirmed_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS super_admin_recovery_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    super_admin_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (super_admin_id) REFERENCES super_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
