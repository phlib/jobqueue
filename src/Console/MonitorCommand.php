<?php

namespace Phlib\JobQueue\Console;

use Phlib\JobQueue\Beanstalk\Job;
use Phlib\JobQueue\Beanstalk\JobQueue;
use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\ConsoleProcess\Command\DaemonCommand;
use Phlib\Db\Adapter as DbAdapter;
use Phlib\Beanstalk\Beanstalk;
use Phlib\Beanstalk\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class MonitorCommand extends DaemonCommand
{
    /**
     * @var DbAdapter
     */
    protected $db;

    /**
     * @var Beanstalk
     */
    protected $beanstalk;

    /**
     * @var DbScheduler
     */
    protected $scheduler;

    /**
     * @var JobQueue
     */
    protected $jobQueue;

    protected function initialize()
    {
        $this->db = new DbAdapter(['host' => '127.0.0.1', 'dbname' => 'test']);
        $this->beanstalk = (new Factory())->create('localhost');
        $this->processingDelay = 5;
        $this->scheduler = new DbScheduler($this->db, 60, 120);
        $this->jobQueue = new JobQueue($this->beanstalk, $this->scheduler);
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
        while ($job = $this->scheduler->retrieve()) {
            $output->writeln("Job {$job['id']} added.");
            $this->jobQueue->put(new Job($job['queue'], $job['data'], null, $job['delay'], $job['priority'], $job['ttr']));
//            $this->beanstalk->useTube($job['queue'])
//                ->put($job['data'], $job['priority'], $job['delay'], $job['ttr']);
            $this->scheduler->remove($job);
        }
    }

    /**
     * @return ConsoleOutputInterface
     */
    protected function createChildOutput()
    {
        return new StreamOutput(fopen(getcwd() . '/scheduler.log', 'a'));
    }
}
