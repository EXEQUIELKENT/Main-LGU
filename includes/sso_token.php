<?php
/**
 * Issues short-lived HMAC-signed SSO tokens that a target system's
 * sso_consume endpoint can verify with its own copy of the shared secret.
 * Token TTL is intentionally short (60s) since it's only alive for the
 * length of a single browser redirect.
 */
function issue_sso_token(string $sharedSecret, string $targetSlug, string $email, string $fullName, string $role): string
{
    $payload = [
        'iss' => 'mainlgu',
        'target' => $targetSlug,
        'email' => $email,
        'full_name' => $fullName,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + 60,
        'nonce' => bin2hex(random_bytes(16)),
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $payloadPart = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadPart, $sharedSecret, true);
    $signaturePart = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $payloadPart . '.' . $signaturePart;
}
