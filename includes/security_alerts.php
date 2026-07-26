<?php
require_once __DIR__ . '/mailer.php';

/**
 * Emails a security alert either to one specific address (account-level
 * events — a lockout or 2FA change on that one account) or to every
 * super admin (team-wide events — a shared secret or system config
 * change affects everyone with access). Mirrors the try/catch-and-log
 * pattern already used for OTP/reset mail in admin/login.php: a failed
 * send must never break the action that triggered the alert.
 */
function sendSecurityAlert(string $title, string $message, ?string $specificEmail = null): void
{
    if ($specificEmail !== null) {
        $recipients = [$specificEmail];
    } else {
        $recipients = array_column(mainLguDb()->query('SELECT email FROM super_admins')->fetchAll(), 'email');
    }

    $when = date('M j, Y g:i A') . ' (Asia/Manila)';
    $body = mainLguAlertEmailHtml($title, $message, $when);

    foreach ($recipients as $email) {
        try {
            $mail = mainLguMailer();
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'InfraGovServices — Security alert: ' . $title;
            $mail->Body = $body;
            $mail->AltBody = "{$title}\n\n{$message}\n\n{$when}";
            $mail->send();
        } catch (\Throwable $e) {
            error_log('Main LGU security alert mail failed: ' . $e->getMessage());
        }
    }
}
