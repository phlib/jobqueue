<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Console;

use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\JobQueue\BatchableJobQueueInterface;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\BatchableSchedulerInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class MonitorCommand extends DaemonCommand
{
    protected SchedulerInterface $scheduler;

    protected JobQueueInterface $jobQueue;

    protected string $logFile;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $dependencies = $this->getHelper('configuration')
            ->fetch();

        if (!$dependencies instanceof MonitorDependencies) {
            throw new InvalidArgumentException('Expected dependencies could not be determined.');
        }

        $this->jobQueue = $dependencies->getJobQueue();
        $this->scheduler = $dependencies->getScheduler();
    }

    protected function configure(): void
    {
        $this->setName('monitor')
            ->setDescription('Monitor the schedule for pending jobs.')
            ->addOption('log', 'l', InputOption::VALUE_REQUIRED, "A file to send log out to. If no path is specified then it's disabled.");
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logFile = $input->getOption('log');
        if (!empty($logFile)) {
            $this->logFile = $logFile;
        }

        if ($this->scheduler instanceof BatchableSchedulerInterface) {
            while ($jobsData = $this->scheduler->retrieveBatch()) {
                $jobs = [];
                foreach ($jobsData as $jobData) {
                    $output->writeln("Job {$jobData['id']} added.");
                    $jobs[] = $this->createJob($jobData);
                }
                $this->putJobBatch($jobs);
                $this->scheduler->removeBatch(array_column($jobsData, 'id'));
            }
        } else {
            while ($jobData = $this->scheduler->retrieve()) {
                $output->writeln("Job {$jobData['id']} added.");
                $this->jobQueue->put($this->createJob($jobData));
                $this->scheduler->remove($jobData['id']);
            }
        }

        return 0;
    }

    protected function createJob(array $schedulerJob): JobInterface
    {
        return $this->jobQueue->createJob(
            $schedulerJob['queue'],
            $schedulerJob['data'],
            null,
            $schedulerJob['delay'],
            $schedulerJob['priority'],
            $schedulerJob['ttr']
        );
    }

    protected function putJobBatch(array $jobs): void
    {
        if ($this->jobQueue instanceof BatchableJobQueueInterface) {
            $this->jobQueue->putBatch($jobs);
            return;
        }

        foreach ($jobs as $job) {
            $this->jobQueue->put($job);
        }
    }

    protected function createChildOutput(): OutputInterface
    {
        if (empty($this->logFile)) {
            return parent::createChildOutput();
        }
        return new StreamOutput(fopen($this->logFile, 'ab'));
    }
}
