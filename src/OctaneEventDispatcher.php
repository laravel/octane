<?php

namespace Laravel\Octane;

// This Event Listener is optimized for Octane
// Listeners must implement the handle() method
// All Listeners are only instantiated once at startup
// Listeners may keep references to initial app instances
// Events may extend an interface, but both the event and interface must be registered
class OctaneEventDispatcher
{
    private array $events = [];

    /**
     * $eventsWithListeners is an array of events with listeners
     * [
     *   Event::class => [ Listener::class, Listener::class ],
     *   Event::class => [ Listener::class, Listener::class ],
     * ].
     */
    public function registerAllListeners(array $eventsWithListeners): void
    {
        foreach ($eventsWithListeners as $event => $listeners) {
            // skip listeners that are already registered
            if (array_key_exists($event, $this->events)) {
                continue;
            }
            foreach (array_filter(array_unique($listeners)) as $listener) {
                $this->addListener($event, $listener);
            }
            $this->registerInterfaces($event, $eventsWithListeners);
        }
    }

    /**
     * Register a single event listener
     */
    public function registerListener(string $event, array $eventsWithListeners): void
    {
        $listeners = $eventsWithListeners[$event] ?? [];
        foreach (array_filter(array_unique($listeners)) as $listener) {
            $this->addListener($event, $listener);
        }
        $this->registerInterfaces($event, $eventsWithListeners);
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
        if (! isset($allEvents[$interface])) {
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
