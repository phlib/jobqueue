<?php

namespace Phlib\JobQueue\Scheduler;

use Phlib\Beanstalk\Connection;
use Phlib\Db\Adapter as DbAdapter;
use Phlib\JobQueue\JobInterface;

/**
 * Class Scheduler
 * @package Phlib\JobQueue
 */
class DbScheduler implements SchedulerInterface
{
    /**
     * @var DbAdapter
     */
    protected $dbAdapter;

    /**
     * @var integer
     */
    protected $maximumDelay;

    /**
     * @var integer
     */
    private $minimumPickup;

    /**
     * @param DbAdapter $dbAdapter
     * @param integer $maximumDelay
     * @param integer $minimumPickup
     */
    public function __construct(DbAdapter $dbAdapter, $maximumDelay = 300, $minimumPickup = 600)
    {
        $this->dbAdapter     = $dbAdapter;
        $this->maximumDelay  = $maximumDelay;
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
        return (boolean)$this->dbAdapter->insert(
            'scheduled_queue',
            [
                'tube'         => $job->getQueue(),
                'data'         => serialize($job->getBody()),
                'scheduled_ts' => $job->getDatetimeDelay()->format($dbTimestampFormat),
                'priority'     => $job->getPriority(),
                'ttr'          => $job->getTtr(),
                'create_ts'    => date($dbTimestampFormat)
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function retrieve()
    {
        $sql = "
            UPDATE scheduled_queue SET
                picked_by = CONNECTION_ID(),
                picked_ts = NOW()
            WHERE
                scheduled_ts <= CURRENT_TIMESTAMP + INTERVAL {$this->minimumPickup} SECOND AND
                picked_by IS NULL
            ORDER BY
                scheduled_ts DESC
            LIMIT 1";
        $stmt = $this->dbAdapter->query($sql);
        if ($stmt->rowCount() == 0) {
            return false; // no jobs
        }

        $sql = "SELECT * FROM `scheduled_queue` WHERE picked_by = CONNECTION_ID() LIMIT 1";
        $row = $this->dbAdapter->query($sql)->fetch(\PDO::FETCH_ASSOC);

        $scheduledTime = strtotime($row['scheduled_ts']);
        $delay         = $scheduledTime - time();
        if ($delay < 0) {
            $delay = 0;
        }

        return [
            'id'       => $row['id'],
            'queue'    => $row['tube'],
            'data'     => unserialize($row['data']),
            'delay'    => $delay,
            'priority' => $row['priority'],
            'ttr'      => $row['ttr']
        ];
    }

    /**
     * @inheritdoc
     */
    public function remove(array $job)
    {
        if (!isset($job['id'])) {
            throw new \InvalidArgumentException();
        }
        return (boolean)$this->dbAdapter->delete('scheduled_queue', '`id` = ?', [$job['id']]);
    }
}
