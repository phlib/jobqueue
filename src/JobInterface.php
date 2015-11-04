<?php

namespace Phlib\JobQueue;

interface JobInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getBody();
}
