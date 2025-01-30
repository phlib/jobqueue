<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\ConnectionInterface;
use Phlib\Beanstalk\Exception\NotFoundException as BeanstalkNotFoundException;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

/**
 * @package Phlib\JobQueue
 */
class JobQueue implements JobQueueInterface
{
    protected ?int $retrieveTimeout = 5;

    public function __construct(
        protected ConnectionInterface $beanstalk,
        protected SchedulerInterface $scheduler,
    ) {
    }

    public function createJob(
        string $queue,
        mixed $data,
        int|string|null $id,
        int $delay,
        int $priority,
        int $ttr,
    ): JobInterface {
        return new Job($queue, $data, $id, $delay, $priority, $ttr);
    }

    public function put(JobInterface $job): self
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            $this->scheduler->store($job);
            return $this;
        }

        $this->beanstalk->useTube($job->getQueue());
        $this->beanstalk->put(JobFactory::serializeBody($job), $job->getPriority(), $job->getDelay(), $job->getTtr());
        return $this;
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

    public function retrieve(string $queue): ?JobInterface
    {
        $this->beanstalk->watch($queue);
        $this->beanstalk->ignore('default');

        try {
            $data = $this->beanstalk->reserve($this->retrieveTimeout);
        } catch (BeanstalkNotFoundException $e) {
            if ($e->getCode() !== BeanstalkNotFoundException::RESERVE_NO_JOBS_AVAILABLE_CODE) {
                throw $e;
            }
            return null;
        }

        return JobFactory::createFromRaw($data);
    }

    public function markAsComplete(JobInterface $job): self
    {
        $this->beanstalk->delete($job->getId());
        return $this;
    }

    public function markAsIncomplete(JobInterface $job): self
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            if ($job->getId() !== null) {
                $this->beanstalk->delete($job->getId());
            }
            $this->scheduler->store($job);
            return $this;
        }

        $this->beanstalk->useTube($job->getQueue());
        $this->beanstalk->release($job->getId(), $job->getPriority(), $job->getDelay());
        return $this;
    }

    public function markAsError(JobInterface $job): self
    {
        $this->beanstalk->bury($job->getId());
        return $this;
    }
}
