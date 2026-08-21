<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use OCP\IConfig;

/**
 * Issues and validates short-lived HMAC-signed tokens for WebSocket
 * authentication.
 *
 * The token is stateless: it encodes the user id, an expiry timestamp and
 * an HMAC-SHA256 signature computed with the Nextcloud secret. Both the
 * web app (which issues tokens) and the realtime service (which validates
 * them) can read the same secret, so no shared cache is required.
 *
 * Token format: base64url(userId).base64url(expiry).base64url(hmac)
 * @version b-2026.08.21
 */
class RealtimeTokenService
{
    public function __construct(
        private IConfig $config,
        private int $ttl = 60,
    ) {
    }

    /**
     * Issue a token for a user.
     */
    public function issue(string $userId): string
    {
        $expiry = time() + $this->ttl;
        $secret = $this->config->getSystemValueString('secret');
        $data = "$userId.$expiry";
        $sig = hash_hmac('sha256', $data, $secret);
        return rtrim(base64_encode($userId), '=') . '.'
            . rtrim(base64_encode((string)$expiry), '=') . '.'
            . rtrim(base64_encode($sig), '=');
    }

    /**
     * Validate a token. Returns the user id or null if invalid/expired.
     */
    public function consume(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$userB64, $expiryB64, $sigB64] = $parts;

        $userId = base64_decode($userB64);
        $expiry = (int)base64_decode($expiryB64);
        $sig = base64_decode($sigB64);

        if ($userId === false || $expiry === 0) {
            return null;
        }
        // Expired
        if ($expiry < time()) {
            return null;
        }

        $secret = $this->config->getSystemValueString('secret');
        $expected = hash_hmac('sha256', "$userId.$expiry", $secret);

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        return $userId;
    }
}
