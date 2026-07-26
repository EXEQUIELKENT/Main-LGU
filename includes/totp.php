<?php
/**
 * RFC 6238 TOTP (Time-based One-Time Password), the standard behind
 * Google Authenticator / Authy / 1Password's authenticator field — no
 * external library, just HMAC-SHA1 over a 30-second time step, same as
 * every other hand-rolled integration in this codebase.
 */

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $output .= $alphabet[bindec($chunk)];
    }

    return $output;
}

function totp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));

    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $output = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $output .= chr(bindec($byte));
        }
    }

    return $output;
}

function totp_code(string $secretBase32, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $timestamp ??= time();
    $counter = (int) floor($timestamp / $period);

    $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter
    $key = totp_base32_decode($secretBase32);
    $hash = hash_hmac('sha1', $binCounter, $key, true);

    $offset = ord($hash[19]) & 0x0F;
    $truncated = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );

    return str_pad((string) ($truncated % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Accepts a code from the current time step or one step either side
 * (±30s) to tolerate clock drift between the server and the phone.
 */
function totp_verify(string $secretBase32, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
{
    $code = trim($code);
    if (!preg_match('/^\d{' . $digits . '}$/', $code)) {
        return false;
    }

    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretBase32, $now + ($i * $period), $period, $digits), $code)) {
            return true;
        }
    }

    return false;
}

function totp_provisioning_uri(string $secretBase32, string $accountEmail, string $issuer = 'InfraGovServices'): string
{
    $label = rawurlencode($issuer . ':' . $accountEmail);
    $params = http_build_query([
        'secret' => $secretBase32,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);

    return "otpauth://totp/{$label}?{$params}";
}

/**
 * @return string[] Plaintext recovery codes — show once, store only the
 *                   hashed form (super_admin_recovery_codes.code_hash).
 */
function totp_generate_recovery_codes(int $count = 8): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5))); // 10 hex chars
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }

    return $codes;
}
