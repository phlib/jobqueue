<?php

namespace Phlib\JobQueue\Command;

use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends DaemonCommand
{
    /**
     * @var string
     */
    protected $queue = null;

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->queue === null) {
            throw new InvalidArgumentException("Missing require property 'queue' to be set on Worker Command.");
        }

        $jobQueue = $this->getJobQueue();
        while ($job = $jobQueue->retrieve($this->queue)) {
            try {
                $code = $this->work($job, $input, $output);
                if ($code != 0) {
                    throw new LogicException("Non zero exit code $code.");
                }
                $jobQueue->markAsComplete($job);
            } catch (\Exception $e) {
                $jobQueue->markAsError($job);
            }
        }
    }

    /**
     * Work on the current job.
     *
     * @param JobInterface  $job  A JobInterface instance
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws LogicException When this abstract method is not implemented
     * @see setCode()
     */
    protected function work(JobInterface $job, InputInterface $input, OutputInterface $output)
    {
        throw new LogicException('You must override the work() method in the concrete command class.');
    }

    /**
     * @return JobQueueInterface
     * @throws LogicException
     */
    protected function getJobQueue()
    {
        throw new LogicException('You must override the getJobQueue() method in the concrete command class.');
    }
}
