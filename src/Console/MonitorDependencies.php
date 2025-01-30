<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Console;

use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

/**
 * @package Phlib\JobQueue
 */
class MonitorDependencies
{
    public function __construct(
        protected JobQueueInterface $jobQueue,
        protected SchedulerInterface $scheduler,
    ) {
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
