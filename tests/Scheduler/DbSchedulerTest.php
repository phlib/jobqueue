<?php

namespace Phlib\JobQueue\Tests\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\JobQueue\Scheduler\SchedulerInterface;

class DbSchedulerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Phlib\Db\Adapter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $adapter;

    /**
     * @var \Phlib\Db\Adapter\QuoteHandler|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $quote;

    public function setUp()
    {
        parent::setUp();
        $this->adapter = $this->getMock(Adapter::class);

        $this->quote = $this->getMockBuilder(Adapter\QuoteHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adapter->expects($this->any())
            ->method('quote')
            ->willReturn($this->quote);
    }

    public function tearDown()
    {
        $this->quote = null;
        $this->adapter = null;
        parent::tearDown();
    }

    public function testImplementsSchedulerInterface()
    {
        $this->assertInstanceOf(SchedulerInterface::class, new DbScheduler($this->adapter));
    }

    public function testShouldBeScheduled()
    {
        $maxDelay  = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        $this->assertTrue($scheduler->shouldBeScheduled($maxDelay + 1));
    }

    public function testShouldNotBeScheduled()
    {
        $maxDelay  = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        $this->assertFalse($scheduler->shouldBeScheduled(60));
    }

    public function testStoringJob()
    {
        $pdoStatement = $this->getMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(1));
        $this->adapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));

        $job = $this->getMock(JobInterface::class);
        $job->expects($this->any())
            ->method('getDatetimeDelay')
            ->will($this->returnValue(new \DateTime()));

        $scheduler = new DbScheduler($this->adapter);
        $this->assertTrue($scheduler->store($job));
    }


    public function testRetrieveWithNoResults()
    {
        $stmt = $this->getMock(\PDOStatement::class);
        $stmt->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(0));
        $this->adapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($stmt));
        $scheduler = new DbScheduler($this->adapter);
        $this->assertFalse($scheduler->retrieve());
    }

    public function testRetrieveCalculatesDelay()
    {
        $rowData = [
            'id' => 1,
            'tube' => 'queue',
            'data' => serialize('someData'),
            'scheduled_ts' => time(),
            'priority' => 1024,
            'ttr' => 60
        ];
        $stmt = $this->getMock(\PDOStatement::class);
        $stmt->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(1));
        $stmt->expects($this->any())
            ->method('fetch')
            ->will($this->returnValue($rowData));
        $this->adapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($stmt));
        $scheduler = new DbScheduler($this->adapter);

        $jobData = $scheduler->retrieve();
        $this->assertEquals(0, $jobData['delay']);
    }

    public function testRemove()
    {
        $pdoStatement = $this->getMock(\PDOStatement::class);
        $pdoStatement->expects($this->any())
            ->method('rowCount')
            ->will($this->returnValue(1));
        $this->adapter->expects($this->any())
            ->method('query')
            ->will($this->returnValue($pdoStatement));
        $scheduler = new DbScheduler($this->adapter);
        $this->assertTrue($scheduler->remove(234));
    }
}
