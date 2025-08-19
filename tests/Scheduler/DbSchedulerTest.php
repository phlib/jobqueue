<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Tests\Scheduler;

use Phlib\Db\Adapter;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\Scheduler\DbScheduler;
use Phlib\JobQueue\Scheduler\SchedulerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use STS\Backoff\Backoff;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->createMock(Adapter::class);

        $this->quote = $this->createMock(Adapter\QuoteHandler::class);
        $this->adapter->method('quote')
            ->willReturn($this->quote);
    }

    protected function tearDown(): void
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
            [
                'id' => 1,
                'tube' => 'queue',
                'data' => serialize('someData'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 1024,
                'ttr' => 60,
            ],
        ];
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);
        $stmt->expects(static::atLeastOnce())
            ->method('fetchAll')
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

    public function testRetrieveBatchWithNoResults(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(0);
        $this->adapter->expects(static::once())
            ->method('query')
            ->willReturn($stmt);
        $scheduler = new DbScheduler($this->adapter);
        static::assertFalse($scheduler->retrieveBatch());
    }

    public function testRetrieveBatchWithResults(): void
    {
        $rowData = [
            [
                'id' => 1,
                'tube' => 'queue1',
                'data' => serialize('data1'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 1024,
                'ttr' => 60,
            ],
            [
                'id' => 2,
                'tube' => 'queue2',
                'data' => serialize('data2'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 512,
                'ttr' => 30,
            ],
        ];

        $selectStmt = $this->createMock(\PDOStatement::class);
        $selectStmt->expects(static::once())
            ->method('fetchAll')
            ->willReturn($rowData);
        $selectStmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(2);

        $updateStmt = $this->createMock(\PDOStatement::class);

        $this->adapter->expects(static::exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls($selectStmt, $updateStmt);

        $scheduler = new DbScheduler($this->adapter);
        $jobs = $scheduler->retrieveBatch();

        static::assertIsArray($jobs);
        static::assertCount(2, $jobs);
        static::assertSame(1, $jobs[0]['id']);
        static::assertSame('queue1', $jobs[0]['queue']);
        static::assertSame('data1', $jobs[0]['data']);
        static::assertSame(0, $jobs[0]['delay']);
        static::assertSame(1024, $jobs[0]['priority']);
        static::assertSame(60, $jobs[0]['ttr']);
    }

    public function testRetrieveBatchCalculatesDelayCorrectly(): void
    {
        $futureTime = date('Y-m-d H:i:s', time() + 120);
        $rowData = [
            [
                'id' => 1,
                'tube' => 'queue',
                'data' => serialize('data'),
                'scheduled_ts' => $futureTime,
                'priority' => 1024,
                'ttr' => 60,
            ],
        ];

        $selectStmt = $this->createMock(\PDOStatement::class);
        $selectStmt->expects(static::once())
            ->method('fetchAll')
            ->willReturn($rowData);
        $selectStmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);

        $updateStmt = $this->createMock(\PDOStatement::class);

        $this->adapter->expects(static::exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls($selectStmt, $updateStmt);

        $scheduler = new DbScheduler($this->adapter);
        $jobs = $scheduler->retrieveBatch();

        static::assertIsArray($jobs);
        static::assertCount(1, $jobs);
        static::assertSame(120, $jobs[0]['delay']);
    }

    public function testRemoveBatchWithSingleJob(): void
    {
        $this->quote->expects(static::once())
            ->method('identifier')
            ->with('scheduled_queue')
            ->willReturn('`scheduled_queue`');

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);

        $this->adapter->expects(static::once())
            ->method('query')
            ->with(
                'DELETE FROM `scheduled_queue` WHERE id IN ( ? )',
                [123]
            )
            ->willReturn($pdoStatement);

        $scheduler = new DbScheduler($this->adapter);
        static::assertTrue($scheduler->removeBatch([123]));
    }

    public function testRemoveBatchWithMultipleJobs(): void
    {
        $this->quote->expects(static::once())
            ->method('identifier')
            ->with('scheduled_queue')
            ->willReturn('`scheduled_queue`');

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(3);

        $this->adapter->expects(static::once())
            ->method('query')
            ->with(
                'DELETE FROM `scheduled_queue` WHERE id IN ( ?,?,? )',
                [123, 456, 789]
            )
            ->willReturn($pdoStatement);

        $scheduler = new DbScheduler($this->adapter);
        static::assertTrue($scheduler->removeBatch([123, 456, 789]));
    }

    public function testRemoveBatchWithNoRowsAffected(): void
    {
        $this->quote->expects(static::once())
            ->method('identifier')
            ->with('scheduled_queue')
            ->willReturn('`scheduled_queue`');

        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->expects(static::once())
            ->method('rowCount')
            ->willReturn(0);

        $this->adapter->expects(static::once())
            ->method('query')
            ->willReturn($pdoStatement);

        $scheduler = new DbScheduler($this->adapter);
        static::assertFalse($scheduler->removeBatch([999]));
    }

    public function testQueryJobsWithRetrySuccessAfterDeadlock(): void
    {
        $rowData = [
            [
                'id' => 1,
                'tube' => 'queue',
                'data' => serialize('data'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 1024,
                'ttr' => 60,
            ],
        ];

        $deadlockException = new \PDOException(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
            40001
        );

        $selectStmt = $this->createMock(\PDOStatement::class);
        $selectStmt->expects(static::once())
            ->method('fetchAll')
            ->willReturn($rowData);
        $selectStmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(1);

        $updateStmt = $this->createMock(\PDOStatement::class);

        $this->adapter->expects(static::exactly(3))
            ->method('query')
            ->willReturnCallback(function () use ($deadlockException, $selectStmt, $updateStmt) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw $deadlockException;
                }
                return $callCount === 2 ? $selectStmt : $updateStmt;
            });

        $scheduler = new DbScheduler($this->adapter, 300, 600, false, 50, new Backoff(2, 'constant', 0));
        $result = $scheduler->retrieve();

        static::assertIsArray($result);
        static::assertSame(1, $result['id']);
    }

    public function testQueryJobsWithRetryThrowsExceptionAfterMaxAttempts(): void
    {
        $deadlockException = new \PDOException(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
            40001
        );

        $this->adapter->expects(static::exactly(5))
            ->method('query')
            ->willThrowException($deadlockException);

        $scheduler = new DbScheduler($this->adapter, 300, 600, false, 50, new Backoff(5, 'constant', 0));

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'
        );

        $scheduler->retrieve();
    }

    public function testQueryJobsWithRetryThrowsNonDeadlockExceptionImmediately(): void
    {
        $nonDeadlockException = new \PDOException('Table does not exist');
        $nonDeadlockException->errorInfo = ['42S02', 1146, 'Table does not exist'];

        $this->adapter->expects(static::once())
            ->method('query')
            ->willThrowException($nonDeadlockException);

        $scheduler = new DbScheduler($this->adapter, 300, 600, false, 50, new Backoff(5, 'constant', 0));

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Table does not exist');

        $scheduler->retrieve();
    }

    public function testQueryJobsWithRetryBatchSuccessAfterDeadlock(): void
    {
        $rowData = [
            [
                'id' => 1,
                'tube' => 'queue1',
                'data' => serialize('data1'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 1024,
                'ttr' => 60,
            ],
            [
                'id' => 2,
                'tube' => 'queue2',
                'data' => serialize('data2'),
                'scheduled_ts' => date('Y-m-d H:i:s'),
                'priority' => 512,
                'ttr' => 30,
            ],
        ];

        $deadlockException = new \PDOException(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
            40001
        );

        $selectStmt = $this->createMock(\PDOStatement::class);
        $selectStmt->expects(static::once())
            ->method('fetchAll')
            ->willReturn($rowData);
        $selectStmt->expects(static::once())
            ->method('rowCount')
            ->willReturn(2);

        $updateStmt = $this->createMock(\PDOStatement::class);

        $this->adapter->expects(static::exactly(3))
            ->method('query')
            ->willReturnCallback(function () use ($deadlockException, $selectStmt, $updateStmt) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw $deadlockException;
                }
                return $callCount === 2 ? $selectStmt : $updateStmt;
            });

        $scheduler = new DbScheduler($this->adapter, 300, 600, false, 50, new Backoff(2, 'constant', 0));
        $result = $scheduler->retrieveBatch();

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertSame(1, $result[0]['id']);
        static::assertSame(2, $result[1]['id']);
    }

    public function testQueryJobsWithRetryRollsBackTransactionOnDeadlock(): void
    {
        $deadlockException = new \PDOException(
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
            40001
        );

        $connection = $this->createMock(\PDO::class);
        $connection->expects(static::once())
            ->method('inTransaction')
            ->willReturn(true);

        $this->adapter->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection);

        $this->adapter->expects(static::once())
            ->method('beginTransaction');

        $this->adapter->expects(static::once())
            ->method('rollBack');

        $this->adapter->expects(static::once())
            ->method('query')
            ->willThrowException($deadlockException);

        $scheduler = new DbScheduler($this->adapter, 300, 600, false, 50, new Backoff(1));

        $this->expectException(\PDOException::class);
        $scheduler->retrieve();
    }
}
