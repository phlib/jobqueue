<?php

namespace Phlib\JobQueue;

interface JobQueueInterface
{
    /**
     * @param mixed $data
     * @param int|string|null $id
     */
    public function createJob(string $queue, $data, $id, int $delay, int $priority, int $ttr): JobInterface;

    /**
     * @return $this
     */
    public function put(JobInterface $job);

    /**
     * @return JobInterface
     */
    public function retrieve(string $queue);

    /**
     * @return mixed
     */
    public function markAsComplete(JobInterface $job);

    /**
     * @return mixed
     */
    public function markAsIncomplete(JobInterface $job);

    /**
     * @return mixed
     */
    public function markAsError(JobInterface $job);
}
