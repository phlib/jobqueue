<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\Connection\ConnectionInterface;
use Phlib\Beanstalk\Connection;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

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
    public function put(JobInterface $job)
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            return $this->scheduler->store($job);
        } else {
            return $this->beanstalk
                ->useTube($job->getQueue())
                ->put(serialize($job->toSpecification()), $job->getPriority(), $job->getDelay(), $job->getTtr());
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
