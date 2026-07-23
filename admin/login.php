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
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            setNotification('error', 'Enter your username and password.');
        } else {
            $stmt = mainLguDb()->prepare('SELECT * FROM super_admins WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $username]);
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        font-family: 'Poppins', system-ui, sans-serif;
        background:
            radial-gradient(ellipse 900px 600px at 15% 10%, rgba(79,110,247,.20), transparent 60%),
            radial-gradient(ellipse 700px 500px at 90% 90%, rgba(59,130,246,.14), transparent 55%),
            linear-gradient(160deg, #050a19 0%, #0a1628 55%, #0d1f3c 100%);
        overflow: hidden;
        position: relative;
        padding: 24px;
    }
    body::before {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background-image: radial-gradient(circle, rgba(255,255,255,.035) 1px, transparent 1px);
        background-size: 26px 26px;
    }
    .auth-shell { position: relative; z-index: 1; width: 100%; max-width: 420px; }
    .brand-row {
        display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 22px;
    }
    .brand-row img { width: 42px; height: 42px; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,.35); }
    .brand-row .brand-text strong { display: block; color: #fff; font-size: 1.02rem; letter-spacing: .01em; }
    .brand-row .brand-text span { display: block; color: #7c88b8; font-size: .75rem; }

    .card {
        background: rgba(15, 22, 48, .72);
        border: 1px solid rgba(120, 140, 220, .18);
        border-radius: 20px;
        padding: 38px 34px;
        backdrop-filter: blur(22px);
        -webkit-backdrop-filter: blur(22px);
        box-shadow: 0 24px 70px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.04);
    }
    h1 { color: #fff; font-size: 1.4rem; font-weight: 700; margin: 0 0 4px; }
    p.sub { color: #8b95c0; margin: 0 0 26px; font-size: .88rem; }

    label { display: block; color: #b6bedc; font-size: .8rem; font-weight: 500; margin-bottom: 7px; letter-spacing: .02em; }
    .input-box { position: relative; margin-bottom: 18px; }
    input {
        width: 100%; padding: 12px 14px; border-radius: 10px;
        border: 1.5px solid rgba(120, 140, 220, .22); background: rgba(6, 12, 30, .55); color: #fff;
        font-size: .95rem; font-family: inherit; transition: border-color .2s, box-shadow .2s;
    }
    input::placeholder { color: #5b6690; }
    input:focus { outline: none; border-color: #4f6ef7; box-shadow: 0 0 0 3px rgba(79,110,247,.18); }
    .input-box.has-toggle input { padding-right: 42px; }
    .toggle-eye {
        position: absolute; right: 10px; top: 34px; background: none; border: none; cursor: pointer;
        color: #7c88b8; font-size: 1rem; padding: 6px;
    }
    .toggle-eye:hover { color: #b6bedc; }

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
    .otp-meta { text-align: center; color: #7c88b8; font-size: .8rem; margin-bottom: 18px; }
    .otp-meta strong { color: #dfe4f7; }

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
        background: rgba(15, 22, 48, .92); border: 1px solid rgba(120,140,220,.2); border-radius: 18px;
        padding: 32px; max-width: 380px; width: 100%; box-shadow: 0 24px 70px rgba(0,0,0,.5);
    }
    .modal-card .modal-icon { font-size: 1.8rem; margin-bottom: 10px; text-align: center; }
    .modal-card h2 { color: #fff; font-size: 1.15rem; margin: 0 0 4px; text-align: center; }
    .modal-card p.modal-sub { color: #8b95c0; font-size: .82rem; margin: 0 0 22px; text-align: center; }
    .modal-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }
</style>
</head>
<body>

<div id="loadingOverlay"><div class="spinner-ring"></div><div class="loading-text">Please wait…</div></div>
<?php renderNotification(); ?>

<div class="auth-shell">
    <div class="brand-row">
        <img src="../public/logocityhall.png" alt="InfraGovServices">
        <div class="brand-text">
            <strong>InfraGovServices</strong>
            <span>Super Admin · SSO Hub</span>
        </div>
    </div>

    <div class="card">
        <?php if (!$showOtpForm): ?>
            <h1>Sign in</h1>
            <p class="sub">Access the admin side of every connected system.</p>
            <form method="post" autocomplete="off" id="loginForm">
                <input type="hidden" name="action" value="login">
                <div class="input-box">
                    <label for="username">Username or email</label>
                    <input type="text" id="username" name="username" placeholder="superadmin" required autofocus>
                </div>
                <div class="input-box has-toggle">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="toggle-eye" id="togglePassword" tabindex="-1" aria-label="Show password">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
                <div class="row-between">
                    <span></span>
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
</script>
</body>
</html>
