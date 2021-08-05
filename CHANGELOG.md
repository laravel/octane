# Release Notes

## [Unreleased](https://github.com/laravel/octane/compare/v1.0.10...master)


## [v1.0.10 (2021-08-05)](https://github.com/laravel/octane/compare/v1.0.9...v1.0.10)

### Changed
- Resolve components when needed ([#355](https://github.com/laravel/octane/pull/355))

### Fixed
- Undefined array key "swoole" ([#351](https://github.com/laravel/octane/pull/351))
- Avoids usage of SWOOLE_SSL when openssl is not configured on swoole ([#357](https://github.com/laravel/octane/pull/357))


## [v1.0.9 (2021-08-03)](https://github.com/laravel/octane/compare/v1.0.8...v1.0.9)

### Changed
- Expose the flag to turn on `SWOOLE_SSL` ([#342](https://github.com/laravel/octane/pull/342), [86731cb](https://github.com/laravel/octane/commit/86731cb1a73200a98dff844a3b7e7b77bd07ff44))


## [v1.0.8 (2021-07-06)](https://github.com/laravel/octane/compare/v1.0.7...v1.0.8)

### Added
- Add `--rr-config` flag to `octane:start` command ([#335](https://github.com/laravel/octane/pull/335), [00aa3b0](https://github.com/laravel/octane/commit/00aa3b0b53a5e7f62e1ac6f19992af80199fb5f6))

### Changed
- Flush any log context after each operation ([#337](https://github.com/laravel/octane/pull/337))


## [v1.0.7 (2021-06-29)](https://github.com/laravel/octane/compare/v1.0.6...v1.0.7)

### Fixed
- Fix config key for roadrunner `http.middlware` ([#333](https://github.com/laravel/octane/pull/333))


## [v1.0.6 (2021-06-22)](https://github.com/laravel/octane/compare/v1.0.5...v1.0.6)

### Changed
- Set roadrunner `http.middleware` as an option ([#327](https://github.com/laravel/octane/pull/327))

### Fixed
- Prints server logs to "stderr" ([#326](https://github.com/laravel/octane/pull/326), [28cf5ee](https://github.com/laravel/octane/commit/28cf5ee6c98d6c4708179fbbba2f1c71125d4efa))


## [v1.0.5 (2021-06-15)](https://github.com/laravel/octane/compare/v1.0.4...v1.0.5)

### Fixed
- Reset application scope to flush scoped instances ([#313](https://github.com/laravel/octane/pull/313))
- Fix Swoole breaks when log is a valid JSON ([#316](https://github.com/laravel/octane/pull/316))
- Fix Symfony uploaded file moving ([#317](https://github.com/laravel/octane/pull/317))


## [v1.0.4 (2021-06-08)](https://github.com/laravel/octane/compare/v1.0.3...v1.0.4)

### Changed
- Add missing default listeners ([#311](https://github.com/laravel/octane/pull/311))


## [v1.0.3 (2021-06-01)](https://github.com/laravel/octane/compare/v1.0.2...v1.0.3)

### Changed
- Display memory usage ([#297](https://github.com/laravel/octane/pull/297), [#304](https://github.com/laravel/octane/pull/304))

### Fixed 
- Fixes issue related to changing non-standard HTTP status codes to 200 OK ([#294](https://github.com/laravel/octane/pull/294))
- Give new application instance to database session handler ([#302](https://github.com/laravel/octane/pull/302))
- Adds SameSite attribute for cookies ([#299](https://github.com/laravel/octane/pull/299))


## [v1.0.2 (2021-05-25)](https://github.com/laravel/octane/compare/v1.0.1...v1.0.2)

### Changed
- Remove buffer_output_size from Swoole's default options ([#286](https://github.com/laravel/octane/pull/286))

### Fixed
- Reload RoadRunner using the global executable if it's present ([#288](https://github.com/laravel/octane/pull/288))


## [v1.0.1 (2021-05-18)](https://github.com/laravel/octane/compare/v1.0.0...v1.0.1)

### Removed
- Remove beta warning on "octane:start" command ([5b25510](https://github.com/laravel/octane/commit/5b255108088e969c1584fe275f44b747a2a71d36))


## [v1.0.0 (2021-05-11)](https://github.com/laravel/octane/compare/v0.5.0...v1.0.0)

Stable release.


## [v0.5.0 (2021-05-04)](https://github.com/laravel/octane/compare/v0.4.0...v0.5.0)

> **Requires to stop, and re-start your Octane server**

### Fixed
- Default `--watch` options making Octanes servers reload on file uploads ([#247](https://github.com/laravel/octane/pull/247))
- Error `No buffer space available` when using Swoole ([#253](https://github.com/laravel/octane/pull/253))
- Global environment variables not being used by workers ([#257](https://github.com/laravel/octane/pull/257))

### Changed
- The new minimum RoadRunner binary version is now 2.1.1 ([#258](https://github.com/laravel/octane/pull/258))


## [v0.4.0 (2021-04-27)](https://github.com/laravel/octane/compare/v0.3.2...v0.4.0)

Various fixes and changes.


## [v0.3.2 (2021-04-20)](https://github.com/laravel/octane/compare/v0.3.1...v0.3.2)

Various fixes and changes.


## [v0.3.0 (2021-04-19)](https://github.com/laravel/octane/compare/v0.2.0...v0.3.0)

Various fixes and changes.


## [v0.2.0 (2021-04-13)](https://github.com/laravel/octane/compare/v0.1.1...v0.2.0)

Various fixes and changes.


## [v0.1.1 (2021-04-07)](https://github.com/laravel/octane/compare/v0.1.0...v0.1.1)

Various fixes and changes.


## v0.1.0 (2021-04-06)

Initial pre-release.
