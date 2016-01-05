<?php

require_once __DIR__ . '/bootstrap.php';

do {
    try {
        $job = false;
        while ($job = $jobQueue->retrieve($queue)) {
            $jobQueue->markAsComplete($job);
        }
        usleep(333000);
    } catch (\Phlib\JobQueue\Exception\JobRuntimeException $e) {
        if ($e->hasJob()) {
            $jobQueue->markAsError($e->getJob());
        }
    }
} while (true);

