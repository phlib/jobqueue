<?php

namespace Phlib\JobQueue\Console;

use Phlib\JobQueue\Scheduler\SchedulerInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\ConsoleProcess\Command\DaemonCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class MonitorCommand extends DaemonCommand
{
    /**
     * @var SchedulerInterface
     */
    protected $scheduler;

    /**
     * @var JobQueueInterface
     */
    protected $jobQueue;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $dependencies = $this->getHelper('configuration')
            ->fetch();

        if (!$dependencies instanceof MonitorDependencies) {
            throw new InvalidArgumentException('Expected dependencies could not be determined.');
        }

        $this->jobQueue = $dependencies->getJobQueue();
        $this->scheduler = $dependencies->getScheduler();
    }

    protected function configure()
    {
        $this->setName('monitor')
            ->setDescription('Monitor the schedule for pending jobs.');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        while ($jobData = $this->scheduler->retrieve()) {
            $output->writeln("Job {$jobData['id']} added.");
            $this->jobQueue->put($this->createJob($jobData));
            $this->scheduler->remove($jobData['id']);
        }
    }

    protected function createJob(array $schedulerJob)
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

    /**
     * @return ConsoleOutputInterface
     */
    protected function createChildOutput()
    {
        return new StreamOutput(fopen(getcwd() . '/jobqueue-monitor.log', 'a'));
    }
}
