---
type: Module
title: Console testing
description: Voyager\Console\Testing — artisan() and PendingCommand for testing Computer commands, over the console kernel's call().
tags: [console, testing, computer]
---

# Overview

`Voyager\Console\Testing\InteractsWithConsole` and `PendingCommand` are Laravel v13.37.0's console test helpers, generic to any Venusian app. A test case using the trait sets `$this->app`; `artisan($command, $parameters)` returns a `PendingCommand` (or the exit code after `withoutMockingConsoleOutput()`), and `realArtisan()`/`mockArtisan()` pick a side explicitly.

`PendingCommand` mocks `Voyager\Console\OutputStyle` through the container for its expectations: `expectsQuestion`, `expectsConfirmation`, `expectsChoice`, `expectsSearch`, `expectsTable`, `expectsOutput`/`doesntExpectOutput`, `expectsOutputToContain`/`doesntExpectOutputToContain`, `expectsPromptsInfo` and the other prompts expectations, `assertExitCode`/`assertOk`/`assertFailed`. It runs on destruct or `run()`.

The console kernel contract has `call(string $command, array $parameters = [], ?OutputInterface $outputBuffer = null): int`, as Laravel's does: it bootstraps (without booting providers for `env:encrypt`/`env:decrypt`) and runs the command through `ComputerConsoleInstance::call()`.

`tests/Console/PendingCommandTest.php` is Laravel's `ArtisanCommandTest` in Pest; its closure commands are classes in `tests/Console/Fixtures/PendingCommands.php`, since Voyager has no closure commands.
