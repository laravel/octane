# Laravel Octane

This application uses Laravel Octane, a long-running PHP process server. The application bootstraps once and handles many requests in the same process.

Critical rules (always apply):
- Never store request-specific state in singletons or static properties, since it leaks across requests
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`)
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services

> When working on Octane-specific features (concurrency, shared tables, memory, driver config, testing), invoke `octane-developement` for detailed rules.
