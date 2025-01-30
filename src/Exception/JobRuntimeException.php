<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Exception;

use Phlib\JobQueue\JobInterface;

/**
 * @package Phlib\JobQueue
 */
class JobRuntimeException extends RuntimeException
{
    public function __construct(
        protected JobInterface $job,
        string $message,
        int $code = 0,
        \Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function hasJob(): bool
    {
        return $this->job !== null;
    }

    public function getJob(): JobInterface
    {
        return $this->job;
    }
}
