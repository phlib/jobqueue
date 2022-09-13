<?php

declare(strict_types=1);

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

    public function __construct(JobQueueInterface $jobQueue, SchedulerInterface $scheduler)
    {
        $this->jobQueue = $jobQueue;
        $this->scheduler = $scheduler;
    }

    public function getJobQueue(): JobQueueInterface
    {
        return $this->jobQueue;
    }

    public function getScheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }
}
