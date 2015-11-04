<?php

namespace Phlib\JobQueue;

interface JobQueueInterface
{
    /**
     * @param string $queue
     * @param string $data
     * @param array $options
     * @return $this
     */
    public function put($queue, $data, array $options);

    /**
     * @param string $queue
     * @return Job
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
