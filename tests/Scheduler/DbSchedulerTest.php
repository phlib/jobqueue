<?php

namespace Phlib\JobQueue\Tests\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\TestCase;

class DbSchedulerTest extends TestCase
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
        $this->adapter = $this->createMock(Adapter::class);

        $this->quote = $this->createMock(Adapter\QuoteHandler::class);
        $this->adapter->method('quote')
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
        static::assertInstanceOf(SchedulerInterface::class, new DbScheduler($this->adapter));
    }

    public function testShouldBeScheduled()
    {
        $maxDelay = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        static::assertTrue($scheduler->shouldBeScheduled($maxDelay + 1));
    }

    public function testShouldNotBeScheduled()
    {
        $maxDelay = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        static::assertFalse($scheduler->shouldBeScheduled(60));
    }

    public function testStoringJob()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);
        $this->adapter->expects(static::once())
            ->method('query')
            ->willReturn($pdoStatement);

        $job = $this->createMock(JobInterface::class);
        $job->expects(static::once())
            ->method('getDatetimeDelay')
            ->willReturn(new \DateTime());

        $scheduler = new DbScheduler($this->adapter);
        static::assertTrue($scheduler->store($job));
    }

    public function testRetrieveWithNoResults()
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(0);
        $this->adapter->expects(static::once())
            ->method('query')
            ->willReturn($stmt);
        $scheduler = new DbScheduler($this->adapter);
        static::assertFalse($scheduler->retrieve());
    }

    public function testRetrieveCalculatesDelay()
    {
        $rowData = [
            'id' => 1,
            'tube' => 'queue',
            'data' => serialize('someData'),
            'scheduled_ts' => time(),
            'priority' => 1024,
            'ttr' => 60,
        ];
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);
        $stmt->expects(static::atLeastOnce())
            ->method('fetch')
            ->willReturn($rowData);
        $this->adapter->expects(static::atLeastOnce())
            ->method('query')
            ->willReturn($stmt);
        $scheduler = new DbScheduler($this->adapter);

        $jobData = $scheduler->retrieve();
        static::assertEquals(0, $jobData['delay']);
    }

    public function testRemove()
    {
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);
        $this->adapter->expects(static::once())
            ->method('query')
            ->willReturn($pdoStatement);
        $scheduler = new DbScheduler($this->adapter);
        static::assertTrue($scheduler->remove(234));
    }
}
