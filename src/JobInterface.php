<?php

declare(strict_types=1);

namespace Phlib\JobQueue;

/**
 * @package Phlib\JobQueue
 */
interface JobInterface
{
    /**
     * @return string|int|null
     */
    public function getId();

    public function getQueue(): string;

    /**
     * @return mixed
     */
    public function getBody();

    public function getDelay(): int;

    public function getDatetimeDelay(): \DateTimeImmutable;

    public function setDelay(int $value): self;

    public function setDatetimeDelay(\DateTimeInterface $value): self;

    public function getTtr(): int;

    public function setTtr(int $value): self;

    public function getPriority(): int;

    public function setPriority(int $value): self;
}
