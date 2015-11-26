<?php

namespace Phlib\JobQueue\Scheduler;

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
     * @param string $queue
     * @param mixed $data
     * @param array $options
     * @return boolean
     */
    public function store($queue, $data, array $options);

    /**
     * @return array|false
     */
    public function retrieve();
}
