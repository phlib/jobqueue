<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\JobQueue\JobInterface;
use Phlib\Beanstalk\Connection;

/**
 * Class Job
 * @package Phlib\JobQueue\Beanstalk
 */
class Job implements JobInterface
{
    /**
     * @var string|null
     */
    protected $queue = null;

    /**
     * @var int|string|null
     */
    protected $id;

    /**
     * @var int
     */
    protected $delay;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var int
     */
    protected $ttr;

    /**
     * @var mixed
     */
    protected $body;

    /**
     * Job constructor.
     * @param string $queue
     * @param mixed $body
     * @param int|string|null $id
     * @param int $delay
     * @param int $priority
     * @param int $ttr
     */
    public function __construct(
        $queue,
        $body,
        $id = null,
        $delay = Connection::DEFAULT_DELAY,
        $priority = Connection::DEFAULT_PRIORITY,
        $ttr = Connection::DEFAULT_TTR
    ) {
        $this->queue = $queue;
        $this->body  = $body;
        $this->id    = $id;
        $this->delay = (int)$delay;
        $this->setPriority($priority);
        $this->setTtr($ttr);
    }

    /**
     * @return int|string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getDatetimeDelay()
    {
        return (new \DateTimeImmutable())->setTimestamp(time() + $this->getDelay());
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setDelay($value)
    {
        $this->delay = (int)$value;
        return $this;
    }

    /**
     * @param \DateTimeInterface $value
     * @return $this
     */
    public function setDatetimeDelay(\DateTimeInterface $value)
    {
        $value = time() - $value->getTimestamp();
        if ($value < 0) {
            $value = 0;
        }
        $this->setDelay($value);
        return $this;
    }

    /**
     * @return int
     */
    public function getTtr()
    {
        return $this->ttr;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setTtr($value)
    {
        $this->ttr = (int)$value;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function setPriority($value)
    {
        $this->priority = (int)$value;
        return $this;
    }
}
