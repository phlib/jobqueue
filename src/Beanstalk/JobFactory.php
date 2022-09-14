<?php

declare(strict_types=1);

namespace Phlib\JobQueue\Beanstalk;

use Phlib\Beanstalk\Connection;
use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\JobRuntimeException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;

/**
 * Class JobFactory
 * @package Phlib\JobQueue\Beanstalk
 */
class JobFactory
{
    public static function createFromRaw(array $data): Job
    {
        if (!isset($data['id']) || !isset($data['body'])) {
            throw new InvalidArgumentException('Specified raw data is missing required elements.');
        }

        $specification = @unserialize($data['body']);
        if (!is_array($specification)) {
            $job = static::createFromSpecification([
                'queue' => '',
                'id' => $data['id'],
                'body' => 'false',
            ]);
            throw new JobRuntimeException($job, 'Failed to extract job data.');
        }

        $specification['id'] = $data['id'];
        return static::createFromSpecification($specification);
    }

    public static function createFromSpecification(array $data): Job
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
            'delay' => Connection::DEFAULT_DELAY,
            'priority' => Connection::DEFAULT_PRIORITY,
            'ttr' => Connection::DEFAULT_TTR,
        ];

        return new Job($data['queue'], $data['body'], $id, $data['delay'], $data['priority'], $data['ttr']);
    }

    public static function serializeBody(JobInterface $job): string
    {
        return serialize([
            'queue' => $job->getQueue(),
            'body' => $job->getBody(),
            'delay' => $job->getDelay(),
            'priority' => $job->getPriority(),
            'ttr' => $job->getTtr(),
        ]);
    }
}
