# Octane

Comprehensive rules for **Laravel Octane** — the long-running server toolkit for Swoole, RoadRunner, and FrankenPHP.

## When to Apply This Skill

Apply this skill when the user is:
- Configuring or troubleshooting an Octane server driver (Swoole, RoadRunner, FrankenPHP)
- Writing code that runs inside an Octane process (services, middleware, jobs)
- Debugging memory leaks, state bleed, or concurrency issues in an Octane app
- Using `Octane::concurrently()`, `Octane::table()`, or tick/interval handlers
- Asking about Octane-specific testing with `Octane::fake()`
- Asking about worker configuration, warm/flush lists, or request lifecycle

## Quick Reference

| Topic | Rule File |
|-------|-----------|
| State isolation & request lifecycle | `rules/state-isolation.md` |
| Concurrent tasks (`Octane::concurrently()`) | `rules/concurrency.md` *(Swoole only)* |
| Shared memory tables (`Octane::table()`) | `rules/swoole-tables.md` *(Swoole only)* |
| FrankenPHP driver setup & features | `rules/driver-frankenphp.md` |
| RoadRunner driver setup & features | `rules/driver-roadrunner.md` |
| Memory management & leak prevention | `rules/memory-management.md` |
| Testing Octane applications | `rules/testing.md` |

## How to Apply

1. **Identify the active driver** using `config('octane.server')` (`swoole`, `roadrunner`, or `frankenphp`).
2. **Load only the relevant rule files** — driver-specific rules (concurrency, tables, driver-frankenphp, driver-roadrunner) apply only to their respective driver.
3. **Always apply** `rules/state-isolation.md` and `rules/memory-management.md` — they are driver-agnostic.
4. For testing questions, use `rules/testing.md`.

> Use each rule file as a sub-agent reference. Do not try to load all rules simultaneously — load the relevant subset for the current task.
