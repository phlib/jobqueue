<?php

namespace Phlib\JobQueue\Tests\Beanstalk;

use Phlib\JobQueue\Beanstalk\JobFactory;
use Phlib\JobQueue\Beanstalk\JobQueue;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use Phlib\Beanstalk\Connection\ConnectionInterface;


class JobQueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConnectionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $beanstalk;

    /**
     * @var SchedulerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $scheduler;

    /**
     * @var JobQueue
     */
    protected $jobQueue;

    public function setUp()
    {
        parent::setUp();
        $this->beanstalk = $this->getMock(ConnectionInterface::class);
        $this->scheduler = $this->getMock(SchedulerInterface::class);
        $this->jobQueue  = new JobQueue($this->beanstalk, $this->scheduler);
    }

    public function tearDown()
    {
        $this->jobQueue  = null;
        $this->scheduler = null;
        $this->beanstalk = null;
        parent::tearDown();
    }

    public function testIsInstanceOfJobQueueInterface()
    {
        static::assertInstanceOf(JobQueueInterface::class, $this->jobQueue);
    }

    public function testPutForImmediateJobCallsBeanstalk()
    {
        $jobId = 123;
        $this->scheduler->expects(static::any())
            ->method('shouldBeScheduled')
            ->willReturn(false);
        $this->beanstalk->expects(static::any())
            ->method('useTube')
            ->will(static::returnSelf());
        $this->beanstalk->expects(static::once())
            ->method('put')
            ->will(static::returnValue($jobId));

        $job = $this->getMock(JobInterface::class);
        static::assertEquals($jobId, $this->jobQueue->put($job));
    }

    public function testPutForProlongedJobCallsScheduler()
    {
        $jobId = 123;
        $job   = $this->getMock(JobInterface::class);

        $this->scheduler->expects(static::any())
            ->method('shouldBeScheduled')
            ->willReturn(true);
        $this->scheduler->expects(static::once())
            ->method('store')
            ->with(static::equalTo($job))
            ->will(static::returnValue($jobId));

        static::assertEquals($jobId, $this->jobQueue->put($job));
    }

    public function testRetrieveSuccessfully()
    {
        $jobId = 123;
        $body  = ['queue' => 'TestQueue', 'body' => 'TestBody'];
        $this->beanstalk->expects(static::any())
            ->method('reserve')
            ->will(static::returnValue(['id' => $jobId, 'body' => serialize($body)]));
        static::assertEquals($jobId, $this->jobQueue->retrieve('testQueue')->getId());
    }

    public function testRetrieveWhenNoJobsAvailable()
    {
        $this->beanstalk->expects(static::any())
            ->method('reserve')
            ->will(static::returnValue(false));
        static::assertFalse($this->jobQueue->retrieve('testQueue'));
    }

    /**
     * @expectedException \Phlib\JobQueue\Exception\InvalidArgumentException
     */
    public function testRetrieveWithBadlyFormedBeanstalkData()
    {
        $this->beanstalk->expects(static::any())
            ->method('reserve')
            ->will(static::returnValue([]));
        $this->jobQueue->retrieve('testQueue');
    }

    /**
     * @expectedException \Phlib\JobQueue\Exception\JobRuntimeException
     */
    public function testRetrieveWithBadlyFormedJobBody()
    {
        $this->beanstalk->expects(static::any())
            ->method('reserve')
            ->will(static::returnValue(['id' => 234, 'body' => serialize('SomeStuffHere')]));
        $this->jobQueue->retrieve('testQueue');
    }

    /**
     * @param mixed $jobData
     * @dataProvider jobDataMaintainsExpectedTypeDataProvider
     */
    public function testJobDataMaintainsExpectedType($jobData)
    {
        $package = JobFactory::serializeBody(new Job('TestQueue', $jobData));
        $this->beanstalk->expects(static::any())
            ->method('reserve')
            ->will(static::returnValue(['id' => 234, 'body' => $package]));
        $job = $this->jobQueue->retrieve('TestQueue');
        static::assertEquals($jobData, $job->getBody());
    }

    public function jobDataMaintainsExpectedTypeDataProvider()
    {
        return [
            [['foo' => 'bar', 'bar' => 'baz']],
            ['SomeStringData'],
            [123],
            [123.34],
            // [null], // <-- null not accepted
            [[]],
            [false],
            [true]
        ];
    }

    public function testMarkAsCompleteDeletesBeanstalkJob()
    {
        $jobId = 123;
        $job = $this->getMock(JobInterface::class);
        $job->expects(static::any())
            ->method('getId')
            ->will(static::returnValue($jobId));
        $this->beanstalk->expects(static::once())
            ->method('delete')
            ->with(static::equalTo($jobId));
        $this->jobQueue->markAsComplete($job);
    }

    public function testMarkAsIncompleteReleasesBeanstalkJobWhenDelayIsMoreImmediate()
    {
        $jobId = 123;
        $job = $this->getMock(JobInterface::class);
        $job->expects(static::any())
            ->method('getId')
            ->will(static::returnValue($jobId));
        $this->scheduler->expects(static::any())
            ->method('shouldBeScheduled')
            ->will(static::returnValue(false));
        $this->beanstalk->expects(static::any())
            ->method('useTube')
            ->will(static::returnSelf());
        $this->beanstalk->expects(static::once())
            ->method('release')
            ->with(static::equalTo($jobId));
        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsIncompleteReleasesBeanstalkJobWhenDelayIsMoreProlonged()
    {
        $jobId = 123;
        $job = $this->getMock(JobInterface::class);
        $job->expects(static::any())
            ->method('getId')
            ->will(static::returnValue($jobId));
        $this->scheduler->expects(static::any())
            ->method('shouldBeScheduled')
            ->will(static::returnValue(true));
        $this->scheduler->expects(static::once())
            ->method('store')
            ->with(static::equalTo($job));
        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsErrorBuriesBeanstalkJob()
    {
        $jobId = 123;
        $job = $this->getMock(JobInterface::class);
        $job->expects(static::any())
            ->method('getId')
            ->will(static::returnValue($jobId));
        $this->beanstalk->expects(static::once())
            ->method('bury')
            ->with(static::equalTo($jobId));
        $this->jobQueue->markAsError($job);
    }
}
