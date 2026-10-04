---
type: Module
title: install:ext
description: Dev command that installs the first-party PHP extensions (epoll, kqueue, pcurl) through PIE, run by the PHP binary running computer.
resource: src/Voyager/Core/Extensions/
tags: [console, computer, extensions, pie, workflows]
status: draft
generated: { by: claude-fable-5-1, at: "2026-10-04T00:00:00Z" }
sources:
  - id: command
    resource: src/Voyager/Core/Console/ExtensionInstallCommand.php
    title: ExtensionInstallCommand
  - id: flow
    resource: src/Voyager/Core/Extensions/ExtensionsFlow.php
    title: ExtensionsFlow and its nodes
  - id: prompt
    resource: src/Voyager/Console/Prompts/ChecklistPrompt.php
    title: ChecklistPrompt
  - id: flow-test
    resource: tests/Core/Extensions/ExtensionsFlowTest.php
    title: Pest coverage for the interview
  - id: prompt-test
    resource: tests/Console/Prompts/ChecklistPromptTest.php
    title: Pest coverage for the prompt
---

# Use

```bash
php computer install:ext          # list, pick, install
php computer install:ext pcurl    # that one, no list
```

Registered in `ComputerServiceProvider::$dev_commands`.[^command]

| Run | Result |
|---|---|
| no argument, interactive | List of all three; rows for another OS or already loaded are disabled with the reason; installable rows start selected |
| no argument, non-interactive | Ends: `Name the extension to install on a non-interactive run: …` |
| `ext` given | Installs it without the list. Unknown name, another OS, or already loaded → one-line reason |

Exit 0: everything asked for is `installed`, or there was nothing to install, or nothing was selected. Exit 1 otherwise.

# Flow

`ExtensionsFlow` = a `Voyager\Workflows\Flow` of six nodes over a `SharedBag`.[^flow]

```
ExtensionStateNode → PhpConfigFinderNode → PieFinderNode → ExtensionSelectNode → ExtensionInstallNode
                                               └─ install-pie → PieInstallNode ─┘
```

* Bag in: `interactive`, `only` (name or null), `output` (callable for PIE's output when it has no terminal).
* Bag out: `extension_results` (name → outcome) or `extensions_note` (why it ended without installing).
* Every transition is a named action. `null` from `post()` stops the flow; no node has a `default` successor.
* Machine access = `Host` only: PHP binary, OS family, loaded extensions, home, executable lookup, download, and `run()` over `Voyager\Process\Factory`. Tests subclass `Host` and fake the factory.[^flow-test]

# Rules

* PIE is always run as `[PHP_BINARY, pie, …]`: PIE targets the PHP that runs it.
* php-config: the one beside the binary (`php8.4` → `php-config8.4`), then `PATH`; taken when its `--php-binary` is the running binary, and passed as `--with-php-config`. None on the machine → no flag, PIE's build-tool check takes over. Only a foreign one → ends with a note.
* PIE: on `PATH` or in `~/.local/bin`; counts only if it runs under this PHP and prints `(PIE)`. Missing → asks, downloads `pie.phar` to `~/.local/bin/pie`, runs `self-verify`, removes a copy that fails. Never downloaded on a non-interactive run.
* Install: one `pie install <package>:^0.10` per extension, on the terminal (sudo, build tools). A failure does not stop the next. Then `[php, '--ri', name]`.
* Outcomes: `installed` · `failed (PIE exit code N)` · `failed (<exception message>)` · `installed by PIE, but {php} does not load it`.

# ChecklistPrompt

`Voyager\Console\Prompts\ChecklistPrompt` extends `laravel/prompts` `MultiSelectPrompt` with disabled rows (value → reason): cursor steps over them, Space and Ctrl+A skip them, drawn dim with a dash and the reason. Answers its own renderer through `getRenderer()`. Needs `laravel/prompts` ≥ 0.3.15 (`highlightedValue()`); the root and Console manifests require `^0.3.15`.[^prompt][^prompt-test]

# Not covered by Pest

A real `pie install`, a real download, the sudo prompt, and `ExtensionInstallCommand::handle()` itself (the suite boots no application).

[^command]: ExtensionInstallCommand
[^flow]: ExtensionsFlow and its nodes
[^prompt]: ChecklistPrompt
[^flow-test]: Pest coverage for the interview
[^prompt-test]: Pest coverage for the prompt
