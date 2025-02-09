<?php

namespace Laravel\Octane;

// This Event Listener is optimized for Octane
// Listeners are registered in the config/octane.php file
// Listeners must implement the handle() method
// All Listeners are only instantiated once at startup
// Listeners may keep references to initial app instances
class OctaneEventListener
{
    private array $events = [];

    public function registerListener(string $event): void
    {
        $listeners = config('octane.listeners', [])[$event] ?? [];
        foreach (array_filter(array_unique($listeners)) as $listener) {
            $this->addListener($event, $listener);
        }
    }

    public function registerAllListeners(): void
    {
        $allEvents = config('octane.listeners', []);
        foreach ($allEvents as $event => $listeners) {
            // skip listeners that are already registered
            if (array_key_exists($event, $this->events)) {
                continue;
            }
            foreach (array_filter(array_unique($listeners)) as $listener) {
                $this->addListener($event, $listener);
            }
            $this->registerInterfaces($event, $allEvents);
        }
    }

    public function dispatchEvent($event): void
    {
        foreach (($this->events[$event::class] ?? []) as $listener) {
            $listener->handle($event);
        }
    }

    private function registerInterfaces(string $class, array $allEvents): void
    {
        foreach (class_implements($class) as $interface) {
            $this->registerInterface($interface, $class, $allEvents);
        }
    }

    private function registerInterface(string $interface, string $event, array $allEvents): void
    {
        if (!isset($allEvents[$interface])) {
            return;
        }
        $listeners = $allEvents[$interface];
        foreach (array_filter(array_unique($listeners)) as $listener) {
            $this->addListener($event, $listener);
        }
    }

    private function addListener(string $event, string $listeners): void
    {
        $this->events[$event][] = new $listeners();
    }

}
