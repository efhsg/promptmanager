<?php

namespace app\services\sync;

use Exception;
use RuntimeException;
use Yii;
use yii\db\Connection;

/**
 * Orchestrates bidirectional database sync between local and remote.
 */
class SyncService
{
    private EntitySyncer $entitySyncer;
    private ?RemoteConnection $remoteConnection = null;

    public function __construct(
        private readonly Connection $localDb
    ) {
        $this->entitySyncer = new EntitySyncer();
    }

    public function status(int $userId): array
    {
        $remote = $this->getRemoteConnection();
        $remoteDb = $remote->connect();

        try {
            $pullReport = $this->calculateDiff($remoteDb, $this->localDb, $userId);
            $pushReport = $this->calculateDiff($this->localDb, $remoteDb, $userId);

            return [
                'pull' => $pullReport->toArray(),
                'push' => $pushReport->toArray(),
            ];
        } finally {
            $remote->disconnect();
        }
    }

    public function pull(int $userId, bool $dryRun = false): SyncReport
    {
        $remote = $this->getRemoteConnection();
        $remoteDb = $remote->connect();

        try {
            return $this->sync($remoteDb, $this->localDb, $userId, $dryRun);
        } finally {
            $remote->disconnect();
        }
    }

    public function push(int $userId, bool $dryRun = false): SyncReport
    {
        $remote = $this->getRemoteConnection();
        $remoteDb = $remote->connect();

        try {
            return $this->sync($this->localDb, $remoteDb, $userId, $dryRun);
        } finally {
            $remote->disconnect();
        }
    }

    public function run(int $userId, bool $dryRun = false): array
    {
        $remote = $this->getRemoteConnection();
        $remoteDb = $remote->connect();

        try {
            $pullReport = $this->sync($remoteDb, $this->localDb, $userId, $dryRun);
            $pushReport = $this->sync($this->localDb, $remoteDb, $userId, $dryRun);

            return [
                'pull' => $pullReport,
                'push' => $pushReport,
            ];
        } finally {
            $remote->disconnect();
        }
    }

    private function sync(
        Connection $sourceDb,
        Connection $destDb,
        int $userId,
        bool $dryRun
    ): SyncReport {
        $report = new SyncReport();

        if (!$dryRun) {
            $transaction = $destDb->beginTransaction();
        }

        try {
            foreach ($this->entitySyncer->getSyncOrder() as $entity) {
                $this->entitySyncer->syncEntity(
                    $entity,
                    $sourceDb,
                    $destDb,
                    $report,
                    $userId,
                    $dryRun
                );
            }

            if (!$dryRun && isset($transaction)) {
                if ($report->hasErrors()) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
            }
        } catch (Exception $e) {
            if (!$dryRun && isset($transaction)) {
                $transaction->rollBack();
            }
            $report->addError('sync', "Sync failed: " . $e->getMessage());
        }

        return $report;
    }

    private function calculateDiff(Connection $sourceDb, Connection $destDb, int $userId): SyncReport
    {
        return $this->sync($sourceDb, $destDb, $userId, true);
    }

    private function getRemoteConnection(): RemoteConnection
    {
        if ($this->remoteConnection !== null) {
            return $this->remoteConnection;
        }

        $config = Yii::$app->params['sync'] ?? [];

        $host = $config['remoteHost'] ?? null;
        $user = $config['remoteUser'] ?? 'esg';
        $password = $config['remoteDbPassword'] ?? null;
        $dbName = $config['remoteDbName'] ?? 'yii';
        $sshKeyPath = $config['sshKeyPath'] ?? null;

        if (empty($host)) {
            throw new RuntimeException("Sync config 'remoteHost' is not set in params.php");
        }

        if (empty($password)) {
            throw new RuntimeException("Sync config 'remoteDbPassword' is not set in params.php");
        }

        $this->remoteConnection = new RemoteConnection(
            remoteHost: $host,
            sshUser: $user,
            dbPassword: $password,
            dbName: $dbName,
            sshKeyPath: $sshKeyPath
        );

        return $this->remoteConnection;
    }
}
