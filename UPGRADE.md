# Upgrade Guide

## Upgrading To 3.0 From 2.x

### Minimum Versions

The following requirements have been updated:

- The minimum PHP version is now `v8.1.0`
- The minimum Laravel version is now `v10.9.0`

### Updating Dependencies

You should update the following dependency in your application's composer.json file:

```diff
-   "laravel/octane": "^1.5",
+   "laravel/octane": "^2.0",
```

If you are using RoadRunner, it is necessary for you to update the corresponding dependency in the `composer.json` file:

```diff
-   "spiral/roadrunner": "^2.8.2",
+   "spiral/roadrunner-http": "^3.0.1",
+   "spiral/roadrunner-cli": "^2.5.0",
```

Please keep in mind that you will be required to "stop" your Octane server prior to updating your dependencies in production. Once the updates have been completed, you may then proceed to re-start your server.
