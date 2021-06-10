# Release Notes

## [Unreleased](https://github.com/laravel/octane/compare/v1.0.4...1.x)

### Fixed
- Uploaded files moving ([#317](https://github.com/laravel/octane/pull/317))

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
