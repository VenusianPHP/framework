---
type: Playbook
title: Local development
description: How to install and run the Venusian 0.8.x working tree. Pest v4 is the suite; CI runs vendor/bin/pest on PHP 8.4 and 8.5.
tags: [development, testing, pest, composer, onboarding]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: root-composer
    resource: ../../composer.json
    title: venusian/framework composer.json
  - id: tests-yml
    resource: ../../.github/workflows/tests.yml
    title: GitHub Actions tests workflow
  - id: phpunit-xml
    resource: ../../phpunit.xml
    title: PHPUnit / Pest suite config
  - id: pr1-ci
    resource: https://github.com/VenusianPHP/framework/actions/runs/32527823474
    title: PR 1 tests workflow — 6257 passed
---

# Context

You are working on `venusian/framework` **0.8.0**, a Laravel-like framework for
windowed apps and hardware ICs. Most of `src/Voyager` is a port of
`laravel/framework@v12.67.0`'s generic surface. See [overview](/overview.md).

# Prerequisites

- PHP `8.4` or `8.5`.[^root-composer]
- Extensions CI installs: `dom`, `curl`, `libxml`, `mbstring`, `intl`, `zip`,
  `pdo`, `pdo_sqlite`, `pdo_mysql`, `gmp`.[^tests-yml]
  `intl` is required by `Number`. `pdo_sqlite` is required by Database
  `:memory:` tests.
- Composer 2.
- `pestphp/pest-plugin` is already allowed in `config.allow-plugins`.

# Install

```bash
composer install
```

`composer.lock` is gitignored, so every install resolves fresh against
`minimum-stability: dev` with `prefer-stable: true`.[^root-composer]

# Run the tests

```bash
vendor/bin/pest
```

That is the command CI runs on PHP 8.4 and 8.5 after `actions/checkout@v5`
and `composer update`.[^tests-yml]

PR 1 (merge `8a8600f`) reported **6257 passed**, 12 skipped, 7 deprecated,
14 notices, 18843 assertions on both versions.[^pr1-ci] Do not cite 184 tests.

`phpunit.xml` defines one suite (`Framework`) over `./tests` with
`suffix="Test.php"`, `failOnWarning` and `failOnRisky`. Every
`tests/**/deferred/` directory is excluded.[^phpunit-xml]

`tests/Pest.php` does not bind a default TestCase. It extends
`toAcceptIterables`, resets `Sleep` and UUID generation in `afterEach`, and
defines `nativeStringable()`.

Leftover PHPUnit `TestCase` classes in Database / Queue / Notifications /
Broadcasting still run through this command. Other packages are Pest v4
closures. See [known gaps](/known-gaps.md).

# Smoke test

```bash
php -r 'require "vendor/autoload.php";
  echo collect([1,2,3])->map(fn($n) => $n * 2)->sum(), PHP_EOL;
  echo Voyager\NutsAndBolts\DataObjects\Str::slug("Hello Venusian World"), PHP_EOL;
  echo Voyager\NutsAndBolts\DataObjects\Number::currency(1234.5), PHP_EOL;'
```

Expected: `12`, `hello-venusian-world`, `$1,234.50`.

`now()` is safe to call; it no longer fatals.

# Adding a class

1. Put the file in `src/Voyager/<Package>/`.
2. Namespace by package (`Voyager\<Package>\`) or, for the NutsAndBolts family,
   by role (`DataObjects\`, `Concerns\`, `Contracts\`). See
   [namespace and autoloading](/architecture/namespace-and-autoloading.md).
3. Keep leaf filenames unique across Macroable / Collections / Conditionable /
   Reflection.
4. A new global helper file belongs in root `autoload.files` *and* the
   sub-package manifest, then `composer dump-autoload`.
5. A cross-package dependency belongs in the sub-package manifest — the
   monorepo autoloader will not tell you that you forgot.

# Where the knowledge lives

This `.okf/` bundle is export-ignored. `README.md` states the product;
`AGENTS.md` is the working contract. Update this bundle when the tree
changes — [maintaining this knowledge bundle](maintaining-this-bundle.md).

[^root-composer]: venusian/framework composer.json
[^tests-yml]: GitHub Actions tests workflow
[^phpunit-xml]: PHPUnit / Pest suite config
[^pr1-ci]: PR 1 tests workflow — 6257 passed
