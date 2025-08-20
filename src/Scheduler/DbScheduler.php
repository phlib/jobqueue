<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;
use STS\Backoff\Backoff;

/**
 * @package Phlib\JobQueue
 */
class DbScheduler implements BatchableSchedulerInterface
{
    private const MYSQL_SERIALIZATION_FAILURE = '40001';

    private const MYSQL_DEADLOCK = '1213';

    public function __construct(
        protected Adapter $adapter,
        protected int $maximumDelay = 300,
        private readonly int $minimumPickup = 600,
        private readonly bool $skipLocked = false,
        private readonly int $batchSize = 50,
        private ?Backoff $backoff = null
    ) {
        if ($this->backoff) {
            $this->backoff->setDecider(
                function (int $attempt, int $maxAttempts, $result, ?\Throwable $e = null) {
                    if ($e === null) {
                        return false;
                    }

                    if ($e instanceof \PDOException && $this->isDeadlock($e) && $attempt < $maxAttempts) {
                        return true;
                    }

                    throw $e;
                }
            );
        }
    }

    public function shouldBeScheduled($delay): bool
    {
        return $delay > $this->maximumDelay;
    }

    public function store(JobInterface $job): bool
    {
        $dbTimestampFormat = 'Y-m-d H:i:s';
        return (bool) $this->insert([
            'tube' => $job->getQueue(),
            'data' => serialize($job->getBody()),
            'scheduled_ts' => $job->getDatetimeDelay()->format($dbTimestampFormat),
            'priority' => $job->getPriority(),
            'ttr' => $job->getTtr(),
            'create_ts' => date($dbTimestampFormat),
        ]);
    }

    public function retrieve(): array|false
    {
        $job = $this->queryJobsWithRetry(1);
        return $job ? $job[0] : false;
    }

    /**
     * @return array|false
     */
    public function retrieveBatch()
    {
        return $this->queryJobsWithRetry($this->batchSize);
    }

    private function isDeadlock(\PDOException $exception): bool
    {
        $regex = '/SQLSTATE\[' . self::MYSQL_SERIALIZATION_FAILURE . '\].*\s' . self::MYSQL_DEADLOCK . '\s/';

        return (string) $exception->getCode() === self::MYSQL_SERIALIZATION_FAILURE
            && preg_match($regex, $exception->getMessage());
    }

    /**
     * @return array|false
     */
    private function queryJobsWithRetry(int $batchSize)
    {
        if ($this->backoff) {
            return $this->backoff->run(function () use ($batchSize) {
                try {
                    return $this->queryJobs($batchSize);
                } catch (\PDOException $e) {
                    if ($this->adapter->getConnection()->inTransaction()) {
                        $this->adapter->rollBack();
                    }
                    throw $e;
                }
            });
        }

        return $this->queryJobs($batchSize);
    }

    /**
     * @return array|false
     */
    private function queryJobs(int $batchSize)
    {
        $this->adapter->beginTransaction();

        $sql = <<<SQL
            SELECT * FROM scheduled_queue
            WHERE
                scheduled_ts <= CURRENT_TIMESTAMP + INTERVAL :minimumPickup SECOND AND
                picked_by IS NULL
            ORDER BY
                scheduled_ts DESC
            LIMIT {$batchSize}
            FOR UPDATE
            SQL;

        if ($this->skipLocked) {
            $sql .= ' SKIP LOCKED';
        }

        $stmt = $this->adapter->query($sql, [
            ':minimumPickup' => $this->minimumPickup,
        ]);

        $rowCount = $stmt->rowCount();

        if ($rowCount === 0) {
            $this->adapter->rollBack();
            return false; // no jobs
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $placeholders = implode(',', array_fill(0, $rowCount, '?'));
        $sql = <<<SQL
            UPDATE scheduled_queue SET
                picked_by = CONNECTION_ID(),
                picked_ts = NOW()
            WHERE id IN ( {$placeholders} )
        SQL;

        $this->adapter->query($sql, array_column($rows, 'id'));

        $this->adapter->commit();

        $jobs = [];

        foreach ($rows as $row) {
            $scheduledTime = strtotime($row['scheduled_ts']);
            $delay = $scheduledTime - time();
            if ($delay < 0) {
                $delay = 0;
            }

            $jobs[] = [
                'id' => (int) $row['id'],
                'queue' => $row['tube'],
                'data' => unserialize($row['data']),
                'delay' => $delay,
                'priority' => (int) $row['priority'],
                'ttr' => (int) $row['ttr'],
            ];
        }

        return $jobs;
    }

    public function remove(int|string $jobId): bool
    {
        $table = $this->adapter->quote()->identifier('scheduled_queue');
        $sql = "DELETE FROM {$table} WHERE id = ?";

        return (bool) $this->adapter
            ->query($sql, [$jobId])
            ->rowCount();
    }

    public function removeBatch(array $jobIds): bool
    {
        $table = $this->adapter->quote()->identifier('scheduled_queue');
        $sql = "DELETE FROM {$table} WHERE id IN ( " . implode(',', array_fill(0, count($jobIds), '?')) . ' )';

        return $this->adapter
            ->query($sql, $jobIds)
            ->rowCount() === count($jobIds);
    }

    protected function insert(array $data): int
    {
        $table = $this->adapter->quote()->identifier('scheduled_queue');
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";

        return $this->adapter
            ->query($sql, array_values($data))
            ->rowCount();
    }
}
