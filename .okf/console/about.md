---
type: Module
title: about
description: Command that prints what the app has set up — environment, caches, drivers, event loop, first-party extensions, sketches — plus any section a package adds.
resource: src/Voyager/Core/Console/AboutCommand.php
tags: [console, computer, about, packages]
status: draft
generated: { by: claude-opus-5-5, at: "2026-10-07T00:00:00Z" }
sources:
  - id: command
    resource: src/Voyager/Core/Console/AboutCommand.php
    title: AboutCommand
  - id: provider
    resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
    title: ComputerServiceProvider
  - id: test
    resource: tests/Core/AboutCommandTest.php
    title: Pest coverage
---

# Use

```bash
php computer about
php computer about --only=event-loop,extensions   # "Event Loop", event-loop, event_loop all match
php computer about --json
```

Runtime command (`$commands`, not dev). `registerAboutCommand()` hands it `$app['composer']`.[^provider]

# Sections

Order: Environment, Cache, Drivers, Event Loop, Extensions, Sketches, then package sections. Rows sorted, Environment excepted.[^command]

| Section | Rows |
|---|---|
| Environment | app name, framework version (Composer `InstalledVersions`, not `RenderedInstance::VERSION`), PHP version + binary, ZTS/NTS, Composer version, env, OS + arch |
| Cache | config cached, signals cached |
| Drivers | broadcasting, cache, concurrency, database, filesystem, hashing, async HTTP, logs (stack → channels), queue (failover → connections), redis client |
| Event Loop | waiter backend, pace, promise engine, mail handler (computer runs), process/thread pools: `ENABLED / n max` or OFF |
| Extensions | each `FirstPartyExtension`: loaded version, OS it needs, or `NOT INSTALLED` + its `install:ext` |
| Sketches | refresh rate, rocket's mail handler (`DeliverMailToSketches::HANDLER`), scanned paths |

`auto` drivers show `auto / <pick>`; the pick comes from `WaiterBackendManager::autoDriver()` / `HttpAsyncManager::autoDriver()`, the same methods `createAutoDriver()` uses. JSON gives the pick alone, stacks as lists, disabled pools as `false`.[^command][^test]

# Package sections

Static, so a deferred provider never needs resolving. Call from a provider's `boot()`:

```php
AboutCommand::add('Robot Arm', ['Joints' => '6']);          // pairs
AboutCommand::add('Robot Arm', 'Gripper', 'parallel');      // label + value
AboutCommand::add('Robot Arm', fn () => ['Bus' => 'i2c']);  // resolver, run at display time
AboutCommand::add('Robot Arm', RobotArmAbout::class);       // invokable, made + called through the container
AboutCommand::add('Robot Arm', ['Mode' => AboutCommand::format($mode, console: fn ($m) => strtoupper($m))]);
```

`AboutCommand::flushState()` clears added data; tests call it in `afterEach`.[^command]

[^command]: AboutCommand
[^provider]: ComputerServiceProvider
[^test]: Pest coverage
