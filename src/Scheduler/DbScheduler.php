<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;

/**
 * @package Phlib\JobQueue
 */
class DbScheduler implements SchedulerInterface
{
    protected Adapter $adapter;

    protected int $maximumDelay;

    private int $minimumPickup;

    /**
     * @param integer $maximumDelay
     * @param integer $minimumPickup
     */
    public function __construct(Adapter $adapter, $maximumDelay = 300, $minimumPickup = 600)
    {
        $this->adapter = $adapter;
        $this->maximumDelay = $maximumDelay;
        $this->minimumPickup = $minimumPickup;
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
        $sql = '
            UPDATE scheduled_queue SET
                picked_by = CONNECTION_ID(),
                picked_ts = NOW()
            WHERE
                scheduled_ts <= CURRENT_TIMESTAMP + INTERVAL :minimumPickup SECOND AND
                picked_by IS NULL
            ORDER BY
                scheduled_ts DESC
            LIMIT 1';
        $stmt = $this->adapter->query($sql, [
            ':minimumPickup' => $this->minimumPickup,
        ]);
        if ($stmt->rowCount() === 0) {
            return false; // no jobs
        }

        $sql = 'SELECT * FROM `scheduled_queue` WHERE picked_by = CONNECTION_ID() LIMIT 1';
        $row = $this->adapter->query($sql)->fetch(\PDO::FETCH_ASSOC);

        $scheduledTime = strtotime($row['scheduled_ts']);
        $delay = $scheduledTime - time();
        if ($delay < 0) {
            $delay = 0;
        }

        return [
            'id' => $row['id'],
            'queue' => $row['tube'],
            'data' => unserialize($row['data']),
            'delay' => $delay,
            'priority' => $row['priority'],
            'ttr' => $row['ttr'],
        ];
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
