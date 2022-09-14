<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Phlib\JobQueue\Job;

$delays = [0, 0, 65, 80, 100, 150, 200, 250, 300];
$length = count($delays);

do {
    $data = [
        'my-number' => rand(1, 100),
    ];
    $delay = $delays[rand(0, ($length - 1))];
    $jobQueue->put(new Job($queue, $data, null, $delay));

    usleep(rand(500000, 1000000));
} while (true);
