<?php

declare(strict_types=1);

namespace Phlib\JobQueue;

interface JobInterface
{
    /**
     * @return string|integer|null
     */
    public function getId();

    /**
     * @return string
     */
    public function getQueue();

    /**
     * @return mixed
     */
    public function getBody();

    /**
     * @return int
     */
    public function getDelay();

    /**
     * @return \DateTime
     */
    public function getDatetimeDelay();

    /**
     * @return $this
     */
    public function setDelay(int $value);

    /**
     * @return $this
     */
    public function setDatetimeDelay(\DateTimeInterface $value);

    /**
     * @return int
     */
    public function getTtr();

    /**
     * @return $this
     */
    public function setTtr(int $value);

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @return $this
     */
    public function setPriority(int $value);
}
