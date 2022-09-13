<?php

namespace Phlib\JobQueue\Command;

require __DIR__ . '/WorkerCommandMock.php';

use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\JobInterface;
use Phlib\JobQueue\JobQueueInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommandTest extends TestCase
{
    /**
     * @var JobQueueInterface|MockObject
     */
    protected $jobQueue;

    /**
     * @var InputInterface|MockObject
     */
    protected $input;

    /**
     * @var OutputInterface|MockObject
     */
    protected $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobQueue = $this->getMockForAbstractClass(JobQueueInterface::class);
        $this->input = $this->getMockForAbstractClass(InputInterface::class);
        $this->output = $this->getMockForAbstractClass(OutputInterface::class);

        $this->input->expects(static::once())
            ->method('getArgument')
            ->willReturn('start');
    }

    public function testRunCompletes(): void
    {
        // need to do at least one job to exit the loop of work
        $job = $this->getMockForAbstractClass(JobInterface::class);
        $this->jobQueue->expects(static::at(0))
            ->method('retrieve')
            ->willReturn($job);
        $this->jobQueue->expects(static::at(1))
            ->method('retrieve')
            ->willReturn(null);
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
        $this->jobQueue->expects(static::at(0))
            ->method('retrieve')
            ->willReturn($job);
        $this->jobQueue->expects(static::at(1))
            ->method('retrieve')
            ->willReturn(null);
        $this->jobQueue->expects(static::once())
            ->method('markAsComplete')
            ->willThrowException(new InvalidArgumentException());

        /** @var WorkerCommand|MockObject $command */
        $command = new WorkerCommandMock($this->jobQueue);
        $command->run($this->input, $this->output);
    }
}
