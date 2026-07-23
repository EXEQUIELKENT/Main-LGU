<?php
/**
 * One-time CLI/browser script to create the first super admin account.
 * Delete or lock this file down after use, same pattern as ipms_lgu/setup_admin.php.
 */
require_once __DIR__ . '/includes/db.php';

$username = 'superadmin';
$email = 'bartolomeexequielkent2003@gmail.com';
$password = 'Kent_136647090132';
$fullName = 'Bartolome';

$pdo = mainLguDb();
$existing = $pdo->prepare('SELECT id FROM super_admins WHERE username = ? OR email = ?');
$existing->execute([$username, $email]);

if ($existing->fetch()) {
    echo "Super admin already exists.\n";
    exit;
}

$stmt = $pdo->prepare('INSERT INTO super_admins (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)');
$stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName]);

echo "Super admin created.\n";
echo "Username: {$username}\n";
echo "Password: {$password}\n";
echo "Change this password after first login.\n";
