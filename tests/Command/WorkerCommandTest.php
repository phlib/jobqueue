<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Command;

require __DIR__ . '/WorkerCommandMock.php';

use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Phlib\JobQueue
 */
class WorkerCommandTest extends TestCase
{
    private JobQueueInterface&MockObject $jobQueue;

    private InputInterface&MockObject $input;

    private OutputInterface&MockObject $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobQueue = $this->getMockForAbstractClass(JobQueueInterface::class);
        $this->input = $this->getMockForAbstractClass(InputInterface::class);
        $this->output = $this->getMockForAbstractClass(OutputInterface::class);

        // Stub only (no call-count expectation): tests that fail in initialize() never reach the action argument.
        $this->input->method('getArgument')
            ->willReturn('start');
    }

    protected function tearDown(): void
    {
        // BackgroundCommand registers handlers bound to the command; restore defaults so they don't leak between tests.
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);

        parent::tearDown();
    }

    public function testRunCompletes(): void
    {
        // need to do at least one job to exit the loop of work
        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::once())
            ->method('retrieve')
            ->willReturn($job);
        $command = new WorkerCommandMock($this->jobQueue);
        $code = $command->run($this->input, $this->output);
        static::assertEquals(0, $code);
    }

    public function testLibraryExceptionOnRetrieve(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->jobQueue->expects(static::once())
            ->method('retrieve')
            ->willThrowException(new InvalidArgumentException());

        /** @var WorkerCommand|MockObject $command */
        $command = new WorkerCommandMock($this->jobQueue);
        $command->run($this->input, $this->output);
    }

    public function testLibraryExceptionInMainLoop(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::once())
            ->method('retrieve')
            ->willReturn($job);
        $this->jobQueue->expects(static::once())
            ->method('markAsComplete')
            ->willThrowException(new InvalidArgumentException());

        /** @var WorkerCommand|MockObject $command */
        $command = new WorkerCommandMock($this->jobQueue);
        $command->run($this->input, $this->output);
    }

    /**
     * Regression: SIGTERM must stop the worker after the current job, even when more jobs are waiting.
     */
    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testSigtermStopsWorkerWhileQueueIsBusy(): void
    {
        $job = $this->getMockForAbstractClass(JobInterface::class);

        // Queue always has work. Fail rather than loop forever if the worker ignores the signal.
        $retrieved = 0;
        $this->jobQueue->method('retrieve')
            ->willReturnCallback(function () use (&$retrieved, $job): JobInterface {
                if (++$retrieved > 3) {
                    $this->fail('Worker kept retrieving jobs after SIGTERM');
                }
                return $job;
            });
        $this->jobQueue->expects(static::once())
            ->method('markAsComplete')
            ->with($job);

        $command = new class($this->jobQueue, false) extends WorkerCommandMock {
            public int $worked = 0;

            protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
            {
                $this->worked++;
                posix_kill(getmypid(), SIGTERM);
                return 0;
            }
        };

        $code = $command->run($this->input, $this->output);

        static::assertSame(0, $code);
        static::assertSame(1, $command->worked);
        static::assertSame(1, $retrieved);
    }

    public function testEmptyQueueDelaysThenContinues(): void
    {
        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::exactly(2))
            ->method('retrieve')
            ->with('mockQueue')
            ->willReturnOnConsecutiveCalls(null, $job);
        $this->jobQueue->expects(static::once())
            ->method('markAsComplete')
            ->with($job);

        $command = new class($this->jobQueue) extends WorkerCommandMock {
            protected int $emptyQueueDelay = 50000;
        };

        $started = microtime(true);
        $code = $command->run($this->input, $this->output);
        $elapsed = microtime(true) - $started;

        static::assertSame(0, $code);
        static::assertGreaterThanOrEqual(
            0.05,
            $elapsed,
            'Expected the worker to wait emptyQueueDelay when no job was available',
        );
    }

    public function testMissingQueueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required property 'queue'");

        $this->jobQueue->expects(static::never())
            ->method('retrieve');

        $command = new class() extends WorkerCommand {
        };
        $command->run($this->input, $this->output);
    }

    public function testEmptyQueueNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required property 'queue'");

        $this->jobQueue->expects(static::never())
            ->method('retrieve');

        $command = new class($this->jobQueue) extends WorkerCommandMock {
            protected string $queue = '';
        };
        $command->run($this->input, $this->output);
    }

    public function testNonZeroWorkCodeMarksErrorAndThrowsWhenExitOnException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Non zero exit code 1.');

        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::once())
            ->method('retrieve')
            ->willReturn($job);
        $this->jobQueue->expects(static::once())
            ->method('markAsError')
            ->with($job);
        $this->jobQueue->expects(static::never())
            ->method('markAsComplete');

        $command = new class($this->jobQueue) extends WorkerCommandMock {
            protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
            {
                return 1;
            }
        };
        $command->run($this->input, $this->output);
    }

    public function testNonZeroWorkCodeMarksErrorAndContinuesWithoutExitOnException(): void
    {
        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::once())
            ->method('retrieve')
            ->willReturn($job);
        $this->jobQueue->expects(static::once())
            ->method('markAsError')
            ->with($job);
        $this->jobQueue->expects(static::never())
            ->method('markAsComplete');

        $command = new class($this->jobQueue) extends WorkerCommandMock {
            protected bool $exitOnException = false;

            protected function work(JobInterface $job, InputInterface $input, OutputInterface $output): int
            {
                $this->shutdown();
                return 1;
            }
        };
        $code = $command->run($this->input, $this->output);

        static::assertSame(0, $code);
    }
}
