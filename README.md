# phlib/jobqueue

[![Build Status](https://img.shields.io/travis/phlib/jobqueue/master.svg)](https://travis-ci.org/phlib/jobqueue)
[![Codecov](https://img.shields.io/codecov/c/github/phlib/jobqueue.svg)](https://codecov.io/gh/phlib/jobqueue)
[![Latest Stable Version](https://img.shields.io/packagist/v/phlib/jobqueue.svg)](https://packagist.org/packages/phlib/jobqueue)
[![Total Downloads](https://img.shields.io/packagist/dt/phlib/jobqueue.svg)](https://packagist.org/packages/phlib/jobqueue)

Job Queue implementation.

## Install

Via Composer

``` bash
$ composer require phlib/jobqueue
```
or
``` JSON
"require": {
    "phlib/jobqueue": "*"
}
```

## Basic Usage

Bootstrap
``` php
$beanstalk = (new \Phlib\Beanstalk\Factory())->create('localhost');
$db        = new \Phlib\Db\Adapter(['host' => '127.0.0.1', 'dbname' => 'example']);

$scheduler = new \Phlib\JobQueue\DbScheduler($db, 300, 600);
$jobQueue  = new \Phlib\JobQueue\Beanstalk\Scheduled($beanstalk, $scheduler);
```

Producer
``` php
$delay = strtotime('+1 week') - time();
$jobQueue->put('my-queue', ['my' => 'jobData'], ['delay' => $delay]);
```

Consumer
``` php
do {
    while ($job = $jobQueue->retrieve($queue)) {
        echo "Found new job {$job->getId()}\n", var_export($job->getBody(), true), "\n";
        $jobQueue->markAsComplete($job);
    }

    usleep(500);
} while (true);
```

## Jobqueue Script

The script has a dependency on two constructed objects. The Job Queue interface and the Scheduler interface. In order 
to provide this the following describes how they are injected into the script.

jobqueue-config.php (can be located in the root or ```config``` folder.
``` php
<?php

$app = new MyApp();

return new \Phlib\JobQueue\Console\MonitorDependencies($app['jobqueue'], $app['scheduler']);

```

## Table Schema

``` SQL
CREATE TABLE `scheduled_queue` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tube` varchar(255) NOT NULL DEFAULT 'default',
  `data` blob NOT NULL,
  `scheduled_ts` timestamp NULL DEFAULT NULL,
  `priority` smallint(5) unsigned DEFAULT NULL,
  `ttr` smallint(5) unsigned DEFAULT NULL,
  `picked_by` varchar(20) DEFAULT NULL,
  `picked_ts` timestamp NULL DEFAULT NULL,
  `create_ts` timestamp NOT NULL,
  `update_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scheduled_ts` (`scheduled_ts`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;
```
