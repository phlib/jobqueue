<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Scheduler;

use Phlib\JobQueue\JobInterface;

/**
 * @package Phlib\JobQueue
 */
interface SchedulerInterface
{
    public function shouldBeScheduled(int $delay): bool;

    public function store(JobInterface $job): bool;

    public function retrieve(): array|false;

    public function remove(int|string $jobId): bool;
}
