<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\Connection\ConnectionInterface;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

class JobQueue implements JobQueueInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $beanstalk;

    /**
     * @var SchedulerInterface
     */
    protected $scheduler;

    /**
     * @var int|null Seconds
     */
    protected $retrieveTimeout = 5;

    public function __construct(ConnectionInterface $beanstalk, SchedulerInterface $scheduler)
    {
        $this->beanstalk = $beanstalk;
        $this->scheduler = $scheduler;
    }

    /**
     * @param mixed $data
     * @param int|string|null $id
     */
    public function createJob(string $queue, $data, $id, int $delay, int $priority, int $ttr): JobInterface
    {
        return new Job($queue, $data, $id, $delay, $priority, $ttr);
    }

    /**
     * @return mixed
     */
    public function put(JobInterface $job)
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            return $this->scheduler->store($job);
        }
        return $this->beanstalk
            ->useTube($job->getQueue())
            ->put(JobFactory::serializeBody($job), $job->getPriority(), $job->getDelay(), $job->getTtr());
    }

    public function getRetrieveTimeout(): ?int
    {
        return $this->retrieveTimeout;
    }

    public function setRetrieveTimeout(?int $value): self
    {
        $options = [
            'options' => [
                'min_range' => 0,
            ],
        ];
        if ($value !== null && filter_var($value, FILTER_VALIDATE_INT, $options) === false) {
            throw new InvalidArgumentException('Specified retrieve timeout value is not valid.');
        }
        $this->retrieveTimeout = $value;
        return $this;
    }

    /**
     * @return JobInterface|false
     */
    public function retrieve(string $queue)
    {
        $this->beanstalk->watch($queue);
        $this->beanstalk->ignore('default');

        $data = $this->beanstalk->reserve($this->retrieveTimeout);
        if ($data === false) {
            return false;
        }
        return JobFactory::createFromRaw($data);
    }

    public function markAsComplete(JobInterface $job): ConnectionInterface
    {
        return $this->beanstalk->delete($job->getId());
    }

    /**
     * @return mixed
     */
    public function markAsIncomplete(JobInterface $job)
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            if ($job->getId() !== null) {
                $this->beanstalk->delete($job->getId());
            }
            return $this->scheduler->store($job);
        }
        return $this->beanstalk
            ->useTube($job->getQueue())
            ->release($job->getId(), $job->getPriority(), $job->getDelay());
    }

    public function markAsError(JobInterface $job): ConnectionInterface
    {
        return $this->beanstalk->bury($job->getId());
    }
}
