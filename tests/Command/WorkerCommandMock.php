<?php

namespace Phlib\JobQueue\Command;

use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommandMock extends WorkerCommand
{
    /**
     * @var string
     */
    protected $queue = 'mockQueue';

    /**
     * @var JobQueueInterface
     */
    protected $jobQueue;

    /**
     * @var bool
     */
    protected $runOnce;

    /**
     * @var bool
     */
    protected $exitOnException = true;

    public function __construct(JobQueueInterface $jobQueue, $runOnce = true)
    {
        parent::__construct();
        $this->jobQueue = $jobQueue;
        $this->runOnce = $runOnce;
    }

    protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
    {
        if ($this->runOnce) {
            $this->continue = false;
        }
        return 0;
    }

    public function shouldContinue(bool $state): void
    {
        $this->continue = $state;
    }

    protected function getJobQueue(): JobQueueInterface
    {
        return $this->jobQueue;
    }
}
