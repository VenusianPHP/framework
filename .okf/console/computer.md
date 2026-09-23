---
type: Module
title: Computer
description: How built-in commands get onto php computer's available list.
resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
tags: [console, computer]
status: stable
verification_key: "agent:framework-auditor@5fb34e76550303f5c6cfeb757c63576b5e84bb94"
generated: { by: okf-documentation-generator/cursor, at: 2026-09-22T13:48:00Z }
sources:
  - id: provider
    resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
    title: ComputerServiceProvider
  - id: kernel
    resource: src/Voyager/Core/Console/Kernel.php
    title: Console kernel bootstrap
  - id: gig
    resource: src/Voyager/Core/Console/GigMakeCommand.php
    title: make:gig
---

# Overview

`php computer` with no arguments prints the application help, then the available commands. This package has no `computer` script; `COMPUTER_BINARY` is `'computer'` and `RenderedInstance::handleInquiry()` is the door. `ComputerServiceProvider` is deferred. `Kernel::bootstrap()` calls `loadDeferredProviders()` before it builds `ComputerConsoleInstance`. The provider's `commands()` hook is `ComputerConsoleInstance::starting()`, which runs in the console constructor and fills the command map before the container command loader is sealed.[^kernel][^provider]

# What is registered

The uncommented entries in `ComputerServiceProvider` are the commands on the list. `QueueServiceProvider` boots, and the `queue:*` work/listen/retry commands are registered. `make:job` and `make:job-middleware` stay commented; the source gives no reason. `About` is also commented. `make:gig` is registered. It writes a class under `Gigs` that implements `ShouldPool`.[^provider][^gig]

The loader maps every `|`-separated name from `AsCommand`'s `name`. It does not read the attribute's `aliases` list. `Voyager\Console\Command` calls `setAliases()` from the `$aliases` property. Extra names in the map that the command object does not declare make Symfony throw `CommandNotFoundException` while listing.

[^provider]: ComputerServiceProvider
[^kernel]: Console kernel bootstrap
[^gig]: make:gig
