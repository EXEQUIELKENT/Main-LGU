<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (is_super_admin_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
$requireOtp = !$isLocalhost;

function setNotification(string $type, string $message): void
{
    $_SESSION['notification'] = ['type' => $type, 'message' => $message];
}

function renderNotification(): void
{
    if (empty($_SESSION['notification'])) {
        return;
    }
    $type = $_SESSION['notification']['type'];
    $message = htmlspecialchars($_SESSION['notification']['message']);
    $icon = ['success' => '✔️', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'][$type] ?? 'ℹ️';
    echo "<div class='notif-popup notif-{$type}' id='notifPopup'>
            <span class='notif-icon'>{$icon}</span>
            <span class='notif-message'>{$message}</span>
            <button type='button' class='notif-close' onclick=\"closeNotif()\">&times;</button>
          </div>";
    unset($_SESSION['notification']);
}

// Only on a plain GET landing — the login form has no action attribute, so
// submitting it from login.php?timeout=1 would otherwise resubmit that same
// query string, re-arming this notification for the OTP screen that follows.
if (isset($_GET['timeout']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    setNotification('info', 'You were signed out after 2 minutes of inactivity.');
    header('Location: login.php');
    exit;
}

// ── Consume a password-reset link ───────────────────────────────────────
if (isset($_GET['reset_token'])) {
    $token = (string) $_GET['reset_token'];
    $stmt = mainLguDb()->prepare('SELECT id, email FROM super_admins WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if ($admin) {
        $_SESSION['show_reset_modal'] = true;
        $_SESSION['reset_admin_id'] = $admin['id'];
        $_SESSION['reset_token'] = $token;
    } else {
        setNotification('error', 'That password reset link is invalid or has expired.');
    }

    header('Location: login.php');
    exit;
}

// ── POST actions ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            setNotification('error', 'Enter your email and password.');
        } else {
            $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                mainLguDb()->prepare('UPDATE super_admins SET failed_login_attempts = 0 WHERE id = ?')->execute([$admin['id']]);

                if (!$requireOtp) {
                    session_regenerate_id(true);
                    $_SESSION['super_admin_id'] = $admin['id'];
                    $_SESSION['super_admin_name'] = $admin['full_name'];
                    $_SESSION['super_admin_email'] = $admin['email'];
                    mainLguDb()->prepare('UPDATE super_admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
                    header('Location: dashboard.php');
                    exit;
                }

                $otp = (string) rand(100000, 999999);
                $_SESSION['pending_admin_id'] = $admin['id'];
                $_SESSION['pending_otp'] = $otp;
                $_SESSION['pending_otp_time'] = time();
                $_SESSION['pending_otp_attempts'] = 0;
                $_SESSION['pending_otp_resend_count'] = 0;
                $_SESSION['show_otp_form'] = true;

                try {
                    $mail = mainLguMailer();
                    $mail->addAddress($admin['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'InfraGovServices — Super Admin OTP Verification';
                    $mail->Body = mainLguOtpEmailHtml($otp, date('Y-m-d H:i:s'));
                    $mail->AltBody = "Your InfraGovServices Super Admin OTP is: {$otp}\nValid for 60 seconds.";
                    $mail->send();
                } catch (\Throwable $e) {
                    error_log('Main LGU OTP mail failed: ' . $e->getMessage());
                    setNotification('error', 'Could not send the OTP email. Please try again.');
                    unset($_SESSION['show_otp_form']);
                }
            } else {
                if ($admin) {
                    mainLguDb()->prepare('UPDATE super_admins SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?')->execute([$admin['id']]);
                }
                setNotification('error', 'Invalid username or password.');
            }
        }

        header('Location: login.php');
        exit;
    }

    if ($action === 'otp_verify') {
        $entered = trim($_POST['otp'] ?? '');
        $valid = !empty($_SESSION['pending_admin_id']) && !empty($_SESSION['pending_otp']);

        if ($valid && (time() - $_SESSION['pending_otp_time']) > 60) {
            setNotification('error', 'That code expired. Request a new one.');
            $valid = false;
        }

        if ($valid && $entered !== $_SESSION['pending_otp']) {
            $_SESSION['pending_otp_attempts'] = ($_SESSION['pending_otp_attempts'] ?? 0) + 1;
            if ($_SESSION['pending_otp_attempts'] >= 3) {
                unset($_SESSION['pending_admin_id'], $_SESSION['pending_otp'], $_SESSION['show_otp_form']);
                setNotification('error', 'Too many incorrect attempts. Please log in again.');
            } else {
                setNotification('error', 'Incorrect code. Please try again.');
            }
            $valid = false;
        }

        if ($valid) {
            $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE id = ?');
            $stmt->execute([$_SESSION['pending_admin_id']]);
            $admin = $stmt->fetch();

            unset($_SESSION['pending_admin_id'], $_SESSION['pending_otp'], $_SESSION['pending_otp_time'], $_SESSION['pending_otp_attempts'], $_SESSION['pending_otp_resend_count'], $_SESSION['show_otp_form']);

            session_regenerate_id(true);
            $_SESSION['super_admin_id'] = $admin['id'];
            $_SESSION['super_admin_name'] = $admin['full_name'];
            $_SESSION['super_admin_email'] = $admin['email'];
            mainLguDb()->prepare('UPDATE super_admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);

            header('Location: dashboard.php');
            exit;
        }

        header('Location: login.php');
        exit;
    }

    if ($action === 'otp_resend') {
        if (!empty($_SESSION['pending_admin_id']) && ($_SESSION['pending_otp_resend_count'] ?? 0) < 1 && (time() - $_SESSION['pending_otp_time']) >= 30) {
            $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE id = ?');
            $stmt->execute([$_SESSION['pending_admin_id']]);
            $admin = $stmt->fetch();

            $otp = (string) rand(100000, 999999);
            $_SESSION['pending_otp'] = $otp;
            $_SESSION['pending_otp_time'] = time();
            $_SESSION['pending_otp_attempts'] = 0;
            $_SESSION['pending_otp_resend_count'] = ($_SESSION['pending_otp_resend_count'] ?? 0) + 1;

            try {
                $mail = mainLguMailer();
                $mail->addAddress($admin['email']);
                $mail->isHTML(true);
                $mail->Subject = 'InfraGovServices — Super Admin OTP Verification';
                $mail->Body = mainLguOtpEmailHtml($otp, date('Y-m-d H:i:s'));
                $mail->send();
                setNotification('success', 'A new code has been sent.');
            } catch (\Throwable $e) {
                error_log('Main LGU OTP resend failed: ' . $e->getMessage());
                setNotification('error', 'Could not resend the code.');
            }
        } else {
            setNotification('warning', 'Please wait before requesting another code.');
        }

        header('Location: login.php');
        exit;
    }

    if ($action === 'forgot_password') {
        $email = trim($_POST['forgot_email'] ?? '');
        $stmt = mainLguDb()->prepare('SELECT id FROM super_admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            mainLguDb()->prepare('UPDATE super_admins SET reset_token = ?, reset_token_expires = ? WHERE id = ?')
                ->execute([$token, $expires, $admin['id']]);

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $basePath = str_replace('login.php', '', $_SERVER['SCRIPT_NAME'] ?? '/admin/login.php');
            $resetUrl = "{$scheme}://{$host}{$basePath}login.php?reset_token={$token}";

            try {
                $mail = mainLguMailer();
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'InfraGovServices — Reset your Super Admin password';
                $mail->Body = mainLguResetEmailHtml($resetUrl);
                $mail->send();
            } catch (\Throwable $e) {
                error_log('Main LGU reset mail failed: ' . $e->getMessage());
            }
        }

        // Same message regardless of whether the email matched, so login
        // enumeration isn't possible via this form.
        setNotification('success', 'If that email is registered, a reset link has been sent.');
        header('Location: login.php');
        exit;
    }

    if ($action === 'reset_password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $adminId = $_SESSION['reset_admin_id'] ?? null;
        $token = $_SESSION['reset_token'] ?? null;

        $stmt = mainLguDb()->prepare('SELECT id FROM super_admins WHERE id = ? AND reset_token = ? AND reset_token_expires > NOW()');
        $stmt->execute([$adminId, $token]);
        $valid = (bool) $stmt->fetch();

        if (!$valid) {
            setNotification('error', 'This reset link is no longer valid. Please request a new one.');
        } elseif (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            setNotification('error', 'Passwords must match and be at least 8 characters.');
            header('Location: login.php');
            exit;
        } else {
            mainLguDb()->prepare('UPDATE super_admins SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
            setNotification('success', 'Password updated. You can now sign in.');
        }

        unset($_SESSION['show_reset_modal'], $_SESSION['reset_admin_id'], $_SESSION['reset_token']);
        header('Location: login.php');
        exit;
    }
}

$showOtpForm = !empty($_SESSION['show_otp_form']);
$showResetModal = !empty($_SESSION['show_reset_modal']);
$otpSecondsLeft = $showOtpForm ? max(0, 60 - (time() - ($_SESSION['pending_otp_time'] ?? time()))) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Login — InfraGovServices</title>
<link rel="icon" href="../public/logocityhall.png" type="image/png">
<script>
(function () {
    try {
        var t = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light';
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root {
        --bg-scrim: linear-gradient(160deg, rgba(238,241,251,.62) 0%, rgba(245,247,253,.55) 55%, rgba(255,255,255,.5) 100%);
        --card-bg: rgba(255,255,255,.86);
        --card-border: rgba(80,100,180,.18);
        --text-primary: #101a3a;
        --text-secondary: #5b6690;
        --input-bg: rgba(255,255,255,.7);
        --input-bg-solid: #eef1fb;
        --input-border: rgba(80,100,180,.24);
        --input-placeholder: #8992b8;
        --scrollbar-track: #eef1fb;
        --scrollbar-thumb: #1a56db;
    }
    [data-theme="dark"] {
        --bg-scrim: linear-gradient(160deg, rgba(5,10,25,.72) 0%, rgba(10,22,40,.68) 55%, rgba(13,31,60,.64) 100%);
        --card-bg: rgba(15,22,48,.78);
        --card-border: rgba(120,140,220,.18);
        --text-primary: #fff;
        --text-secondary: #8b95c0;
        --input-bg: rgba(6,12,30,.55);
        --input-bg-solid: #0d1530;
        --input-border: rgba(120,140,220,.22);
        --input-placeholder: #5b6690;
        --scrollbar-track: #0a1628;
        --scrollbar-thumb: #1a56db;
    }
    * { box-sizing: border-box; }
    html { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track); }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 4px; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        font-family: 'Poppins', system-ui, sans-serif;
        background-image: var(--bg-scrim), url('../public/assets/img/memcir.jpg');
        background-size: cover, cover;
        background-position: center, center;
        background-attachment: fixed, fixed;
        position: relative;
        padding: 24px;
        transition: color .3s;
    }
    /* ── Desktop top bar (floating pills) ── */
    .top-bar {
        position: fixed; top: 18px; left: 18px; right: 18px; z-index: 200;
        display: flex; align-items: center; justify-content: space-between;
    }
    .back-link {
        display: inline-flex; align-items: center; gap: 8px; color: var(--text-primary); text-decoration: none;
        font-size: .84rem; font-weight: 500; background: var(--card-bg); border: 1px solid var(--card-border);
        padding: 9px 16px; border-radius: 50px; backdrop-filter: blur(14px); transition: background .2s, border-color .2s;
    }
    .back-link:hover { background: rgba(79,110,247,.14); }
    .top-actions { display: flex; align-items: center; gap: 14px; }
    .live-clock {
        font-family: 'DM Mono', 'Poppins', monospace; font-size: .78rem; color: var(--text-primary);
        background: var(--card-bg); border: 1px solid var(--card-border); padding: 9px 16px; border-radius: 50px;
        backdrop-filter: blur(14px); white-space: nowrap;
    }
    .clock-date::after { content: ' · '; }
    .theme-toggle { background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; }
    .theme-track {
        width: 46px; height: 25px; background: var(--card-bg); border: 1px solid var(--card-border);
        border-radius: 50px; position: relative; transition: background .3s, border-color .3s; display: block;
    }
    [data-theme="dark"] .theme-track { background: rgba(59,130,246,.25); border-color: rgba(59,130,246,.5); }
    .theme-thumb {
        position: absolute; top: 2px; left: 2px; width: 19px; height: 19px; background: #fff; border-radius: 50%;
        transition: transform .3s cubic-bezier(.34,1.56,.64,1); display: flex; align-items: center; justify-content: center;
        font-size: 10px; box-shadow: 0 1px 6px rgba(0,0,0,.3);
    }
    [data-theme="dark"] .theme-thumb { transform: translateX(21px); }
    .theme-thumb .fa-sun { color: #101a3a; }
    .theme-thumb .fa-moon { display: none; color: #fff; }
    [data-theme="dark"] .theme-thumb .fa-sun { display: none; }
    [data-theme="dark"] .theme-thumb .fa-moon { display: inline; }

    /* ── Mobile: compact always-dark bar (same pattern as public site's mobile nav) ── */
    @media (max-width: 640px) {
        .top-bar {
            position: fixed; top: 0; left: 0; right: 0; height: 54px;
            padding: 0 12px; gap: 8px;
            background: rgba(5,10,25,.94); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(59,130,246,.2); box-shadow: 0 2px 16px rgba(0,0,0,.5);
        }
        .back-link {
            background: rgba(255,255,255,.06); border-color: rgba(255,255,255,.12);
            width: 32px; height: 32px; padding: 0; border-radius: 8px; justify-content: center; gap: 0;
        }
        .back-link:hover { background: rgba(59,130,246,.2); }
        .back-link .back-label { display: none; }
        .top-actions { gap: 8px; }
        .live-clock {
            background: none; border: none; padding: 0; backdrop-filter: none;
            font-size: .74rem; font-weight: 700; color: rgba(255,255,255,.65);
        }
        .clock-date { display: none; }
        .theme-toggle {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
            width: 32px; height: 32px; border-radius: 8px; padding: 0; justify-content: center;
        }
        .theme-toggle:hover { background: rgba(59,130,246,.2); }
        .theme-track { position: static; width: auto; height: auto; background: none !important; border: none !important; display: flex; }
        .theme-thumb {
            position: static; width: auto; height: auto; background: none !important; box-shadow: none; transform: none !important;
        }
        .theme-thumb i { font-size: 14px; }
        .theme-thumb .fa-sun { color: #fbbf24; }
        .theme-thumb .fa-moon { color: #93c5fd; }
    }

    .auth-shell { position: relative; z-index: 1; width: 100%; max-width: 420px; }
    .brand-row {
        display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 26px;
        flex-direction: column; text-align: center;
    }
    .brand-row img { width: 48px; height: 48px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.35); }
    .brand-row .brand-text strong { display: block; color: var(--text-primary); font-size: 1.1rem; letter-spacing: .01em; }
    .brand-row .brand-text span { display: block; color: var(--text-secondary); font-size: .78rem; margin-top: 2px; }

    .card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 38px 34px;
        backdrop-filter: blur(22px);
        -webkit-backdrop-filter: blur(22px);
        box-shadow: 0 24px 70px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.04);
        transition: background .3s, border-color .3s;
        animation: authCardIn .5s cubic-bezier(.34,1.56,.64,1) backwards;
    }
    @keyframes authCardIn {
        from { opacity: 0; transform: translateY(18px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    h1 { color: var(--text-primary); font-size: 1.4rem; font-weight: 700; margin: 0 0 4px; text-align: center; }
    p.sub { color: var(--text-secondary); margin: 0 0 26px; font-size: .88rem; text-align: center; }

    label { display: block; color: var(--text-secondary); font-size: .8rem; font-weight: 500; margin-bottom: 7px; letter-spacing: .02em; }
    .input-box { position: relative; margin-bottom: 18px; }
    input {
        width: 100%; padding: 12px 14px; border-radius: 10px;
        border: 1.5px solid var(--input-border); background: var(--input-bg); color: var(--text-primary);
        font-size: .95rem; font-family: inherit; transition: border-color .2s, box-shadow .2s;
    }
    input::placeholder { color: var(--input-placeholder); }
    input:focus { outline: none; border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.18); }
    /* Browsers paint autofilled fields with their own white/yellow
       background that normal `background` can't override — this inset
       box-shadow trick paints over it with the theme's own input color,
       and the absurdly long transition delays the repaint indefinitely
       so it never flashes white first. */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px var(--input-bg-solid) inset !important;
        box-shadow: 0 0 0 1000px var(--input-bg-solid) inset !important;
        -webkit-text-fill-color: var(--text-primary) !important;
        caret-color: var(--text-primary);
        transition: background-color 5000s ease-in-out 0s, box-shadow 5000s ease-in-out 0s;
    }
    .input-box.has-toggle input { padding-right: 42px; }
    .toggle-eye {
        position: absolute; right: 10px; top: 34px; background: none; border: none; cursor: pointer;
        color: var(--text-secondary); font-size: 1rem; padding: 6px;
    }
    .toggle-eye:hover { color: var(--text-primary); }

    button.btn-primary {
        width: 100%; padding: 13px; border: none; border-radius: 10px;
        background: linear-gradient(135deg, #4f6ef7, #3f5adf);
        color: #fff; font-weight: 600; font-size: .95rem; cursor: pointer; font-family: inherit;
        box-shadow: 0 10px 26px rgba(63,90,223,.35); transition: transform .15s, box-shadow .15s;
    }
    button.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 32px rgba(63,90,223,.45); }
    button.btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    .row-between { display: flex; align-items: center; justify-content: space-between; margin: -6px 0 18px; }
    .link-btn {
        background: none; border: none; color: #7c9dfb; font-size: .82rem; cursor: pointer; padding: 0;
        font-family: inherit; text-decoration: none;
    }
    .link-btn:hover { color: #a9bdfc; text-decoration: underline; }

    .otp-boxes { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; }
    .otp-boxes input {
        width: 44px; height: 52px; text-align: center; font-size: 1.3rem; padding: 0; letter-spacing: 0;
    }
    .otp-meta { text-align: center; color: var(--text-secondary); font-size: .8rem; margin-bottom: 18px; }
    .otp-meta strong { color: var(--text-primary); }

    .notif-popup {
        position: fixed; top: 26px; left: 50%; transform: translateX(-50%);
        min-width: 280px; max-width: 92vw; padding: 15px 26px;
        background: #12193a; color: #eef1ff; border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0,0,0,.4);
        z-index: 10000; display: flex; align-items: center; gap: 12px;
        font-family: 'Poppins', sans-serif; font-size: .92rem; font-weight: 500;
        opacity: 1; transition: opacity .35s; border: 1px solid rgba(120,140,220,.2);
    }
    .notif-popup.notif-success { border-left: 4px solid #4fc97a; }
    .notif-popup.notif-error { border-left: 4px solid #d73f52; }
    .notif-popup.notif-warning { border-left: 4px solid #dda203; }
    .notif-popup.notif-info { border-left: 4px solid #527cdf; }
    .notif-close { background: none; border: none; font-size: 18px; margin-left: auto; color: #7c88b8; cursor: pointer; }

    #loadingOverlay {
        position: fixed; inset: 0; background: rgba(3,6,16,.6);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
        display: none; align-items: center; justify-content: center; z-index: 10001;
        flex-direction: column; opacity: 0; transition: opacity .25s;
    }
    #loadingOverlay.show { display: flex; opacity: 1; }
    .spinner-ring {
        width: 46px; height: 46px; border-radius: 50%;
        border: 3px solid rgba(120,140,220,.25); border-top-color: #4f6ef7;
        animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text { margin-top: 16px; color: #dfe4f7; font-size: .85rem; letter-spacing: .02em; }

    .modal-backdrop {
        position: fixed; inset: 0; background: rgba(3,6,16,.65); backdrop-filter: blur(6px);
        display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px;
    }
    .modal-backdrop.show { display: flex; }
    .modal-card {
        background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px;
        padding: 32px; max-width: 380px; width: 100%; box-shadow: 0 24px 70px rgba(0,0,0,.4);
        backdrop-filter: blur(22px);
    }
    .modal-card .modal-icon { font-size: 1.8rem; margin-bottom: 10px; text-align: center; }
    .modal-card h2 { color: var(--text-primary); font-size: 1.15rem; margin: 0 0 4px; text-align: center; }
    .modal-card p.modal-sub { color: var(--text-secondary); font-size: .82rem; margin: 0 0 22px; text-align: center; }
    .modal-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }

    /* ── Mobile: card sizing ────────────────────────────── */
    @media (max-width: 640px) {
        body { padding: 14px; padding-top: 68px; }
        .auth-shell { max-width: 100%; width: 100%; margin: 0 auto; }
        .card { padding: 28px 22px; border-radius: 18px; }
        h1 { font-size: 1.25rem; }
        .otp-boxes input { font-size: 1.15rem; }
    }
</style>
</head>
<body>

<div id="loadingOverlay"><div class="spinner-ring"></div><div class="loading-text">Please wait…</div></div>
<?php renderNotification(); ?>

<div class="top-bar">
    <a class="back-link" href="../public/citizendash.php"><i class="fas fa-arrow-left"></i> <span class="back-label">Back to InfraGovServices</span></a>
    <div class="top-actions">
        <span class="live-clock"><span class="clock-date" id="clockDate"></span><span class="clock-time" id="clockTime"></span></span>
        <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="theme-track">
                <span class="theme-thumb"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></span>
            </span>
        </button>
    </div>
</div>

<div class="auth-shell">
    <div class="card">
        <div class="brand-row">
            <img src="../public/logocityhall.png" alt="InfraGovServices">
            <div class="brand-text">
                <strong>InfraGovServices</strong>
                <span>Super Admin · SSO Hub</span>
            </div>
        </div>

        <?php if (!$showOtpForm): ?>
            <form method="post" autocomplete="off" id="loginForm">
                <input type="hidden" name="action" value="login">
                <div class="input-box">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="you@infragovservices.com" required autofocus>
                </div>
                <div class="input-box has-toggle">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="toggle-eye" id="togglePassword" tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                <div class="row-between">
                    <button type="button" class="link-btn" id="openForgotModal">Forgot password?</button>
                </div>
                <button type="submit" class="btn-primary">Sign in</button>
            </form>
        <?php else: ?>
            <h1>Verify it's you</h1>
            <p class="sub">We emailed a 6-digit code to your registered address.</p>
            <form method="post" autocomplete="off" id="otpForm">
                <input type="hidden" name="action" value="otp_verify">
                <div class="otp-boxes">
                    <input type="text" name="otp" id="otpInput" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="——————" required autofocus style="width:100%; letter-spacing:.4em; text-align:center;">
                </div>
                <div class="otp-meta">Code expires in <strong id="otpTimer"><?= $otpSecondsLeft ?></strong>s</div>
                <button type="submit" class="btn-primary">Verify</button>
            </form>
            <form method="post" id="resendForm" style="margin-top:12px;">
                <input type="hidden" name="action" value="otp_resend">
                <button type="submit" class="link-btn" style="width:100%; text-align:center;">Resend code</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Forgot password modal -->
<div class="modal-backdrop" id="forgotModal">
    <div class="modal-card">
        <div class="modal-icon">🔑</div>
        <h2>Reset your password</h2>
        <p class="modal-sub">Enter your account email and we'll send a reset link.</p>
        <form method="post" id="forgotForm">
            <input type="hidden" name="action" value="forgot_password">
            <div class="input-box">
                <label for="forgot_email">Email</label>
                <input type="email" id="forgot_email" name="forgot_email" placeholder="you@infragovservices.com" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Send reset link</button>
                <button type="button" class="link-btn" id="closeForgotModal" style="text-align:center;">Back to login</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset password modal -->
<div class="modal-backdrop<?= $showResetModal ? ' show' : '' ?>" id="resetModal">
    <div class="modal-card">
        <div class="modal-icon">🔒</div>
        <h2>Set a new password</h2>
        <p class="modal-sub">Choose a new password for your Super Admin account.</p>
        <form method="post" id="resetForm">
            <input type="hidden" name="action" value="reset_password">
            <div class="input-box has-toggle">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>
                <button type="button" class="toggle-eye" data-target="new_password" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="input-box has-toggle">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                <button type="button" class="toggle-eye" data-target="confirm_password" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Reset password</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeNotif() {
    var n = document.getElementById('notifPopup');
    if (n) { n.style.opacity = '0'; setTimeout(() => n.remove(), 350); }
}
setTimeout(closeNotif, 4500);

function showLoading() { document.getElementById('loadingOverlay').classList.add('show'); }
document.querySelectorAll('form').forEach(f => f.addEventListener('submit', showLoading));

// Password eye-toggle (main login form)
(function () {
    var pwd = document.getElementById('password');
    var btn = document.getElementById('togglePassword');
    var icon = document.getElementById('togglePasswordIcon');
    if (btn) {
        btn.addEventListener('click', function () {
            var show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }
})();

// Password eye-toggles (reset modal, data-target driven)
document.querySelectorAll('.toggle-eye[data-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        var icon = btn.querySelector('i');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});

// Forgot-password modal open/close
var forgotModal = document.getElementById('forgotModal');
var openForgot = document.getElementById('openForgotModal');
var closeForgot = document.getElementById('closeForgotModal');
if (openForgot) openForgot.addEventListener('click', () => forgotModal.classList.add('show'));
if (closeForgot) closeForgot.addEventListener('click', () => forgotModal.classList.remove('show'));

// OTP countdown
var timerEl = document.getElementById('otpTimer');
if (timerEl) {
    var secs = parseInt(timerEl.textContent, 10) || 0;
    var interval = setInterval(function () {
        secs -= 1;
        if (secs <= 0) { clearInterval(interval); secs = 0; }
        timerEl.textContent = secs;
    }, 1000);
}

// Theme toggle — shares localStorage keys with the public site
// (public/citizendash.php) so a preference set on either side carries over.
(function () {
    var html = document.documentElement;
    var btn = document.getElementById('themeToggle');

    function apply(isDark) {
        if (isDark) { html.setAttribute('data-theme', 'dark'); } else { html.removeAttribute('data-theme'); }
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.setItem('theme_backup', isDark ? 'dark' : 'light');
        } catch (e) {}
    }

    var saved = 'light';
    try { saved = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light'; } catch (e) {}
    apply(saved === 'dark');

    btn.addEventListener('click', function () { apply(!html.hasAttribute('data-theme')); });
})();

// Live clock — date hidden on mobile via CSS, only the time shows there
(function () {
    var dateEl = document.getElementById('clockDate');
    var timeEl = document.getElementById('clockTime');
    function tick() {
        var now = new Date();
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>
