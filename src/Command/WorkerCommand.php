<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Command;

use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\JobQueue\Exception\Exception as LibraryException;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Phlib\JobQueue
 */
class WorkerCommand extends DaemonCommand implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Delay between processing jobs, in μs.
     * Defaults to 0 to prevent a delay beween jobs.
     * @see \Phlib\ConsoleProcess\Command\BackgroundCommand::$processingDelay
     */
    protected int $processingDelay = 0;

    /**
     * Delay between checking for jobs when the queue is empty, in μs.
     */
    protected int $emptyQueueDelay = 500000;

    protected string $queue;

    protected bool $exitOnException = false;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->queue)) {
            throw new InvalidArgumentException("Missing required property 'queue' to be set on Worker Command.");
        }

        parent::initialize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobQueue = $this->getJobQueue();
        $logger = $this->getLogger($output);
        $job = $this->retrieve($jobQueue, $logger);

        if (!$job instanceof JobInterface) {
            usleep($this->emptyQueueDelay);
            return 0;
        }

        try {
            $logger->info("Retrieved job {$job->getId()} for {$this->queue}");
            $workStarted = microtime(true);
            $code = $this->work($job, $input, $output);
            $timeTaken = microtime(true) - $workStarted;

            $debugCode = var_export($code, true);
            $logger->debug("Work completed on job {$job->getId()} with return code '{$debugCode}' taking {$timeTaken}");

            if ($code !== 0) {
                throw new LogicException("Non zero exit code {$code}.");
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

        return 0;
    }

    private function retrieve(JobQueueInterface $jobQueue, LoggerInterface $logger): ?JobInterface
    {
        try {
            return $jobQueue->retrieve($this->queue);
        } catch (\Exception $e) {
            $this->logException($logger, "Failed to retrieve job due to error '{$e->getMessage()}'", $e);
            $this->shutdown();
            throw $e;
        }
    }

    protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
    {
        throw new LogicException('You must override the work() method in the concrete command class.');
    }

    protected function getJobQueue(): JobQueueInterface
    {
        throw new LogicException('You must override the getJobQueue() method in the concrete command class.');
    }

    protected function getLogger(OutputInterface $output): LoggerInterface
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }

    private function logException(LoggerInterface $logger, string $message, \Exception $exception, $job = null): void
    {
        $context = [
            'qClass' => $this->getJobQueue()::class,
            'xMessage' => $exception->getMessage(),
            'xFile' => $exception->getFile(),
            'xLine' => $exception->getLine(),
            'xTrace' => $exception->getTraceAsString(),
        ];
        if ($job instanceof JobInterface) {
            $context['jId'] = $job->getId();
            $context['jDelay'] = $job->getDelay();
            $context['jPriority'] = $job->getPriority();
            $context['jTtr'] = $job->getTtr();
        }
        $logger->error($message, $context);
    }
}
