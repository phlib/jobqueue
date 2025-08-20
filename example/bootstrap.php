<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$queue = 'my-test-tube';
$beanstalk = (new \Phlib\Beanstalk\Factory())->create('localhost');
$db = new \Phlib\Db\Adapter([
    'host' => '127.0.0.1',
    'dbname' => 'test',
]);
$backoff = new \STS\Backoff\Backoff(5, 'constant', 2000, true);
$scheduler = new \Phlib\JobQueue\Scheduler\DbScheduler($db, 60, 120, true, 50, $backoff);
$jobQueue = new \Phlib\JobQueue\Beanstalk\JobQueue($beanstalk, $scheduler);
