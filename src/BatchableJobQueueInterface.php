<?php

declare(strict_types=1);

namespace Phlib\JobQueue;

interface BatchableJobQueueInterface extends JobQueueInterface
{
    /**
     * @param JobInterface[] $jobs
     */
    public function putBatch(array $jobs): self;
}
