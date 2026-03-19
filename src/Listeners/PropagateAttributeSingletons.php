<?php

namespace Laravel\Octane\Listeners;

class PropagateAttributeSingletons
{
    /**
     * Handle the event.
     *
     * When classes decorated with the #[Singleton] attribute are resolved for
     * the first time inside the request sandbox, the container lazily registers
     * them as shared bindings and stores their instances – but only on the
     * sandbox clone. Because the root application never receives these bindings,
     * every subsequent request clone starts fresh, effectively treating the
     * singleton as a scoped binding.
     *
     * This listener runs after each operation (request/task/tick) and copies any
     * attribute-detected singleton bindings AND their resolved instances from
     * the sandbox back to the root application, so that future clones inherit
     * them and behave identically to singletons registered via a service provider.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $sandbox = $event->sandbox;
        $app = $event->app;

        // The checkedForSingletonOrScopedAttributes property was introduced in
        // Laravel 11 alongside the #[Singleton] attribute. On older versions
        // there is nothing to propagate, so bail out early.
        if (! property_exists($sandbox, 'checkedForSingletonOrScopedAttributes')) {
            return;
        }

        // Access the protected attribute cache from the sandbox via reflection.
        $checkedAttributes = $this->getProtectedProperty($sandbox, 'checkedForSingletonOrScopedAttributes');

        if (empty($checkedAttributes)) {
            return;
        }

        // For each class that was detected as a singleton via the attribute,
        // register the binding and instance on the root app if not already present.
        foreach ($checkedAttributes as $className => $type) {
            if ($type !== 'singleton') {
                continue;
            }

            // Skip if the root app already has this as a shared binding or instance.
            if (isset($app[$className]) && $app->isShared($className)) {
                continue;
            }

            // Register as singleton on root app.
            if (! $app->bound($className)) {
                $app->singleton($className);
            }

            // If the sandbox resolved an instance, copy it to the root app
            // so clones won't need to rebuild it.
            if ($sandbox->resolved($className)) {
                $instances = $this->getProtectedProperty($sandbox, 'instances');

                if (array_key_exists($className, $instances)) {
                    $app->instance($className, $instances[$className]);
                }
            }
        }

        // Also propagate the attribute cache itself so the root app skips
        // reflection on future clones.
        $rootChecked = $this->getProtectedProperty($app, 'checkedForSingletonOrScopedAttributes');
        $merged = array_merge($rootChecked, $checkedAttributes);
        $this->setProtectedProperty($app, 'checkedForSingletonOrScopedAttributes', $merged);
    }

    /**
     * Get a protected property from an object via reflection.
     *
     * @param  object  $object
     * @param  string  $property
     * @return mixed
     */
    protected function getProtectedProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }

    /**
     * Set a protected property on an object via reflection.
     *
     * @param  object  $object
     * @param  string  $property
     * @param  mixed  $value
     */
    protected function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);

        $reflection->setValue($object, $value);
    }
}
