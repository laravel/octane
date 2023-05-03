# Upgrade Guide

## Upgrading To 3.0 From 2.x

### Minimum Versions

The following requirements have been updated:

- The minimum PHP version is now `v8.1.0`
- The minimum Laravel version is now `v10.9.0`

### Updating Dependencies

You should update the following dependencies in your application's composer.json file:

```diff
-   "laravel/octane": "^1.5",
-   "spiral/roadrunner": "^2.8.2",
+   "laravel/octane": "^2.0",
+   "spiral/roadrunner-http": "^3.0.1",
+   "spiral/roadrunner-cli": "^2.5.0",
```

### Stop And Re-Start Server

Once you update your composer's dependencies, you will need to stop and re-start your Octane server.
