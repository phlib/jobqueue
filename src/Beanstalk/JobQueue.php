<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\Connection\ConnectionInterface;
use Phlib\Beanstalk\Connection;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\SchedulerInterface;

class JobQueue implements JobQueueInterface
{
    /**
     * @var ConnectionInterface
     */
    private $beanstalk;

    /**
     * @var SchedulerInterface
     */
    private $scheduler;

    /**
     * @param ConnectionInterface $beanstalk
     * @param SchedulerInterface $scheduler
     */
    public function __construct(ConnectionInterface $beanstalk, SchedulerInterface $scheduler)
    {
        $this->beanstalk = $beanstalk;
        $this->scheduler = $scheduler;
    }

    /**
     * @inheritdoc
     */
    public function put($queue, $data, array $options)
    {
        $options = $options + [
            'priority' => Connection::DEFAULT_PRIORITY,
            'delay'    => Connection::DEFAULT_DELAY,
            'ttr'      => Connection::DEFAULT_TTR
        ];

        if ($this->scheduler->shouldBeScheduled($options['delay'])) {
            return $this->scheduler->store($queue, $data, $options);
        } else {
            return $this->beanstalk
                ->useTube($queue)
                ->put($data, $options['priority'], $options['delay'], $options['ttr']);
        }
    }

    /**
     * @inheritdoc
     */
    public function retrieve($queue)
    {
        $this->beanstalk->watch($queue);
        $this->beanstalk->ignore('default');
        $jobData = $this->beanstalk->reserve();
        if ($jobData === false) {
            return false;
        }
        return new Job($jobData);
    }

    /**
     * @inheritdoc
     */
    public function markAsComplete(JobInterface $job)
    {
        return $this->beanstalk->delete($job->getId());
    }

    /**
     * @inheritdoc
     */
    public function markAsIncomplete(JobInterface $job)
    {
        return $this->beanstalk->release($job->getId());
    }

    /**
     * @inheritdoc
     */
    public function markAsError(JobInterface $job)
    {
        return $this->beanstalk->bury($job->getId());
    }
}
