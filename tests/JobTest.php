<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Tests;

use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;
use PHPUnit\Framework\TestCase;

/**
 * @package Phlib\JobQueue
 */
class JobTest extends TestCase
{
    public function testInstanceOfJobInterface(): void
    {
        static::assertInstanceOf(JobInterface::class, new Job('queue', 'body'));
    }

    public function testEachProperty(): void
    {
        $data = [
            'queue' => 'testQueue',
            'body' => 'Job Body',
            'id' => 'abc123',
            'delay' => 10,
            'priority' => 5,
            'ttr' => 60,
        ];
        $job = new Job($data['queue'], $data['body'], $data['id'], $data['delay'], $data['priority'], $data['ttr']);

        $actual = [
            'queue' => $job->getQueue(),
            'body' => $job->getBody(),
            'id' => $job->getId(),
            'delay' => $job->getDelay(),
            'priority' => $job->getPriority(),
            'ttr' => $job->getTtr(),
        ];
        static::assertSame($data, $actual);
    }

    public function testGetDatetimeDelayReturnsCorrectDateTime(): void
    {
        $delay = 10;
        $job = new Job('queue', 'body', 'id', $delay);
        static::assertEquals(time() + $delay, $job->getDatetimeDelay()->getTimestamp());
    }

    public function testSetDelay(): void
    {
        $delay = 10;
        $job = new Job('queue', 'body', 'id', 2000);
        $job->setDelay($delay);
        static::assertEquals($delay, $job->getDelay());
    }

    public function testSetTtr(): void
    {
        $ttr = 60;
        $job = new Job('queue', 'body', 'id', 10, 10, 2000);
        $job->setTtr($ttr);
        static::assertEquals($ttr, $job->getTtr());
    }

    public function testSetPriority(): void
    {
        $priority = 100;
        $job = new Job('queue', 'body', 'id', 10, 2000);
        $job->setPriority($priority);
        static::assertEquals($priority, $job->getPriority());
    }
}
