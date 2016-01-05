<?php

namespace Phlib\JobQueue\Beanstalk;

use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\JobRuntimeException;

/**
 * Class JobFactory
 * @package Phlib\JobQueue\Beanstalk
 */
class JobFactory
{
    /**
     * @param array $data
     * @return Job|false
     * @throws InvalidArgumentException
     * @throws JobRuntimeException
     */
    public static function createFromRaw(array $data)
    {
        if (!isset($data['id']) || !isset($data['body'])) {
            throw new InvalidArgumentException('Specified raw data is missing required elements.');
        }

        $specification = @unserialize($data['body']);
        if ($specification === false) {
            $job = static::createFromSpecification(['queue' => false, 'id' => $data['id'], 'body' => 'false']);
            throw new JobRuntimeException($job, 'Failed to extract job data.');
        }

        $specification['id'] = $data['id'];
        return static::createFromSpecification($specification);
    }

    /**
     * @param array $data
     * @return Job
     * @throws InvalidArgumentException
     */
    public static function createFromSpecification(array $data)
    {
        if (!isset($data['body']) || !isset($data['queue'])) {
            throw new \InvalidArgumentException('Missing required job data.');
        }

        $id = null;
        if (isset($data['id'])) {
            $id = $data['id'];
        }

        // merge default values if any are missing
        $data = $data + [
            'delay'    => Connection::DEFAULT_DELAY,
            'priority' => Connection::DEFAULT_PRIORITY,
            'ttr'      => Connection::DEFAULT_TTR
        ];

        return new Job($data['queue'], $data['body'], $id, $data['delay'], $data['priority'], $data['ttr']);
    }
}
