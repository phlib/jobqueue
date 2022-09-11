<?php
namespace Phlib\JobQueue\AwsSqs;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class JobQueueTest extends TestCase
{
    public function testCreateJob()
    {
        $queuePrefix = 'prefix-';
        $sqsClient = $this->getMockBuilder(SqsClient::class)->disableOriginalConstructor()->getMock();
        $scheduler = $this->getMock(SchedulerInterface::class);
        $jobQueue = new JobQueue($sqsClient, $scheduler, $queuePrefix);

        $queue = 'mockQueue';
        $data = ['userId' => 123];
        $id = 456;
        $delay = 75;
        $priority = 256;
        $ttr = 45;

        $job = $jobQueue->createJob($queue, $data, $id, $delay, $priority, $ttr);

        $this->assertEquals($queue, $job->getQueue());
        $this->assertEquals($data, $job->getBody());
        $this->assertEquals($id, $job->getId());
        $this->assertEquals($delay, $job->getDelay());
        $this->assertEquals($priority, $job->getPriority());
        $this->assertEquals($ttr, $job->getTtr());
    }

    public function testRetrieve()
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->getMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'mockQueue';
        $queueUrl = 'mockQueueUrl';

        // We expect to fetch the URL for the queue with the prefix
        $sqsClient->getQueueUrl(['QueueName' => $queuePrefix . $queue])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        // We expect to retrieve a job form the specified queue
        $sqsClient->receiveMessage(Argument::withEntry('QueueUrl', $queueUrl))->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $jobQueue->retrieve($queue);
    }

    public function testMarkAsErrorWithPrefix()
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->getMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'mockQueue';
        $deadLetterQueue = $queuePrefix . $queue . '-deadletter';
        $queueUrl = 'mockQueueUrl';
        $deadletterQueueUrl = 'mockDeadletterQueueUrl';
        $jobId = 123;


        // We expect to fetch the URL for the queue with the prefix
        $sqsClient->getQueueUrl(['QueueName' => $queuePrefix . $queue])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        // We expect to fetch the URL for the deadletter queue
        $sqsClient->getQueueUrl(['QueueName' => $deadLetterQueue])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $deadletterQueueUrl]]));

        // We expect to query the deadletter queue from the main queue with prefix
        $sqsClient->getQueueAttributes(Argument::withEntry('QueueUrl', $queueUrl))
            ->shouldBeCalledOnce()
            ->willReturn(
                $this->mockAwsResult([
                    ['Attributes.RedrivePolicy', json_encode(['deadLetterTargetArn' => "arn:{$deadLetterQueue}"])]
                ])
            );

        // We expect to remove the job from the main queue
        $sqsClient->deleteMessage(['QueueUrl' => $queueUrl, 'ReceiptHandle' => $jobId])
            ->shouldBeCalledOnce();

        // We expect to push a job to the deadletter queue
        $sqsClient->sendMessage(Argument::withEntry('QueueUrl', $deadletterQueueUrl))->shouldBeCalledOnce();

        $job = new Job($queue, null, $jobId);
        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $jobQueue->markAsError($job);
    }

    private function mockAwsResult(array $valueMap)
    {
        $result = $this->getMock(Result::class);
        $result->method('get')->will($this->returnValueMap($valueMap));
        $result->method('search')->will($this->returnValueMap($valueMap));
        return $result;
    }
}
