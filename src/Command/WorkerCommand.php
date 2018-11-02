<?php

namespace Phlib\JobQueue\Command;

use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\Exception as LibraryException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
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
    protected $queue;

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

        while ($this->continue && ($job = $this->retrieve($jobQueue, $logger)) instanceof JobInterface) {
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
            } catch (LibraryException $e) {
                $this->logException($logger, "JobQueue library exception occurred while working on job {$job->getId()}", $e, $job);
                throw $e;
            } catch (\Exception $e) {
                $jobQueue->markAsError($job);
                $this->logException($logger, "Job {$job->getId()} marked as error", $e, $job);

                if ($this->exitOnException) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param JobQueueInterface $jobQueue
     * @param LoggerInterface $logger
     * @return JobInterface|null
     * @throws \Exception
     */
    private function retrieve(JobQueueInterface $jobQueue, LoggerInterface $logger)
    {
        try {
            return $jobQueue->retrieve($this->queue);
        } catch (\Exception $e) {
            $this->logException($logger, "Failed to retrieve job due to error '{$e->getMessage()}'", $e);
            $this->continue = false;
            throw $e;
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

    private function logException(LoggerInterface $logger, $message, \Exception $exception, $job = null)
    {
        $context = [
            'q_class'    => get_class($this->getJobQueue()),
            'e_message'  => $exception->getMessage(),
            'e_file'     => $exception->getFile(),
            'e_line'     => $exception->getLine(),
            'e_trace'    => $exception->getTraceAsString()
        ];
        if ($job instanceof JobInterface) {
            $context['j_id']       = $job->getId();
            $context['j_delay']    = $job->getDelay();
            $context['j_priority'] = $job->getPriority();
            $context['j_ttr']      = $job->getTtr();
        }
        $logger->error($message, $context);
    }
}
