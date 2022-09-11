<?php

namespace Phlib\JobQueue\Tests;

use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;

class JobTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOfJobInterface()
    {
        $this->assertInstanceOf(JobInterface::class, new Job('queue', 'body'));
    }

    public function testEachProperty()
    {
        $data = [
            'queue'    => 'testQueue',
            'body'     => 'Job Body',
            'id'       => 'abc123',
            'delay'    => 10,
            'priority' => 5,
            'ttr'      => 60,
        ];
        $job = new Job($data['queue'], $data['body'], $data['id'], $data['delay'], $data['priority'], $data['ttr']);

        $actual = [
            'queue'    => $job->getQueue(),
            'body'     => $job->getBody(),
            'id'       => $job->getId(),
            'delay'    => $job->getDelay(),
            'priority' => $job->getPriority(),
            'ttr'      => $job->getTtr(),
        ];
        $this->assertSame($data, $actual);
    }

    public function testGetDatetimeDelayReturnsCorrectDateTime()
    {
        $delay = 10;
        $job = new Job('queue', 'body', 'id', $delay);
        $this->assertEquals(time() + $delay, $job->getDatetimeDelay()->getTimestamp());
    }

    public function testSetDelay()
    {
        $delay = 10;
        $job = new Job('queue', 'body', 'id', 2000);
        $job->setDelay($delay);
        $this->assertEquals($delay, $job->getDelay());
    }

    public function testSetTtr()
    {
        $ttr = 60;
        $job = new Job('queue', 'body', 'id', 10, 10, 2000);
        $job->setTtr($ttr);
        $this->assertEquals($ttr, $job->getTtr());
    }

    public function testSetPriority()
    {
        $priority = 100;
        $job = new Job('queue', 'body', 'id', 10, 2000);
        $job->setPriority($priority);
        $this->assertEquals($priority, $job->getPriority());
    }
}
