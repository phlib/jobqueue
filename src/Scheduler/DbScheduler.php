<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;

/**
 * Class DbScheduler
 *
 * @package Phlib\JobQueue
 */
class DbScheduler implements BatchableSchedulerInterface
{
    protected Adapter $adapter;

    protected int $maximumDelay;

    private int $minimumPickup;

    private bool $skipLocked;

    private int $batchSize;

    /**
     * @param integer $maximumDelay
     * @param integer $minimumPickup
     * @param boolean $skipLocked
     * @param integer $batchSize
     */
    public function __construct(Adapter $adapter, $maximumDelay = 300, $minimumPickup = 600, $skipLocked = false, $batchSize = 50)
    {
        $this->adapter = $adapter;
        $this->maximumDelay = $maximumDelay;
        $this->minimumPickup = $minimumPickup;
        $this->skipLocked = $skipLocked;
        $this->batchSize = $batchSize;
    }

    public function shouldBeScheduled($delay): bool
    {
        return $delay > $this->maximumDelay;
    }

    public function store(JobInterface $job): bool
    {
        $dbTimestampFormat = 'Y-m-d H:i:s';
        return (bool)$this->insert([
            'tube' => $job->getQueue(),
            'data' => serialize($job->getBody()),
            'scheduled_ts' => $job->getDatetimeDelay()->format($dbTimestampFormat),
            'priority' => $job->getPriority(),
            'ttr' => $job->getTtr(),
            'create_ts' => date($dbTimestampFormat),
        ]);
    }

    /**
     * @return array|false
     */
    public function retrieve()
    {
        $job = $this->queryJobs(1);
        return $job ? $job[0] : false;
    }

    /**
     * @return array|false
     */
    public function retrieveBatch()
    {
        return $this->queryJobs($this->batchSize);
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

    /**
     * @param int|string $jobId
     */
    public function remove($jobId): bool
    {
        $table = $this->adapter->quote()->identifier('scheduled_queue');
        $sql = "DELETE FROM {$table} WHERE id = ?";

        return (bool)$this->adapter
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
