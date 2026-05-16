# Octane

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- The Laravel container's `scoped` method may be used as a safe alternative to `singleton`.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or `bind()` instead:

```php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
```

- Never append to static properties, as they accumulate in memory across requests.
