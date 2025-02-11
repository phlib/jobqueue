<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\ConnectionInterface;
use Phlib\Beanstalk\Exception\NotFoundException as BeanstalkNotFoundException;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\JobRuntimeException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @package Phlib\JobQueue
 */
class JobQueueTest extends TestCase
{
    private ConnectionInterface&MockObject $beanstalk;

    private SchedulerInterface&MockObject $scheduler;

    private JobQueue $jobQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beanstalk = $this->createMock(ConnectionInterface::class);
        $this->scheduler = $this->createMock(SchedulerInterface::class);
        $this->jobQueue = new JobQueue($this->beanstalk, $this->scheduler);
    }

    protected function tearDown(): void
    {
        unset(
            $this->jobQueue,
            $this->scheduler,
            $this->beanstalk,
        );
        parent::tearDown();
    }

    public function testIsInstanceOfJobQueueInterface(): void
    {
        static::assertInstanceOf(JobQueueInterface::class, $this->jobQueue);
    }

    public function testPutForImmediateJobCallsBeanstalk(): void
    {
        $jobId = 123;
        $this->scheduler->expects(static::once())
            ->method('shouldBeScheduled')
            ->willReturn(false);
        $this->beanstalk->expects(static::once())
            ->method('useTube');
        $this->beanstalk->expects(static::once())
            ->method('put')
            ->willReturn($jobId);

        $job = $this->createMock(JobInterface::class);
        $job->method('getDelay')
            ->willReturn(rand(1, 100));

        $this->jobQueue->put($job);
    }

    public function testPutForProlongedJobCallsScheduler(): void
    {
        $job = $this->createMock(JobInterface::class);

        $job->method('getDelay')
            ->willReturn(rand(1, 100));

        $this->scheduler->expects(static::once())
            ->method('shouldBeScheduled')
            ->willReturn(true);
        $this->scheduler->expects(static::once())
            ->method('store')
            ->with($job)
            ->willReturn(true);

        $this->jobQueue->put($job);
    }

    public function testRetrieveSuccessfully(): void
    {
        $jobId = 123;
        $body = [
            'queue' => 'TestQueue',
            'body' => 'TestBody',
        ];
        $this->beanstalk->expects(static::once())
            ->method('reserve')
            ->willReturn([
                'id' => $jobId,
                'body' => serialize($body),
            ]);
        static::assertEquals($jobId, $this->jobQueue->retrieve('testQueue')->getId());
    }

    public function testRetrieveWhenNoJobsAvailable(): void
    {
        $this->beanstalk->expects(static::once())
            ->method('reserve')
            ->willThrowException(new BeanstalkNotFoundException(
                BeanstalkNotFoundException::RESERVE_NO_JOBS_AVAILABLE_MSG,
                BeanstalkNotFoundException::RESERVE_NO_JOBS_AVAILABLE_CODE,
            ));
        static::assertNull($this->jobQueue->retrieve('testQueue'));
    }

    public function testRetrieveWithBadlyFormedBeanstalkData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->beanstalk->expects(static::once())
            ->method('reserve')
            ->willReturn([]);
        $this->jobQueue->retrieve('testQueue');
    }

    public function testRetrieveWithBadlyFormedJobBody(): void
    {
        $this->expectException(JobRuntimeException::class);

        $this->beanstalk->expects(static::once())
            ->method('reserve')
            ->willReturn([
                'id' => 234,
                'body' => serialize('SomeStuffHere'),
            ]);
        $this->jobQueue->retrieve('testQueue');
    }

    #[DataProvider('jobDataMaintainsExpectedTypeDataProvider')]
    public function testJobDataMaintainsExpectedType(mixed $jobData): void
    {
        $package = JobFactory::serializeBody(new Job('TestQueue', $jobData));
        $this->beanstalk->expects(static::once())
            ->method('reserve')
            ->willReturn([
                'id' => 234,
                'body' => $package,
            ]);
        $job = $this->jobQueue->retrieve('TestQueue');
        static::assertEquals($jobData, $job->getBody());
    }

    public static function jobDataMaintainsExpectedTypeDataProvider(): array
    {
        return [
            [[
                'foo' => 'bar',
                'bar' => 'baz',
            ]],
            ['SomeStringData'],
            [123],
            [123.34],
            // [null], // <-- null not accepted
            [[]],
            [false],
            [true],
        ];
    }

    public function testMarkAsCompleteDeletesBeanstalkJob(): void
    {
        $jobId = 123;
        $job = $this->createMock(JobInterface::class);
        $job->expects(static::once())
            ->method('getId')
            ->willReturn($jobId);

        $this->beanstalk->expects(static::once())
            ->method('delete')
            ->with($jobId);

        $this->jobQueue->markAsComplete($job);
    }

    public function testMarkAsIncompleteReleasesBeanstalkJobWhenDelayIsMoreImmediate(): void
    {
        $jobId = 123;
        $job = $this->createMock(JobInterface::class);
        $job->expects(static::once())
            ->method('getId')
            ->willReturn($jobId);
        $job->method('getDelay')
            ->willReturn(rand(1, 100));

        $this->scheduler->expects(static::once())
            ->method('shouldBeScheduled')
            ->willReturn(false);

        $this->beanstalk->expects(static::once())
            ->method('useTube');
        $this->beanstalk->expects(static::once())
            ->method('release')
            ->with($jobId);

        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsIncompleteReleasesBeanstalkJobWhenDelayIsMoreProlonged(): void
    {
        $jobId = 123;
        $job = $this->createMock(JobInterface::class);
        $job->expects(static::atLeastOnce())
            ->method('getId')
            ->willReturn($jobId);
        $job->method('getDelay')
            ->willReturn(rand(1, 100));

        $this->scheduler->expects(static::once())
            ->method('shouldBeScheduled')
            ->willReturn(true);
        $this->scheduler->expects(static::once())
            ->method('store')
            ->with($job);
        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsErrorBuriesBeanstalkJob(): void
    {
        $jobId = 123;
        $job = $this->createMock(JobInterface::class);
        $job->expects(static::once())
            ->method('getId')
            ->willReturn($jobId);
        $this->beanstalk->expects(static::once())
            ->method('bury')
            ->with($jobId);

        $this->jobQueue->markAsError($job);
    }
}
