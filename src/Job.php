<?php

declare(strict_types=1);

namespace Phlib\JobQueue;

/**
 * @package Phlib\JobQueue
 */
class Job implements JobInterface
{
    public const DEFAULT_DELAY = 0;

    public const DEFAULT_PRIORITY = 1024;

    public const DEFAULT_TTR = 60;

    protected string $queue;

    /**
     * @var int|string|null
     */
    protected $id;

    protected int $delay;

    protected int $priority;

    protected int $ttr;

    /**
     * @var mixed
     */
    protected $body;

    /**
     * @param mixed $body
     * @param int|string|null $id
     */
    public function __construct(
        string $queue,
        $body,
        $id = null,
        int $delay = self::DEFAULT_DELAY,
        int $priority = self::DEFAULT_PRIORITY,
        int $ttr = self::DEFAULT_TTR
    ) {
        $this->queue = $queue;
        $this->body = $body;
        $this->id = $id;
        $this->delay = $delay;
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

    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getDatetimeDelay(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp(time() + $this->getDelay());
    }

    public function setDelay(int $value): self
    {
        $this->delay = (int)$value;
        return $this;
    }

    public function setDatetimeDelay(\DateTimeInterface $value): self
    {
        $value = time() - $value->getTimestamp();
        if ($value < 0) {
            $value = 0;
        }
        $this->setDelay($value);
        return $this;
    }

    public function getTtr(): int
    {
        return $this->ttr;
    }

    public function setTtr(int $value): self
    {
        $this->ttr = $value;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $value): self
    {
        $this->priority = $value;
        return $this;
    }
}
