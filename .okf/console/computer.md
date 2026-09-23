---
type: Module
title: Computer
description: How built-in commands get onto php computer's available list.
resource: src/Voyager/Core/Providers/ComputerServiceProvider.php
tags: [console, computer]
status: draft
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

`php computer` with no arguments prints the application help, then the available commands. `ComputerServiceProvider` is deferred. `Kernel::bootstrap()` calls `loadDeferredProviders()` before it builds `ComputerConsoleInstance`. The provider's `commands()` hook is `ComputerConsoleInstance::starting()`, which runs in the console constructor and fills the command map before the container command loader is sealed.[^kernel][^provider]

# What is registered

The uncommented entries in `ComputerServiceProvider` are the commands on the list. `make:job` and `make:job-middleware` are commented out because the queue provider does not boot. `make:gig` is registered. It writes a class under `Gigs` that implements `ShouldPool`.[^provider][^gig]

A command whose `AsCommand` attribute carries aliases must `setAliases()` with those names. The loader map includes every alias. Symfony throws `CommandNotFoundException` while listing if an alias is in the map and the command object does not declare it.

[^provider]: ComputerServiceProvider
[^kernel]: Console kernel bootstrap
[^gig]: make:gig
