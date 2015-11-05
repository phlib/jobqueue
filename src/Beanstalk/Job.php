<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\JobQueue\JobInterface;

/**
 * Class Job
 * @package Phlib\JobQueue\Beanstalk
 */
class Job implements JobInterface
{
    /**
     * @var array
     */
    private $id;

    /**
     * @var array
     */
    private $body;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->body = $data['body'];
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }
}
