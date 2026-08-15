<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

/**
 * Keeps track of which user has which WebSocket connections.
 * @version b-2026.08.13
 */
class UserConnectionRegistry
{
    /** @var array<string, array<int, \Workerman\Connection\TcpConnection>> */
    private array $connections = [];

    /**
     * Register a connection for a user.
     */
    public function add(string $userId, $connection): void
    {
        $this->connections[$userId] ??= [];
        // Index by connection id to avoid duplicates
        $this->connections[$userId][spl_object_id($connection)] = $connection;
    }

    /**
     * Remove a connection.
     */
    public function remove(string $userId, $connection): void
    {
        if (!isset($this->connections[$userId])) {
            return;
        }
        unset($this->connections[$userId][spl_object_id($connection)]);
        if (empty($this->connections[$userId])) {
            unset($this->connections[$userId]);
        }
    }

    /**
     * Push a payload to all online connections of a user.
     */
    public function pushToUser(string $userId, array $payload): void
    {
        if (!isset($this->connections[$userId])) {
            return;
        }
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach ($this->connections[$userId] as $connection) {
            try {
                $connection->send($data);
            } catch (\Throwable) {
                // Ignore send errors; the connection will be cleaned up on close
            }
        }
    }

    /**
     * Total number of registered connections.
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->connections as $list) {
            $count += count($list);
        }
        return $count;
    }

    /**
     * List user ids with at least one connection.
     *
     * @return string[]
     */
    public function onlineUserIds(): array
    {
        return array_keys($this->connections);
    }
}
