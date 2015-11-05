<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\BeanstalkInterface;
use Phlib\Beanstalk\Beanstalk;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\SchedulerInterface;

class Scheduled implements JobQueueInterface
{
    /**
     * @var BeanstalkInterface
     */
    private $beanstalk;

    /**
     * @var SchedulerInterface
     */
    private $scheduler;

    /**
     * @param BeanstalkInterface $beanstalk
     * @param SchedulerInterface $scheduler
     */
    public function __construct(BeanstalkInterface $beanstalk, SchedulerInterface $scheduler)
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
            'priority' => Beanstalk::DEFAULT_PRIORITY,
            'delay'    => Beanstalk::DEFAULT_DELAY,
            'ttr'      => Beanstalk::DEFAULT_TTR
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
        $this->beanstalk->ignore('default');
        $this->beanstalk->watch($queue);
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
