<?php

namespace Phlib\JobQueue\Console;

use Phlib\JobQueue\BeanstalkDb;
use Phlib\JobQueue\Scheduler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phlib\Console\Command\DaemonCommand;
use Phlib\Db\Adapter as DbAdapter;
use Phlib\Beanstalk\Beanstalk;
use Phlib\Beanstalk\Factory;
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
     * @var Scheduler
     */
    protected $scheduler;

    protected function initialize()
    {
        $this->db = new DbAdapter(['host' => '127.0.0.1', 'dbname' => 'test']);
        $this->beanstalk = (new Factory())->create('localhost');
        $this->processingDelay = 5;
        $this->scheduler = new Scheduler($this->db, 60, 120);
    }

    protected function configure()
    {
        $this->setName('monitor')
            ->setDescription('Monitor the schedule for pending jobs.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        while ($job = $this->scheduler->retrieve()) {
            $output->writeln("Job {$job['id']} added.");
            $this->beanstalk->useTube($job['queue'])
                ->put($job['data'], $job['priority'], $job['delay'], $job['ttr']);
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
