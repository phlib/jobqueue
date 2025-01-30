# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [3.0.0] - 2025-01-30
### Added
- Type declarations for parameters and return types.
### Changed
- Upgraded `phlib/beanstalk` dependency to require v3. 
- Upgraded `phlib/console-process` dependency to require v3 or v4.
- Allow `phlib/console-configuration` v3 dependency.
### Removed
- **BC break**: Removed support for PHP versions <= v8.0 as they are no longer
  [actively supported](https://php.net/supported-versions.php) by the PHP project.

## [2.0.0] - 2022-09-14
### Added
- Add support for PHP v8
- Type declarations have been added to all properties, method parameters and
  return types where possible.
### Changed
- Improve worker command handling exceptions from job queue library.
- Worker command error log context keys changed to use `camelCase`.
- **BC break**: Make return types consistent for `JobQueueInterface` methods.
  - `put()` returns self. A job delayed in the DB used to return `true`,
    otherwise Beanstalk used to return a Beanstalkd job ID.
  - `retrieve()` returns `null` when not found. Beanstalk used to return `false`.
  - `markAsComplete()`, `markAsIncomplete()` and `markAsError()` return self.
    AWS returned void. Beanstalk sometimes returned the underlying connection.
- **BC break**: Wrap exceptions from SQS in package exception classes.
  If an implementation was catching `SqsException` it should now catch a
  `Phlib\JobQueue\Exception` class instead.
### Removed
- **BC break**: Removed support for PHP versions <= v7.3 as they are no longer
  [actively supported](https://php.net/supported-versions.php) by the PHP project

## [1.1.1] - 2018-11-15
### Fixed
- QueuePrefix is only applied when reading and writing to the queue.

## [1.1.0] - 2018-06-20
### Added
- Added implementation of AWS SQS queue using SDK.

## [1.0.1] - 2017-06-13
### Changed
- Updated all dependencies to also use stable versions.

## [1.0.0] - 2017-06-13
Stable release

## [0.0.5] - 2017-01-03
### Changed
- Update to use latest version of Phlib\Db.

## [0.0.4] - 2016-11-03
### Fixed
- When a queue becomes busy and is requested to shutdown, it ignores the
  shutdown request until it no longer has jobs to process. This fix adds
  a check to look for the shutdown call before looking for available jobs
  in the queue.

## [0.0.3] - 2016-06-06
Composer versioning fixes
### Changed
- Reduce the amount of logging output.
- Removed the debug lines and improved the info logging to include the queue.

## [0.0.2] - 2016-04-25
Composer versioning fixes
### Changed
- Remove unneeded DB repository.
- Change versions up to meet PHP 7 requirement.

## [0.0.1] - 2016-04-25
Initial release
