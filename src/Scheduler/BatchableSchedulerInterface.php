<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

/**
 * Interface BatchableSchedulerInterface
 *
 * @package Phlib\JobQueue
 */
interface BatchableSchedulerInterface extends SchedulerInterface
{
    /**
     * @return array|false
     */
    public function retrieveBatch();

    public function removeBatch(array $jobId): bool;
}
