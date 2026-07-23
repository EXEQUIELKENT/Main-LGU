<?php
require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Reuses the same working Gmail SMTP sender already configured for CIMM's
 * OTP/reset emails (LGU/lgu-portal/public/citizen/login.php createMailer()),
 * so this doesn't need a brand-new mail account provisioned.
 */
function mainLguMailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lguportal2026@gmail.com';
    $mail->Password = 'krdatioghgqriruh';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'quoted-printable';
    $mail->Timeout = 30;
    $mail->SMTPAutoTLS = false;
    $mail->SMTPKeepAlive = false;
    $mail->WordWrap = 0;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->setFrom('lguportal2026@gmail.com', 'InfraGovServices SSO Hub', false);

    return $mail;
}

function mainLguOtpEmailHtml(string $otp, string $sentAt): string
{
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f5f5f5">
    <div style="max-width:500px;margin:0 auto;background:#fff;border-radius:12px;padding:40px 30px;box-shadow:0 2px 10px rgba(0,0,0,0.1)">
        <h1 style="color:#101a3a;margin:0 0 10px 0;font-size:28px;text-align:center;">InfraGovServices</h1>
        <h2 style="color:#4e627f;margin:0 0 30px 0;font-size:18px;font-weight:400;text-align:center;">Super Admin OTP Verification</h2>
        <div style="background:#eef1fb;border-radius:8px;padding:25px;text-align:center;margin:30px 0">
            <div style="color:#666;font-size:16px;margin-bottom:10px">Your authentication code is</div>
            <div style="font-size:42px;font-family:'Courier New',monospace;color:#4f6ef7;font-weight:700;letter-spacing:8px">{$otp}</div>
            <div style="color:#999;font-size:12px;margin-top:12px">Sent: {$sentAt}</div>
        </div>
        <p style="color:#666;font-size:14px;line-height:1.6;margin:20px 0;text-align:center;">
            This code is valid for <strong style="color:#3f5adf">60 seconds</strong> and can only be used once.
        </p>
        <p style="color:#ca173f;font-size:14px;font-weight:700;margin:20px 0;text-align:center;">
            Never share this code with anyone.<br>InfraGovServices staff will never ask for this code.
        </p>
        <p style="color:#999;font-size:12px;margin-top:30px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
            Didn't request this OTP? You may safely ignore this email.
        </p>
        <p style="color:#999;font-size:11px;text-align:center;margin-top:30px">&copy; InfraGovServices</p>
    </div>
</body></html>
HTML;
}

function mainLguResetEmailHtml(string $resetUrl): string
{
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f5f5f5">
    <div style="max-width:500px;margin:0 auto;background:#fff;border-radius:12px;padding:40px 30px;box-shadow:0 2px 10px rgba(0,0,0,0.1)">
        <h1 style="color:#101a3a;margin:0 0 10px 0;font-size:28px;text-align:center;">InfraGovServices</h1>
        <h2 style="color:#4e627f;margin:0 0 30px 0;font-size:18px;font-weight:400;text-align:center;">Password Reset Request</h2>
        <p style="color:#666;font-size:14px;line-height:1.6;text-align:center;">Click the button below to set a new password for your Super Admin account.</p>
        <div style="text-align:center;margin:30px 0">
            <a href="{$resetUrl}" style="display:inline-block;background:#4f6ef7;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px;">Reset Password</a>
        </div>
        <p style="color:#666;font-size:13px;">Or copy and paste this link into your browser:</p>
        <p style="color:#4f6ef7;font-size:12px;word-break:break-all;background:#f0f4f8;padding:12px;border-radius:6px;">{$resetUrl}</p>
        <p style="color:#666;font-size:14px;text-align:center;">This link is valid for <strong>1 hour</strong>.</p>
        <p style="color:#999;font-size:12px;margin-top:30px;border-top:1px solid #eee;padding-top:20px;text-align:center;">
            Didn't request this? You may safely ignore this email.
        </p>
    </div>
</body></html>
HTML;
}
