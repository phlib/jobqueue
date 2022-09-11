<?php

namespace Phlib\JobQueue\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;

/**
 * Class DbScheduler
 * @package Phlib\JobQueue
 */
class DbScheduler implements SchedulerInterface
{
    /**
     * @var QuotableAdapterInterface
     */
    protected $adapter;

    /**
     * @var integer
     */
    protected $maximumDelay;

    /**
     * @var integer
     */
    private $minimumPickup;

    /**
     * @param Adapter $adapter
     * @param integer $maximumDelay
     * @param integer $minimumPickup
     */
    public function __construct(Adapter $adapter, $maximumDelay = 300, $minimumPickup = 600)
    {
        $this->adapter = $adapter;
        $this->maximumDelay = $maximumDelay;
        $this->minimumPickup = $minimumPickup;
    }

    /**
     * @inheritdoc
     */
    public function shouldBeScheduled($delay)
    {
        return $delay > $this->maximumDelay;
    }

    /**
     * @inheritdoc
     */
    public function store(JobInterface $job)
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
     * @inheritdoc
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
        if ($stmt->rowCount() == 0) {
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
     * @inheritdoc
     */
    public function remove($jobId)
    {
        $table = $this->adapter->quote()->identifier('scheduled_queue');
        $sql = "DELETE FROM {$table} WHERE id = ?";

        return (bool)$this->adapter
            ->query($sql, [$jobId])
            ->rowCount();
    }

    /**
     * @param array $data
     * @return int
     */
    protected function insert(array $data)
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
