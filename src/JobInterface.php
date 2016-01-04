<?php

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
     * @return string
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
     * @param int $value
     * @return $this
     */
    public function setDelay($value);

    /**
     * @param \DateTimeInterface $value
     * @return $this
     */
    public function setDatetimeDelay(\DateTimeInterface $value);

    /**
     * @return int
     */
    public function getTtr();

    /**
     * @param int $value
     * @return $this
     */
    public function setTtr($value);

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @param int $value
     * @return $this
     */
    public function setPriority($value);
}
