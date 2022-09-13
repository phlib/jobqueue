<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

use Phlib\JobQueue\JobInterface;

/**
 * Interface SchedulerInterface
 * @package Phlib\JobQueue
 */
interface SchedulerInterface
{
    public function shouldBeScheduled(int $delay): bool;

    public function store(JobInterface $job): bool;

    /**
     * @return array|false
     */
    public function retrieve();

    /**
     * @param int|string $jobId
     */
    public function remove($jobId): bool;
}
