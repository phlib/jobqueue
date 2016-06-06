<?php

namespace Phlib\JobQueue\Command;

use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends DaemonCommand implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    protected $queue = null;

    /**
     * @var bool
     */
    protected $exitOnException = false;

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->queue === null) {
            throw new InvalidArgumentException("Missing require property 'queue' to be set on Worker Command.");
        }

        $jobQueue = $this->getJobQueue();
        $logger   = $this->getLogger($output);

        while ($job = $jobQueue->retrieve($this->queue)) {
            try {
                $logger->info("Retrieved job {$job->getId()} for {$this->queue}");
                $workStarted = microtime(true);
                $code        = $this->work($job, $input, $output);
                $timeTaken   = microtime(true) - $workStarted;

                $debugCode = var_export($code, true);
                $logger->debug("Work completed on job {$job->getId()} with return code '{$debugCode}' taking {$timeTaken}");

                if ($code != 0) {
                    throw new LogicException("Non zero exit code $code.");
                }
                $jobQueue->markAsComplete($job);
                $logger->info("Job {$job->getId()} completed");
            } catch (\Exception $e) {
                $jobQueue->markAsError($job);
                $logger->error("Job {$job->getId()} marked as error", [
                    'j_id'       => $job->getId(),
                    'j_delay'    => $job->getDelay(),
                    'j_priority' => $job->getPriority(),
                    'j_ttr'      => $job->getTtr(),
                    'e_message'  => $e->getMessage(),
                    'e_file'     => $e->getFile(),
                    'e_line'     => $e->getLine(),
                    'e_trace'    => $e->getTraceAsString()
                ]);

                if ($this->exitOnException) {
                    throw $e;
                }
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

    /**
     * @param OutputInterface $output An OutputInterface instance
     * @return \Psr\Log\LoggerInterface|NullLogger
     */
    protected function getLogger($output)
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }
}
