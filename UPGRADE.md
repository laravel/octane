# Upgrade Guide

## Upgrading To 3.0 From 2.x

### Minimum Versions

The following requirements have been updated:

- The minimum PHP version is now `v8.1.0`
- The minimum Laravel version is now `v10.9.0`

### Updating Dependencies

If you are using RoadRunner, replace `"spiral/roadrunner": "^2.8.2"` with `"spiral/roadrunner-http": "^3.0.1"` and `"spiral/roadrunner-cli": "^2.5.0"` in your application's composer.json file.

To complete the upgrade, you need to stop and re-start your Octane server.
