# phlib/jobqueue

[![Code Checks](https://img.shields.io/github/workflow/status/phlib/jobqueue/CodeChecks?logo=github)](https://github.com/phlib/jobqueue/actions/workflows/code-checks.yml)
[![Codecov](https://img.shields.io/codecov/c/github/phlib/jobqueue.svg?logo=codecov)](https://codecov.io/gh/phlib/jobqueue)
[![Latest Stable Version](https://img.shields.io/packagist/v/phlib/jobqueue.svg?logo=packagist)](https://packagist.org/packages/phlib/jobqueue)
[![Total Downloads](https://img.shields.io/packagist/dt/phlib/jobqueue.svg?logo=packagist)](https://packagist.org/packages/phlib/jobqueue)
![Licence](https://img.shields.io/github/license/phlib/jobqueue.svg)

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

$scheduler = new \Phlib\JobQueue\Scheduler\DbScheduler($db, 300, 600, true);
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

See [examples](example/) for more advance usage.

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

## License

This package is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
