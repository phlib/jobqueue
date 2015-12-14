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
     * @return \DateTime
     */
    public function getDelay();

    /**
     * @param \DateTime $value
     * @return $this
     */
    public function setDelay(\DateTime $value);

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
