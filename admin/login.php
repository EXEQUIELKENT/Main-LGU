<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (is_super_admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['super_admin_id'] = $admin['id'];
            $_SESSION['super_admin_name'] = $admin['full_name'];
            $_SESSION['super_admin_email'] = $admin['email'];

            $update = mainLguDb()->prepare('UPDATE super_admins SET last_login = NOW() WHERE id = ?');
            $update->execute([$admin['id']]);

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Login — InfraGovServices</title>
<style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: #050a19;
        background-image: radial-gradient(circle at 20% 20%, #101a3a 0%, #050a19 60%);
    }
    .card {
        width: 100%; max-width: 380px; background: #0d1530; border: 1px solid #1c2748;
        border-radius: 14px; padding: 36px 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    h1 { color: #fff; font-size: 1.3rem; margin: 0 0 4px; }
    p.sub { color: #8b95b8; margin: 0 0 24px; font-size: 0.9rem; }
    label { display: block; color: #b6bedc; font-size: 0.85rem; margin-bottom: 6px; }
    input {
        width: 100%; padding: 11px 12px; margin-bottom: 16px; border-radius: 8px;
        border: 1px solid #29335a; background: #0a1226; color: #fff; font-size: 0.95rem;
    }
    input:focus { outline: none; border-color: #4f6ef7; }
    button {
        width: 100%; padding: 12px; border: none; border-radius: 8px; background: #4f6ef7;
        color: #fff; font-weight: 600; font-size: 0.95rem; cursor: pointer;
    }
    button:hover { background: #3f5adf; }
    .error { background: #3a1220; color: #ff8fa3; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem; }
</style>
</head>
<body>
    <form class="card" method="post" autocomplete="off">
        <h1>Super Admin</h1>
        <p class="sub">InfraGovServices SSO Hub</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <label for="username">Username or email</label>
        <input type="text" id="username" name="username" required autofocus>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>
