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
    public function __construct(
        protected Adapter $adapter,
        protected int $maximumDelay = 300,
        private readonly int $minimumPickup = 600,
    ) {
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

    public function retrieve(): array|false
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

    public function remove(int|string $jobId): bool
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
