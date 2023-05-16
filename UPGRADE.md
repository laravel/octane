# Upgrade Guide

## Upgrading To 3.0 From 2.x

### Minimum Versions

The following requirements have been updated:

- The minimum PHP version is now `v8.1.0`
- The minimum Laravel version is now `v10.10.1`

### Updating Dependencies

You should update the following dependency in your application's `composer.json` file:

```diff
-   "laravel/octane": "^1.5",
+   "laravel/octane": "^2.0",
```

If you are using RoadRunner, you should update the corresponding dependency in your application's `composer.json` file:

```diff
-   "spiral/roadrunner": "^2.8.2",
+   "spiral/roadrunner-http": "^3.0.1",
+   "spiral/roadrunner-cli": "^2.5.0",
```

In production, you should "stop" your Octane workers prior to updating your dependencies. After updating, you may restart your workers.
