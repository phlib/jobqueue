<?php

require_once __DIR__ . '/../vendor/autoload.php';

$queue     = 'my-test-tube';
$beanstalk = (new \Phlib\Beanstalk\Factory())->create('localhost');
$db        = new \Phlib\Db\Adapter(['host' => '127.0.0.1', 'dbname' => 'test']);
$scheduler = new \Phlib\JobQueue\Scheduler\DbScheduler($db, 60, 120);
$jobQueue  = new \Phlib\JobQueue\Beanstalk\JobQueue($beanstalk, $scheduler);
