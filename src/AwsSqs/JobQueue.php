<?php

namespace Phlib\JobQueue\AwsSqs;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\RuntimeException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

class JobQueue implements JobQueueInterface
{
    /** @var SqsClient */
    private $client;

    /** @var SchedulerInterface */
    private $scheduler;

    /** @var int Seconds */
    private $retrieveTimeout = 10;

    /** @var array */
    private $queues = [];

    /** @var string */
    private $queuePrefix;

    public function __construct(SqsClient $client, SchedulerInterface $scheduler, $queuePrefix = '')
    {
        $this->client = $client;
        $this->scheduler = $scheduler;
        $this->queuePrefix = $queuePrefix;
    }

    /**
     * @inheritdoc
     */
    public function createJob($queue, $data, $id = null, $delay = Job::DEFAULT_DELAY, $priority = Job::DEFAULT_PRIORITY, $ttr = Job::DEFAULT_TTR)
    {
        return new Job($queue, $data, $id, $delay, $priority, $ttr);
    }

    /**
     * @inheritdoc
     */
    public function put(JobInterface $job)
    {
        if ($this->scheduler->shouldBeScheduled($job->getDelay())) {
            return $this->scheduler->store($job);
        }

        $this->client->sendMessage([
            'QueueUrl'     => $this->getQueueUrlWithPrefix($job->getQueue()),
            'DelaySeconds' => $job->getDelay(),
            'MessageBody'  => JobFactory::serializeBody($job),
        ]);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function retrieve($queue)
    {
        $result = $this->client->receiveMessage([
            'QueueUrl'            => $this->getQueueUrlWithPrefix($queue),
            'WaitTimeSeconds'     => $this->retrieveTimeout,
            'MaxNumberOfMessages' => 1
        ]);

        if (!isset($result['Messages'])) {
            return null;
        }

        return JobFactory::createFromRaw($result['Messages'][0]);
    }

    /**
     * @inheritdoc
     */
    public function markAsComplete(JobInterface $job)
    {
        $this->client->deleteMessage([
            'QueueUrl'      => $this->getQueueUrlWithPrefix($job->getQueue()),
            'ReceiptHandle' => $job->getId(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function markAsIncomplete(JobInterface $job)
    {
        $this->markAsComplete($job);
        $job->setDelay(0);
        $this->put($job);
    }

    /**
     * @inheritdoc
     */
    public function markAsError(JobInterface $job)
    {
        $queue = $job->getQueue();
        $deadletter = $this->determineDeadletterQueue($queue);

        $this->client->deleteMessage([
            'QueueUrl'      => $this->getQueueUrlWithPrefix($queue),
            'ReceiptHandle' => $job->getId(),
        ]);

        $job->setDelay(0);
        $this->client->sendMessage([
            'QueueUrl'     => $this->getQueueUrl($deadletter),
            'DelaySeconds' => 0,
            'MessageBody'  => JobFactory::serializeBody($job),
        ]);
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
                $result = $this->client->getQueueUrl(['QueueName' => $name]);
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
                'AttributeNames' => ['RedrivePolicy']
            ]);
            $arnJson = $result->search('Attributes.RedrivePolicy');
            if (empty($arnJson)) {
                throw new RuntimeException("Specified queue '{$name}' does not have a Redrive Policy");
            }

            $targetArn = json_decode($arnJson, true)['deadLetterTargetArn'];
            return substr($targetArn, strrpos($targetArn, ':') + 1);
        } catch (SqsException $exception) {
            throw new RuntimeException("Specified queue '{$name}' does not have a Redrive Policy");
        }
    }
}
