<?php

declare(strict_types=1);

namespace Phlib\JobQueue\AwsSqs;

use Phlib\JobQueue\Exception\InvalidArgumentException;
use Phlib\JobQueue\Exception\JobRuntimeException;
use Phlib\JobQueue\Job;
use Phlib\JobQueue\JobInterface;

/**
 * @package Phlib\JobQueue
 */
class JobFactory
{
    /**
     * @return string
     */
    public static function serializeBody(JobInterface $job)
    {
        return json_encode([
            'queue' => $job->getQueue(),
            'body' => $job->getBody(),
            'delay' => $job->getDelay(),
            'priority' => $job->getPriority(),
            'ttr' => $job->getTtr(),
        ], JSON_THROW_ON_ERROR);
    }

    public static function createFromRaw(array $data): Job
    {
        if (!isset($data['ReceiptHandle'], $data['Body'])) {
            throw new InvalidArgumentException('Specified raw data is missing required elements.');
        }
        $specification = json_decode($data['Body'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($specification)) {
            $job = static::createFromSpecification([
                'queue' => false,
                'id' => $data['ReceiptHandle'],
                'body' => 'false',
            ]);
            throw new JobRuntimeException($job, 'Failed to extract job data.');
        }

        $specification['id'] = $data['ReceiptHandle'];
        return static::createFromSpecification($specification);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function createFromSpecification(array $data): Job
    {
        if (!isset($data['body'], $data['queue'])) {
            throw new \InvalidArgumentException('Missing required job data.');
        }

        $id = null;
        if (isset($data['id'])) {
            $id = $data['id'];
        }

        // merge default values if any are missing
        $data += [
            'delay' => 0,
            'priority' => 1024,
            'ttr' => 60,
        ];

        return new Job($data['queue'], $data['body'], $id, $data['delay'], $data['priority'], $data['ttr']);
    }
}
