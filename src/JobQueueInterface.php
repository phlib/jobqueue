<?php

declare(strict_types=1);

namespace Phlib\JobQueue;

/**
 * @package Phlib\JobQueue
 */
interface JobQueueInterface
{
    public function createJob(
        string $queue,
        mixed $data,
        int|string|null $id,
        int $delay,
        int $priority,
        int $ttr,
    ): JobInterface;

    /**
     * @todo php74 return type self
     * @return $this
     */
    public function put(JobInterface $job);

    public function retrieve(string $queue): ?JobInterface;

    /**
     * @todo php74 return type self
     * @return $this
     */
    public function markAsComplete(JobInterface $job);

    /**
     * @todo php74 return type self
     * @return $this
     */
    public function markAsIncomplete(JobInterface $job);

    /**
     * @todo php74 return type self
     * @return $this
     */
    public function markAsError(JobInterface $job);
}
