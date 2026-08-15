<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

use Workerman\Connection\TcpConnection;

/**
 * WebSocket message handling.
 *
 * The actual Workerman worker is created by RealtimeServer (so the WS
 * worker and the IDLE listeners can coexist in the same process). This
 * class only contains the auth + event wiring logic.
 * @version b-2026.08.13
 */
class WebSocketServer
{
    public function __construct(
        private UserConnectionRegistry $registry,
        private RealtimeTokenService $tokenService,
    ) {
    }

    /**
     * Handle an incoming message. Called by the Workerman onMessage handler.
     */
    public function handleMessage(TcpConnection $connection, $data): void
    {
        // Already authenticated: this protocol is one-way after auth
        if (isset($connection->userId)) {
            return;
        }

        $payload = json_decode((string)$data, true);
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'auth') {
            $connection->close(json_encode(['type' => 'error', 'message' => 'auth required']));
            return;
        }

        $token = (string)($payload['token'] ?? '');
        $userId = $this->tokenService->consume($token);

        if ($userId === null) {
            $connection->close(json_encode(['type' => 'error', 'message' => 'invalid token']));
            return;
        }

        $connection->userId = $userId;
        $this->registry->add($userId, $connection);

        $connection->send(json_encode([
            'type' => 'connected',
            'userId' => $userId,
            'timestamp' => time(),
        ]));

        echo "[yoomail-realtime] user $userId connected\n";
    }

    /**
     * Handle connection close.
     */
    public function handleClose(TcpConnection $connection): void
    {
        $userId = $connection->userId ?? null;
        if ($userId !== null) {
            $this->registry->remove($userId, $connection);
            echo "[yoomail-realtime] user $userId disconnected\n";
        }
    }
}
