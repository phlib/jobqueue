<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

/**
 * @package Phlib\JobQueue
 */
interface BatchableSchedulerInterface extends SchedulerInterface
{
    public function retrieveBatch(): array|false;

    public function removeBatch(array $jobId): bool;
}
