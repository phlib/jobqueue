<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Tests\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DbSchedulerTest extends TestCase
{
    /**
     * @var Adapter|MockObject
     */
    private Adapter $adapter;

    /**
     * @var Adapter\QuoteHandler|MockObject
     */
    private Adapter\QuoteHandler $quote;

    public function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->createMock(Adapter::class);

        $this->quote = $this->createMock(Adapter\QuoteHandler::class);
        $this->adapter->method('quote')
            ->willReturn($this->quote);
    }

    public function tearDown(): void
    {
        unset(
            $this->quote,
            $this->adapter,
        );
        parent::tearDown();
    }

    public function testImplementsSchedulerInterface(): void
    {
        static::assertInstanceOf(SchedulerInterface::class, new DbScheduler($this->adapter));
    }

    public function testShouldBeScheduled(): void
    {
        $maxDelay = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        static::assertTrue($scheduler->shouldBeScheduled($maxDelay + 1));
    }

    public function testShouldNotBeScheduled(): void
    {
        $maxDelay = 300;
        $scheduler = new DbScheduler($this->adapter, $maxDelay);
        static::assertFalse($scheduler->shouldBeScheduled(60));
    }

    public function testStoringJob(): void
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
            ->willReturn(new \DateTimeImmutable());

        $scheduler = new DbScheduler($this->adapter);
        static::assertTrue($scheduler->store($job));
    }

    public function testRetrieveWithNoResults(): void
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

    public function testRetrieveCalculatesDelay(): void
    {
        $rowData = [
            'id' => 1,
            'tube' => 'queue',
            'data' => serialize('someData'),
            'scheduled_ts' => date('Y-m-d H:i:s'),
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

    public function testRemove(): void
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
