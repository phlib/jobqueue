<?php

namespace Phlib\JobQueue;

interface JobQueueInterface
{
    /**
     * @param string $queue
     * @param mixed $data
     * @param int|string|null $id
     * @param int $delay
     * @param int $priority
     * @param int $ttr
     * @return JobInterface
     */
    public function createJob($queue, $data, $id, $delay, $priority, $ttr);

    /**
     * @param JobInterface $job
     * @return $this
     */
    public function put(JobInterface $job);

    /**
     * @param string $queue
     * @return JobInterface
     */
    public function retrieve($queue);

    /**
     * @param JobInterface $job
     * @return mixed
     */
    public function markAsComplete(JobInterface $job);

    /**
     * @param JobInterface $job
     */
    public function markAsIncomplete(JobInterface $job);

    /**
     * @param JobInterface $job
     * @return mixed
     */
    public function markAsError(JobInterface $job);
}
