<?php

declare(strict_types=1);

namespace Phlib\JobQueue\AwsSqs;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Phlib\JobQueue\BatchableJobQueueInterface;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\RuntimeException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

class JobQueue implements BatchableJobQueueInterface
{
    private SqsClient $client;

    private SchedulerInterface $scheduler;

    private int $retrieveTimeout = 10;

    private array $queues = [];

    /**
     * @var string
     */
    private $queuePrefix;

    public function __construct(SqsClient $client, SchedulerInterface $scheduler, $queuePrefix = '')
    {
        $this->client = $client;
        $this->scheduler = $scheduler;
        $this->queuePrefix = $queuePrefix;
    }

    /**
     * @param mixed $data
     * @param int|string|null $id
     */
    public function createJob(
        string $queue,
        $data,
        $id = null,
        $delay = Job::DEFAULT_DELAY,
        $priority = Job::DEFAULT_PRIORITY,
        $ttr = Job::DEFAULT_TTR
    ): JobInterface {
        return new Job($queue, $data, $id, $delay, $priority, $ttr);
    }

    public function put(JobInterface $job): self
    {
        try {
            if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
                $this->scheduler->store($job);
                return $this;
            }

            $this->client->sendMessage([
                'QueueUrl' => $this->getQueueUrlWithPrefix($job->getQueue()),
                'DelaySeconds' => $job->getDelay(),
                'MessageBody' => JobFactory::serializeBody($job),
            ]);
            return $this;
        } catch (SqsException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function putBatch(array $jobs): self
    {
        try {
            $queues = [];

            foreach ($jobs as $key => $job) {
                if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
                    $this->scheduler->store($job);
                    continue;
                }

                $queues[$job->getQueue()][] = [
                    'Id' => (string) $key,
                    'DelaySeconds' => $job->getDelay(),
                    'MessageBody' => JobFactory::serializeBody($job),
                ];
            }

            foreach ($queues as $queue => $jobs) {
                foreach (array_chunk($jobs, 10) as $batch) {
                    $this->client->sendMessageBatch([
                        'QueueUrl' => $this->getQueueUrlWithPrefix($queue),
                        'Entries' => $batch,
                    ]);
                }
            }

            return $this;
        } catch (SqsException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function retrieve(string $queue): ?JobInterface
    {
        try {
            $result = $this->client->receiveMessage([
                'QueueUrl' => $this->getQueueUrlWithPrefix($queue),
                'WaitTimeSeconds' => $this->retrieveTimeout,
                'MaxNumberOfMessages' => 1,
            ]);

            if (!isset($result['Messages'])) {
                return null;
            }

            return JobFactory::createFromRaw($result['Messages'][0]);
        } catch (SqsException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function markAsComplete(JobInterface $job): self
    {
        try {
            $this->client->deleteMessage([
                'QueueUrl' => $this->getQueueUrlWithPrefix($job->getQueue()),
                'ReceiptHandle' => $job->getId(),
            ]);
        } catch (SqsException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $this;
    }

    public function markAsIncomplete(JobInterface $job): self
    {
        $this->markAsComplete($job);
        $job->setDelay(0);
        $this->put($job);

        return $this;
    }

    public function markAsError(JobInterface $job): self
    {
        try {
            $queue = $job->getQueue();
            $deadletter = $this->determineDeadletterQueue($queue);

            $this->client->deleteMessage([
                'QueueUrl' => $this->getQueueUrlWithPrefix($queue),
                'ReceiptHandle' => $job->getId(),
            ]);

            $job->setDelay(0);
            $this->client->sendMessage([
                'QueueUrl' => $this->getQueueUrl($deadletter),
                'DelaySeconds' => 0,
                'MessageBody' => JobFactory::serializeBody($job),
            ]);
        } catch (SqsException $exception) {
            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $this;
    }

    private function getQueueUrlWithPrefix($name)
    {
        $name = $this->queuePrefix . $name;
        return $this->getQueueUrl($name);
    }

    private function getQueueUrl($name)
    {
        if (!isset($this->queues[$name])) {
            try {
                $result = $this->client->getQueueUrl([
                    'QueueName' => $name,
                ]);
                $this->queues[$name] = $result->get('QueueUrl');
            } catch (SqsException $exception) {
                throw new InvalidArgumentException("Specified queue '{$name}' does not exist", $exception->getCode(), $exception);
            }
        }

        return $this->queues[$name];
    }

    private function determineDeadletterQueue($queue)
    {
        $name = $this->queuePrefix . $queue;
        try {
            $result = $this->client->getQueueAttributes([
                'QueueUrl' => $this->getQueueUrlWithPrefix($queue),
                'AttributeNames' => ['RedrivePolicy'],
            ]);
            $arnJson = $result->search('Attributes.RedrivePolicy');
            if (empty($arnJson)) {
                throw new RuntimeException("Specified queue '{$name}' does not have a Redrive Policy");
            }

            $targetArn = json_decode($arnJson, true, 512, JSON_THROW_ON_ERROR)['deadLetterTargetArn'];
            return substr($targetArn, strrpos($targetArn, ':') + 1);
        } catch (SqsException $exception) {
            throw new RuntimeException("Specified queue '{$name}' does not have a Redrive Policy");
        }
    }
}
