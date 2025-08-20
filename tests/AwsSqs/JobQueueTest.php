<?php

declare(strict_types=1);

namespace Phlib\JobQueue\AwsSqs;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @package Phlib\JobQueue
 */
class JobQueueTest extends TestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    public function testCreateJob(): void
    {
        $queuePrefix = 'prefix-';
        $sqsClient = $this->createMock(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);
        $jobQueue = new JobQueue($sqsClient, $scheduler, $queuePrefix);

        $queue = 'mockQueue';
        $data = [
            'userId' => 123,
        ];
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

    public function testRetrieve(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'mockQueue';
        $queueUrl = 'mockQueueUrl';

        // We expect to fetch the URL for the queue with the prefix
        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        // We expect to retrieve a job form the specified queue
        $sqsClient->receiveMessage(Argument::withEntry('QueueUrl', $queueUrl))->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $jobQueue->retrieve($queue);
    }

    public function testMarkAsErrorWithPrefix(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'mockQueue';
        $deadLetterQueue = $queuePrefix . $queue . '-deadletter';
        $queueUrl = 'mockQueueUrl';
        $deadletterQueueUrl = 'mockDeadletterQueueUrl';
        $jobId = 123;

        // We expect to fetch the URL for the queue with the prefix
        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        // We expect to fetch the URL for the deadletter queue
        $sqsClient->getQueueUrl([
            'QueueName' => $deadLetterQueue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $deadletterQueueUrl]]));

        // We expect to query the deadletter queue from the main queue with prefix
        $sqsClient->getQueueAttributes(Argument::withEntry('QueueUrl', $queueUrl))
            ->shouldBeCalledOnce()
            ->willReturn(
                $this->mockAwsResult([[
                    'Attributes.RedrivePolicy', json_encode([
                        'deadLetterTargetArn' => "arn:{$deadLetterQueue}",
                    ]),
                ]]),
            );

        // We expect to remove the job from the main queue
        $sqsClient->deleteMessage([
            'QueueUrl' => $queueUrl,
            'ReceiptHandle' => $jobId,
        ])
            ->shouldBeCalledOnce();

        // We expect to push a job to the deadletter queue
        $sqsClient->sendMessage(Argument::withEntry('QueueUrl', $deadletterQueueUrl))->shouldBeCalledOnce();

        $job = new Job($queue, null, $jobId);
        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $jobQueue->markAsError($job);
    }

    public function testPutBatchWithSingleQueue(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'test-queue';
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/prefix-test-queue';

        $scheduler->method('shouldBeScheduled')->willReturn(false);

        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        $job1 = new Job($queue, [
            'data' => 'test1',
        ], null, 0, 1024, 60);
        $job2 = new Job($queue, [
            'data' => 'test2',
        ], null, 5, 512, 30);
        $jobs = [$job1, $job2];

        $expectedEntries = [
            [
                'Id' => '0',
                'DelaySeconds' => 0,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue,
                        'body' => [
                            'data' => 'test1',
                        ],
                        'delay' => 0,
                        'priority' => 1024,
                        'ttr' => 60,
                    ]
                ),
            ],
            [
                'Id' => '1',
                'DelaySeconds' => 5,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue,
                        'body' => [
                            'data' => 'test2',
                        ],
                        'delay' => 5,
                        'priority' => 512,
                        'ttr' => 30,
                    ]
                ),
            ],
        ];

        $sqsClient->sendMessageBatch(
            [
                'QueueUrl' => $queueUrl,
                'Entries' => $expectedEntries,
            ]
        )->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    public function testPutBatchWithMultipleQueues(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue1 = 'test-queue-1';
        $queue2 = 'test-queue-2';
        $queueUrl1 = 'https://sqs.us-east-1.amazonaws.com/123456789012/prefix-test-queue-1';
        $queueUrl2 = 'https://sqs.us-east-1.amazonaws.com/123456789012/prefix-test-queue-2';

        $scheduler->method('shouldBeScheduled')->willReturn(false);

        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue1,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl1]]));

        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue2,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl2]]));

        $job1 = new Job($queue1, [
            'data' => 'test1',
        ]);
        $job2 = new Job($queue2, [
            'data' => 'test2',
        ]);
        $job3 = new Job($queue1, [
            'data' => 'test3',
        ]);
        $jobs = [$job1, $job2, $job3];

        $expectedEntries1 = [
            [
                'Id' => '0',
                'DelaySeconds' => 0,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue1,
                        'body' => [
                            'data' => 'test1',
                        ],
                        'delay' => 0,
                        'priority' => 1024,
                        'ttr' => 60,
                    ]
                ),
            ],
            [
                'Id' => '2',
                'DelaySeconds' => 0,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue1,
                        'body' => [
                            'data' => 'test3',
                        ],
                        'delay' => 0,
                        'priority' => 1024,
                        'ttr' => 60,
                    ]
                ),
            ],
        ];

        $expectedEntries2 = [
            [
                'Id' => '1',
                'DelaySeconds' => 0,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue2,
                        'body' => [
                            'data' => 'test2',
                        ],
                        'delay' => 0,
                        'priority' => 1024,
                        'ttr' => 60,
                    ]
                ),
            ],
        ];

        $sqsClient->sendMessageBatch(
            [
                'QueueUrl' => $queueUrl1,
                'Entries' => $expectedEntries1,
            ]
        )->shouldBeCalledOnce();

        $sqsClient->sendMessageBatch(
            [
                'QueueUrl' => $queueUrl2,
                'Entries' => $expectedEntries2,
            ]
        )->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    public function testPutBatchWithScheduledJobs(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'test-queue';
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/prefix-test-queue';

        $job1 = new Job($queue, [
            'data' => 'test1',
        ], null, 300);
        $job2 = new Job($queue, [
            'data' => 'test2',
        ], null, 0);
        $jobs = [$job1, $job2];

        $scheduler->expects($this->exactly(2))
            ->method('shouldBeScheduled')
            ->willReturnCallback(
                function ($delay) {
                    if ($delay === 300) {
                        return true;
                    } elseif ($delay === 0) {
                        return false;
                    }
                    throw new \InvalidArgumentException("Unexpected delay: {$delay}");
                }
            );

        $scheduler->expects($this->once())
            ->method('store')
            ->with($job1);

        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        $expectedEntries = [
            [
                'Id' => '1',
                'DelaySeconds' => 0,
                'MessageBody' => json_encode(
                    [
                        'queue' => $queue,
                        'body' => [
                            'data' => 'test2',
                        ],
                        'delay' => 0,
                        'priority' => 1024,
                        'ttr' => 60,
                    ]
                ),
            ],
        ];

        $sqsClient->sendMessageBatch(
            [
                'QueueUrl' => $queueUrl,
                'Entries' => $expectedEntries,
            ]
        )
            ->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    public function testPutBatchWithLargeNumberOfJobs(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'test-queue';
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/prefix-test-queue';

        $scheduler->method('shouldBeScheduled')->willReturn(false);

        $sqsClient->getQueueUrl([
            'QueueName' => $queuePrefix . $queue,
        ])
            ->shouldBeCalledOnce()
            ->willReturn($this->mockAwsResult([['QueueUrl', $queueUrl]]));

        $jobs = [];
        for ($i = 1; $i <= 25; $i++) {
            $jobs[] = new Job($queue, [
                'data' => "test{$i}",
            ]);
        }

        $sqsClient->sendMessageBatch(
            Argument::that(
                function ($args) use ($queueUrl) {
                    return $args['QueueUrl'] === $queueUrl &&
                       is_array($args['Entries']) &&
                       count($args['Entries']) === 10;
                }
            )
        )
            ->shouldBeCalledTimes(2);

        $sqsClient->sendMessageBatch(
            Argument::that(
                function ($args) use ($queueUrl) {
                    return $args['QueueUrl'] === $queueUrl &&
                       is_array($args['Entries']) &&
                       count($args['Entries']) === 5;
                }
            )
        )
            ->shouldBeCalledOnce();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    public function testPutBatchWithAllScheduledJobs(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->createMock(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $queue = 'test-queue';

        $job1 = new Job($queue, [
            'data' => 'test1',
        ], 300);
        $job2 = new Job($queue, [
            'data' => 'test2',
        ], 600);
        $jobs = [$job1, $job2];

        $scheduler->method('shouldBeScheduled')->willReturn(true);
        $scheduler->expects($this->exactly(2))
            ->method('store')
            ->willReturnCallback(
                function ($job) use ($job1, $job2) {
                    if ($job === $job1 || $job === $job2) {
                        return true;
                    }
                    throw new \InvalidArgumentException('Unexpected job');
                }
            );

        $sqsClient->getQueueUrl()->shouldNotBeCalled();
        $sqsClient->sendMessageBatch()->shouldNotBeCalled();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler, $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    public function testPutBatchWithEmptyArray(): void
    {
        $sqsClient = $this->prophesize(SqsClient::class);
        $scheduler = $this->prophesize(SchedulerInterface::class);

        $queuePrefix = 'prefix-';
        $jobs = [];

        $sqsClient->getQueueUrl()->shouldNotBeCalled();
        $sqsClient->sendMessageBatch()->shouldNotBeCalled();
        $scheduler->shouldBeScheduled()->shouldNotBeCalled();

        $jobQueue = new JobQueue($sqsClient->reveal(), $scheduler->reveal(), $queuePrefix);
        $result = $jobQueue->putBatch($jobs);

        $this->assertSame($jobQueue, $result);
    }

    private function mockAwsResult(array $valueMap): MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('get')->will($this->returnValueMap($valueMap));
        $result->method('search')->will($this->returnValueMap($valueMap));
        return $result;
    }
}
