<?php

namespace app\services\sync;

use RuntimeException;
use yii\db\Connection;
use Exception;

/**
 * Manages SSH tunnel and remote database connection for sync operations.
 */
class RemoteConnection
{
    private ?Connection $connection = null;
    private ?int $tunnelPid = null;
    private int $localPort;

    public function __construct(
        private readonly string $remoteHost,
        private readonly string $sshUser,
        private readonly string $dbPassword,
        private readonly string $dbName = 'yii',
        private readonly string $dbUser = 'root',
        private readonly int $remoteDbPort = 3306,
        private readonly ?string $sshKeyPath = null
    ) {
        $this->validateInputs();
        $this->localPort = $this->findAvailablePort();
    }

    /**
     * @throws RuntimeException
     */
    private function validateInputs(): void
    {
        // Validate host is IP or hostname (no shell metacharacters)
        if (!preg_match('/^[a-zA-Z0-9.\-]+$/', $this->remoteHost)) {
            throw new RuntimeException('Invalid remote host format');
        }

        // Validate SSH user (alphanumeric, underscore, hyphen only)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $this->sshUser)) {
            throw new RuntimeException('Invalid SSH user format');
        }

        // Validate SSH key path if provided
        if ($this->sshKeyPath !== null && !preg_match('/^[a-zA-Z0-9_.\-\/~]+$/', $this->sshKeyPath)) {
            throw new RuntimeException('Invalid SSH key path format');
        }

        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->dbName)) {
            throw new RuntimeException('Invalid database name format');
        }
    }

    /**
     * Opens SSH tunnel and establishes database connection.
     *
     * @throws RuntimeException
     */
    public function connect(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $this->openTunnel();
        $this->createConnection();

        return $this->connection;
    }

    /**
     * Closes connection and SSH tunnel.
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->closeTunnel();
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->getIsActive();
    }

    private function openTunnel(): void
    {
        $args = [
            'ssh',
            '-f',
            '-N',
            '-L', sprintf('%d:127.0.0.1:%d', $this->localPort, $this->remoteDbPort),
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout=10',
        ];

        if ($this->sshKeyPath !== null) {
            $args[] = '-i';
            $args[] = $this->sshKeyPath;
        }

        $args[] = sprintf('%s@%s', $this->sshUser, $this->remoteHost);

        $command = implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Failed to create SSH tunnel: " . implode("\n", $output)
            );
        }

        // Find tunnel PID using lsof (more reliable than pgrep pattern matching)
        $this->tunnelPid = $this->findTunnelPid();

        // Wait for tunnel to be ready
        $this->waitForTunnel();
    }

    private function findTunnelPid(): ?int
    {
        $command = sprintf('lsof -ti tcp:%d 2>/dev/null', $this->localPort);
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return (int) $output[0];
        }

        return null;
    }

    private function waitForTunnel(int $maxAttempts = 10): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $socket = @fsockopen('127.0.0.1', $this->localPort, $errno, $errstr, 1);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(200000); // 200ms
        }

        throw new RuntimeException(
            "SSH tunnel not ready after {$maxAttempts} attempts on port {$this->localPort}"
        );
    }

    private function closeTunnel(): void
    {
        if ($this->tunnelPid !== null) {
            posix_kill($this->tunnelPid, SIGTERM);
            $this->tunnelPid = null;
        }
    }

    private function createConnection(): void
    {
        $dsn = sprintf(
            'mysql:host=127.0.0.1;port=%d;dbname=%s',
            $this->localPort,
            $this->dbName
        );

        $this->connection = new Connection([
            'dsn' => $dsn,
            'username' => $this->dbUser,
            'password' => $this->dbPassword,
            'charset' => 'utf8mb4',
            'enableSchemaCache' => false,
        ]);

        try {
            $this->connection->open();
        } catch (Exception $e) {
            $this->closeTunnel();
            throw new RuntimeException(
                "Failed to connect to remote database: " . $e->getMessage()
            );
        }
    }

    private function findAvailablePort(int $startPort = 33061, int $endPort = 33100): int
    {
        for ($port = $startPort; $port <= $endPort; $port++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if (!$socket) {
                return $port;
            }
            fclose($socket);
        }

        throw new RuntimeException("No available port found in range {$startPort}-{$endPort}");
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
