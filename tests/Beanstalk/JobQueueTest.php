<?php

namespace Phlib\JobQueue\Tests\Beanstalk;

use Phlib\JobQueue\Beanstalk\JobQueue;
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
        $this->beanstalk = $this->getMock('\Phlib\Beanstalk\Connection\ConnectionInterface');
        $this->scheduler = $this->getMock('\Phlib\JobQueue\Scheduler\SchedulerInterface');
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
        $this->assertInstanceOf('\Phlib\JobQueue\JobQueueInterface', $this->jobQueue);
    }

    public function testPutForImmediateJobCallsBeanstalk()
    {
        $jobId = 123;
        $this->scheduler->expects($this->any())
            ->method('shouldBeScheduled')
            ->willReturn(false);
        $this->beanstalk->expects($this->any())
            ->method('useTube')
            ->will($this->returnSelf());
        $this->beanstalk->expects($this->once())
            ->method('put')
            ->will($this->returnValue($jobId));

        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $this->assertEquals($jobId, $this->jobQueue->put($job));
    }

    public function testPutForProlongedJobCallsScheduler()
    {
        $jobId = 123;
        $job   = $this->getMock('\Phlib\JobQueue\JobInterface');

        $this->scheduler->expects($this->any())
            ->method('shouldBeScheduled')
            ->willReturn(true);
        $this->scheduler->expects($this->once())
            ->method('store')
            ->with($this->equalTo($job))
            ->will($this->returnValue($jobId));

        $this->assertEquals($jobId, $this->jobQueue->put($job));
    }

    public function testRetrieveSuccessfully()
    {
        $jobId = 123;
        $body  = ['queue' => 'TestQueue', 'body' => 'TestBody'];
        $this->beanstalk->expects($this->any())
            ->method('reserve')
            ->will($this->returnValue(['id' => $jobId, 'body' => serialize($body)]));
        $this->assertEquals($jobId, $this->jobQueue->retrieve('testQueue')->getId());
    }

    public function testRetrieveWhenNoJobsAvailable()
    {
        $this->beanstalk->expects($this->any())
            ->method('reserve')
            ->will($this->returnValue(false));
        $this->assertFalse($this->jobQueue->retrieve('testQueue'));
    }

    /**
     * @expectedException \Phlib\JobQueue\Exception\InvalidArgumentException
     */
    public function testRetrieveWithBadlyFormedBeanstalkData()
    {
        $this->beanstalk->expects($this->any())
            ->method('reserve')
            ->will($this->returnValue([]));
        $this->jobQueue->retrieve('testQueue');
    }

    /**
     * @expectedException \Phlib\JobQueue\Exception\JobRuntimeException
     */
    public function testRetrieveWithBadlyFormedJobBody()
    {
        $this->beanstalk->expects($this->any())
            ->method('reserve')
            ->will($this->returnValue(['id' => 234, 'body' => serialize('SomeStuffHere')]));
        $this->jobQueue->retrieve('testQueue');
    }

    public function testMarkAsCompleteDeletesBeanstalkJob()
    {
        $jobId = 123;
        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $job->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($jobId));
        $this->beanstalk->expects($this->once())
            ->method('delete')
            ->with($this->equalTo($jobId));
        $this->jobQueue->markAsComplete($job);
    }

    public function testMarkAsInompleteReleasesBeanstalkJobWhenDelayIsMoreImmediate()
    {
        $jobId = 123;
        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $job->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($jobId));
        $this->scheduler->expects($this->any())
            ->method('shouldBeScheduled')
            ->will($this->returnValue(false));
        $this->beanstalk->expects($this->any())
            ->method('useTube')
            ->will($this->returnSelf());
        $this->beanstalk->expects($this->once())
            ->method('release')
            ->with($this->equalTo($jobId));
        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsIncompleteReleasesBeanstalkJobWhenDelayIsMoreProlonged()
    {
        $jobId = 123;
        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $job->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($jobId));
        $this->scheduler->expects($this->any())
            ->method('shouldBeScheduled')
            ->will($this->returnValue(true));
        $this->scheduler->expects($this->once())
            ->method('store')
            ->with($this->equalTo($job));
        $this->jobQueue->markAsIncomplete($job);
    }

    public function testMarkAsErrorBuriesBeanstalkJob()
    {
        $jobId = 123;
        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $job->expects($this->any())
            ->method('getId')
            ->will($this->returnValue($jobId));
        $this->beanstalk->expects($this->once())
            ->method('bury')
            ->with($this->equalTo($jobId));
        $this->jobQueue->markAsError($job);
    }
}
