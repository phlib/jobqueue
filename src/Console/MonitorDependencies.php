<?php

namespace Phlib\JobQueue\Console;

use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

/**
 * Class MonitorDependencies
 * @package Phlib\JobQueue\Console
 */
class MonitorDependencies
{
    /**
     * @var JobQueueInterface
     */
    protected $jobQueue;

    /**
     * @var SchedulerInterface
     */
    protected $scheduler;

    /**
     * @param JobQueueInterface $jobQueue
     * @param SchedulerInterface $scheduler
     */
    public function __construct(JobQueueInterface $jobQueue, SchedulerInterface $scheduler)
    {
        $this->jobQueue = $jobQueue;
        $this->scheduler = $scheduler;
    }

    /**
     * @return JobQueueInterface
     */
    public function getJobQueue()
    {
        return $this->jobQueue;
    }

    /**
     * @return SchedulerInterface
     */
    public function getScheduler()
    {
        return $this->scheduler;
    }
}
