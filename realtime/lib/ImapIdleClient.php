<?php

declare(strict_types=1);

namespace OCA\YooMailRealtime;

/**
 * Minimal IMAP IDLE client implemented over a plain socket.
 *
 * This intentionally does NOT depend on Horde. It is only a "doorbell":
 * it keeps an IDLE connection open and reports when the server signals
 * that a mailbox changed (EXISTS / RECENT / EXPUNGE). It never fetches
 * or parses message content — the actual sync is done by reusing the
 * existing Nextcloud Mail synchronization services.
 * @version b-2026.08.21
 */
class ImapIdleClient
{
    private $socket;
    private int $tagCounter = 0;
    private bool $idleActive = false;

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $sslMode = 'ssl',
        private int $timeout = 10,
    ) {
    }

    /**
     * Open the socket and authenticate.
     *
     * @throws \RuntimeException
     */
    public function connect(): void
    {
        $transport = $this->sslMode === 'none' || $this->sslMode === false
            ? "tcp://{$this->host}:{$this->port}"
            : "ssl://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $this->socket = @stream_socket_client(
            $transport,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            throw new \RuntimeException("IMAP connect failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read the greeting banner
        $this->readLine();

        // Capability check
        $capability = $this->command('CAPABILITY');
        if (!preg_match('/\bIDLE\b/i', $capability)) {
            throw new \RuntimeException("IMAP server does not support IDLE for {$this->host}");
        }

        // Login
        $loginResponse = $this->command(
            'LOGIN ' . $this->quote($this->username) . ' ' . $this->quote($this->password)
        );
        if (!preg_match('/\bOK\b/i', $loginResponse)) {
            throw new \RuntimeException("IMAP login failed: $loginResponse");
        }
    }

    /**
     * SELECT a mailbox (e.g. INBOX).
     */
    public function select(string $mailbox): void
    {
        $response = $this->command('SELECT ' . $this->quote($mailbox));
        if (!preg_match('/\bOK\b/i', $response)) {
            throw new \RuntimeException("SELECT $mailbox failed: $response");
        }
    }

    /**
     * Enter IDLE and block until a mailbox change is signaled or the
     * timeout elapses.
     *
     * Returns the raw untagged response line that triggered the wake-up,
     * or null if the timeout expired without any change.
     *
     * @param float|int $idleTimeout seconds to stay in IDLE before forcing
     *                              a refresh (servers usually require ~29min)
     * @return string|null
     * @throws \RuntimeException
     */
    public function waitForChange(float|int $idleTimeout = 1500): ?string
    {
        $this->tagCounter++;
        $tag = 'A' . str_pad((string)$this->tagCounter, 3, '0', STR_PAD_LEFT);

        fwrite($this->socket, "$tag IDLE\r\n");
        $this->idleActive = true;

        $start = microtime(true);
        $deadline = $start + $idleTimeout;

        // First line should be "+ idling"
        $line = $this->readLine();
        if ($line !== '+ idling') {
            $this->idleActive = false;
            throw new \RuntimeException("IDLE not started, got: $line");
        }

        while (microtime(true) < $deadline) {
            $remaining = $deadline - microtime(true);
            $read = [$this->socket];
            $write = null;
            $except = null;
            $sec = (int)floor($remaining);
            $usec = (int)(($remaining - $sec) * 1_000_000);

            $n = @stream_select($read, $write, $except, $sec, $usec);
            if ($n === false) {
                // Interrupted or error
                break;
            }
            if ($n === 0) {
                // Timeout
                break;
            }

            $line = $this->readLine();
            if ($line === false) {
                break;
            }

            // Untagged response * n EXISTS / * n RECENT / * n EXPUNGE
            if (preg_match('/^\* \d+ (EXISTS|RECENT|EXPUNGE)/i', trim($line))) {
                $this->stopIdle();
                return trim($line);
            }

            // If the server responded with the tagged OK while still in
            // IDLE (shouldn't happen), bail out.
            if (preg_match("/^$tag OK/i", trim($line))) {
                $this->idleActive = false;
                break;
            }
        }

        $this->stopIdle();
        return null;
    }

    /**
     * Send DONE to terminate an active IDLE session.
     */
    public function stopIdle(): void
    {
        if (!$this->idleActive) {
            return;
        }
        fwrite($this->socket, "DONE\r\n");
        // Consume the tagged OK for the IDLE command
        $this->readLine();
        $this->idleActive = false;
    }

    /**
     * Send a NOOP to keep the connection alive.
     */
    public function noop(): void
    {
        $this->command('NOOP');
    }

    public function logout(): void
    {
        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // ignore
        }
        $this->close();
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        $this->idleActive = false;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    /**
     * Send a command and return all response lines (including untagged lines).
     */
    private function command(string $cmd): string
    {
        $this->tagCounter++;
        $tag = 'A' . str_pad((string)$this->tagCounter, 3, '0', STR_PAD_LEFT);
        fwrite($this->socket, "$tag $cmd\r\n");

        $lines = [];
        while (true) {
            $line = $this->readLine();
            if ($line === false) {
                throw new \RuntimeException("Connection closed during command: $cmd");
            }
            $lines[] = trim($line);
            if (preg_match("/^$tag (OK|NO|BAD)/i", trim($line))) {
                break;
            }
        }
        return implode("\n", $lines);
    }

    private function readLine(): string|false
    {
        if (!is_resource($this->socket)) {
            return false;
        }
        $line = fgets($this->socket);
        if ($line === false) {
            return false;
        }
        return rtrim($line, "\r\n");
    }

    private function quote(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }
}
