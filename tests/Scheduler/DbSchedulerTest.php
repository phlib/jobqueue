<?php

namespace Phlib\JobQueue\Tests\Scheduler;

use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

class DbSchedulerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phlib\Db\Adapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $dbAdapter;

    public function setUp()
    {
        parent::setUp();
        $this->dbAdapter = $this->getMock('\Phlib\Db\Adapter');
    }

    public function tearDown()
    {
        $this->dbAdapter = null;
        parent::tearDown();
    }

    public function testImplementsSchedulerInterface()
    {
        $this->assertInstanceOf('\Phlib\JobQueue\Scheduler\SchedulerInterface', new DbScheduler($this->dbAdapter));
    }

    public function testShouldBeScheduled()
    {
        $maxDelay  = 300;
        $scheduler = new DbScheduler($this->dbAdapter, $maxDelay);
        $this->assertTrue($scheduler->shouldBeScheduled($maxDelay + 1));
    }

    public function testShouldNotBeScheduled()
    {
        $maxDelay  = 300;
        $scheduler = new DbScheduler($this->dbAdapter, $maxDelay);
        $this->assertFalse($scheduler->shouldBeScheduled(60));
    }

    public function testStoringJob()
    {
        $this->dbAdapter->expects($this->any())
            ->method('insert')
            ->will($this->returnValue(true));

        $job = $this->getMock('\Phlib\JobQueue\JobInterface');
        $job->expects($this->any())
            ->method('getDatetimeDelay')
            ->will($this->returnValue(new \DateTime()));

        $scheduler = new DbScheduler($this->dbAdapter);
        $this->assertTrue($scheduler->store($job));
    }


    public function testRetrieveWithNoResults()
    {
        $stmt = $this->getMock('\PDOStatement');
        $stmt->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(0));
        $this->dbAdapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($stmt));
        $scheduler = new DbScheduler($this->dbAdapter);
        $this->assertFalse($scheduler->retrieve());
    }

    public function testRetrieveCalculatesDelay()
    {
        $rowData = [
            'id' => 1,
            'queue' => 'queue',
            'data' => serialize('someData'),
            'delay' => time(),
            'priority' => 1024,
            'ttr' => 60
        ];
        $stmt = $this->getMock('\PDOStatement');
        $stmt->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(0));
        $stmt->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($rowData));
        $this->dbAdapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($stmt));
        $scheduler = new DbScheduler($this->dbAdapter);

        $jobData = $scheduler->retrieve();
        $this->assertEquals(0, $jobData['delay']);
    }

    public function testRemove()
    {
        $this->dbAdapter->expects($this->any())
            ->method('delete')
            ->will($this->returnValue(true));
        $scheduler = new DbScheduler($this->dbAdapter);
        $this->assertTrue($scheduler->remove(234));
    }
}
