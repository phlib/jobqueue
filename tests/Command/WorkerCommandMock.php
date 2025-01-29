<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Command;

use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Phlib\JobQueue
 */
class WorkerCommandMock extends WorkerCommand
{
    protected string $queue = 'mockQueue';

    protected bool $exitOnException = true;

    public function __construct(
        private readonly JobQueueInterface $jobQueue,
        private readonly bool $runOnce = true,
    ) {
        parent::__construct();
    }

    protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
    {
        if ($this->runOnce) {
            $this->queueContinue = false;
            $this->shutdown();
        }
        return 0;
    }

    protected function getJobQueue(): JobQueueInterface
    {
        return $this->jobQueue;
    }
}
