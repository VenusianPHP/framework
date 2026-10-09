---
type: Module
title: Computer
description: How built-in commands get onto php computer's available list.
resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
tags: [console, computer]
status: draft
generated: { by: claude-opus-5-5, at: 2026-09-29T18:31:32Z }
sources:
  - id: provider
    resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
    title: ComputerServiceProvider
  - id: kernel
    resource: src/Voyager/Core/Console/Kernel.php
    title: Console kernel bootstrap
  - id: generator
    resource: src/Voyager/Console/MigrationGeneratorCommand.php
    title: MigrationGeneratorCommand
  - id: stubs
    resource: src/Voyager/Core/Console/StubPublishCommand.php
    title: StubPublishCommand
  - id: composer
    resource: src/Voyager/Core/ComposerScripts.php
    title: ComposerScripts
---

# Overview

`php computer` with no arguments prints the application help, then the available commands. This package has no `computer` script; `COMPUTER_BINARY` is `'computer'` and `RenderedInstance::handleInquiry()` is the door. `ComputerServiceProvider` is deferred; `Kernel::bootstrap()` calls `loadDeferredProviders()` before it builds `ComputerConsoleInstance`. A provider's `commands()` hook is `ComputerConsoleInstance::starting()`, which fills the command map in the console constructor before the container command loader is sealed.[^kernel][^provider]

# What is registered

The uncommented entries in `ComputerServiceProvider`. Commands: `cache:clear`, `cache:forget`, config cache/clear, env and env encrypt/decrypt, package discover, `invoke-serialized-closure`, signal cache/clear/list, every `queue:*`, `about`. Dev commands: `make:cache-table`, `make:queue-table`, `make:queue-failed-table`, `make:queue-batches-table`, the class/config/console/enum/exception/factory/interface/node/provider/sketch/test/trait makers, `config:publish`, `stub:publish`, `vendor:publish`. Commented: `make:gig`, `make:job`, `make:job-middleware`, event/listener/observer makers.[^provider]

A command constructed with required parameters gets `new Filesystem` unless the provider has a `register{Name}Command()` method (cache clear/forget, queue listen/monitor/restart/work). Other providers add theirs with `commands()`: migrations, `make:graph-model`.[^provider]

`Console\MigrationGeneratorCommand` writes one migration from a stub through `migration.creator`, into `databasePath('migrations')`, and refuses when a `*_create_{table}_table.php` exists.[^generator]

`stub:publish` copies maker stubs plus Database's factory, seeder, migration stubs into `stubs/`; makers prefer an app copy there. No rule or notification stub: neither component is in 0.10. Feature `test`/`pest` stubs assert `true`, no HTTP. `tests/Core/StubImportsTest` fails on any stub import the framework lacks; `Tests\` imports are the app's.[^stubs]

# Composer hook

App's `post-autoload-dump` calls `Voyager\Core\ComposerScripts::postAutoloadDump`: loads vendor autoload, then `clearCompiled(getcwd())` deletes cached config, services, packages, signals under `bootstrap/cache` (or their `APP_*_CACHE` paths). Next `package:discover` rebuilds packages from what is installed. No uninstall hook: dump runs after every remove.[^composer]

The loader maps every `|`-separated name from `AsCommand`'s `name`, not the attribute's `aliases`. `Voyager\Console\Command` calls `setAliases()` from its `$aliases` property.

[^provider]: ComputerServiceProvider
[^kernel]: Console kernel bootstrap
[^generator]: MigrationGeneratorCommand
[^stubs]: StubPublishCommand
[^composer]: ComposerScripts
