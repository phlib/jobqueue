<?php

namespace Phlib\JobQueue\Scheduler;

use Phlib\JobQueue\JobInterface;

/**
 * Interface SchedulerInterface
 * @package Phlib\JobQueue
 */
interface SchedulerInterface
{
    /**
     * @param integer $delay
     * @return boolean
     */
    public function shouldBeScheduled($delay);

    /**
     * @param JobInterface $job
     * @return boolean
     */
    public function store(JobInterface $job);

    /**
     * @return array|false
     */
    public function retrieve();

    /**
     * @param int|string $jobId
     * @return bool
     */
    public function remove($jobId);
}
