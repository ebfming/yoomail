<?php

declare(strict_types=1);

namespace OCA\YooMail\Service;

use OCP\IConfig;

/**
 * Shared auth helper used by both the Nextcloud app and the detached realtime
 * service for websocket auth and local IPC message signing.
 * @version b-2026.08.21
 */
class RealtimeAuthService
{
    private const IPC_MAX_SKEW_SECONDS = 300;

    public function __construct(
        private IConfig $config,
        private int $ttl = 60,
    ) {
    }

    public function issueToken(string $userId): string
    {
        $expiry = time() + $this->ttl;
        $sig = hash_hmac('sha256', "$userId.$expiry", $this->getSecret());

        return rtrim(base64_encode($userId), '=') . '.'
            . rtrim(base64_encode((string)$expiry), '=') . '.'
            . rtrim(base64_encode($sig), '=');
    }

    public function consumeToken(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$userB64, $expiryB64, $sigB64] = $parts;

        $userId = base64_decode($userB64, true);
        $expiryRaw = base64_decode($expiryB64, true);
        $sig = base64_decode($sigB64, true);

        if ($userId === false || $expiryRaw === false || $sig === false || !is_numeric($expiryRaw)) {
            return null;
        }

        $expiry = (int)$expiryRaw;
        if ($expiry < time()) {
            return null;
        }

        $expected = hash_hmac('sha256', "$userId.$expiry", $this->getSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        return $userId;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function signIpcPayload(array $payload): array
    {
        $payload['auth'] = hash_hmac('sha256', $this->canonicalize($payload), $this->getSecret());
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function consumeIpcPayload(array $payload): ?array
    {
        $signature = $payload['auth'] ?? null;
        if (!is_string($signature) || $signature === '') {
            return null;
        }

        unset($payload['auth']);

        $timestamp = $payload['timestamp'] ?? null;
        if (!is_int($timestamp) && !(is_string($timestamp) && ctype_digit($timestamp))) {
            return null;
        }

        $timestamp = (int)$timestamp;
        if (abs(time() - $timestamp) > self::IPC_MAX_SKEW_SECONDS) {
            return null;
        }

        $expected = hash_hmac('sha256', $this->canonicalize($payload), $this->getSecret());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $payload;
    }

    private function getSecret(): string
    {
        return $this->config->getSystemValueString('secret');
    }

    /**
     * @param mixed $value
     */
    private function canonicalize($value): string
    {
        return json_encode($this->normalizeValue($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalizeValue(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeValue($item);
        }

        return $value;
    }
}
