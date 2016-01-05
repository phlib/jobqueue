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
    protected $beanstalk;

    /**
     * @var SchedulerInterface
     */
    protected $scheduler;

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
                ->put(JobFactory::serializeBody($job), $job->getPriority(), $job->getDelay(), $job->getTtr());
        }
    }

    /**
     * @inheritdoc
     */
    public function retrieve($queue)
    {
        $this->beanstalk->watch($queue);
        $this->beanstalk->ignore('default');

        $data = $this->beanstalk->reserve();
        if ($data === false) {
            return false;
        }
        return JobFactory::createFromRaw($data);
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
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            if ($job->getId() !== null) {
                $this->beanstalk->delete($job->getId());
            }
            return $this->scheduler->store($job);
        } else {
            return $this->beanstalk
                ->useTube($job->getQueue())
                ->release($job->getId(), $job->getPriority(), $job->getDelay());
        }
    }

    /**
     * @inheritdoc
     */
    public function markAsError(JobInterface $job)
    {
        return $this->beanstalk->bury($job->getId());
    }
}
