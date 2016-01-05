<?php

namespace Phlib\JobQueue\Exception;

use Phlib\JobQueue\JobInterface;

class JobRuntimeException extends RuntimeException
{
    /**
     * @var JobInterface
     */
    protected $job = null;

    /**
     * JobRuntimeException constructor.
     * @param JobInterface $job
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($job, $message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        if ($job instanceof JobInterface) {
            $this->job = $job;
        }
    }

    /**
     * @return bool
     */
    public function hasJob()
    {
        return $this->job !== null;
    }

    /**
     * @return JobInterface|null
     */
    public function getJob()
    {
        return $this->job;
    }
}
