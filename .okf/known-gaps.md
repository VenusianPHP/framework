---
type: Known Issues
title: Known gaps in Venusian v0.8.0
description: Defects in the ported Support foundation, each confirmed by running the code — what was fixed on 2026-08-19 and what remains open.
tags: [defects, technical-debt, php, porting]
status: draft
generated: { by: claude-code/claude-opus-5, at: 2026-08-19T23:00:00Z }
stale_after: 2026-11-19
sources:
  - id: audit
    resource: runtime audit and regression sweep against ../vendor/autoload.php on 2026-08-19
    title: Runtime audit, 37-check sweep, and the 184-test Pest suite
  - id: src-tree
    resource: every PHP file under ../src/Voyager
    title: Framework source tree
  - id: subpackage-manifests
    resource: ../src/Voyager/*/composer.json
    title: Per-package composer manifests
  - id: ref-0-7-x
    resource: /Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/ScrapyardIO/framework/src/Fabricate
    title: 0.7.x reference implementation (Fabricate namespace)
    author: human:angel
  - id: wave5
    resource: ../src/Voyager/Queue, ../src/Voyager/Broadcasting and ../src/Voyager/Notifications, ported 2026-08-20
    title: Wave 5 port
  - id: wave4
    resource: ../src/Voyager/Validation and ../tests/Validation, ported 2026-08-20
    title: Wave 4 Validation port
  - id: agents-md
    resource: ../AGENTS.md
    title: Agent guidelines — venusian/framework
    author: human:angel
    last_modified: 2026-08-19
---

# Overview

Everything here was reproduced against the working tree with
`vendor/autoload.php` loaded, not inferred from reading source.[^audit] Baseline
health is good: all 26 declarations resolve and all 27 global helpers are
defined.[^audit]

**Scope note.** This covers defects in what has been built. The absence of a
container, kernel, router, or console is *not* listed — Venusian is at the
[Support-foundation stage](/overview.md) by design. Likewise no cross-package
import violates the layering rule; see
[dependency direction](/architecture/dependency-direction.md).

# Fixed on 2026-08-19

All verified by a 37-check regression sweep covering both the fixed paths and
ordinary inputs.[^audit] The repo has no test suite, so that sweep is currently
the only regression evidence — see [local development](/playbooks/local-development.md).

## `Stringable` resolved to the wrong class in three Collections sites

Three sites tested `$x instanceof Stringable` intending PHP's **native**
`\Stringable`, and resolved elsewhere:[^src-tree]

| Site | Resolved to | Effect |
|------|-------------|--------|
| `Collection.php` `groupBy` | `Voyager\NutsAndBolts\DataObjects\Stringable` (imported) | `TypeError` on a native-`\Stringable` group key |
| `Collection.php` `implode` | same import | Wrong branch; returned `''` |
| `EnumeratesValues.php` `where` | `Voyager\NutsAndBolts\Concerns\Stringable` — **no import, class does not exist** | Match arm dead; comparison silently failed |

Voyager's `Stringable` implements native `\Stringable`, so the imported name was
strictly narrower — every plain `__toString()` object stopped matching. The third
site had no `use` at all, so the unqualified name resolved against the file's own
namespace to a missing class; `instanceof` against a missing class is just
`false`, so nothing errored.

Before the fix: `implode(', ')` returned `''` instead of `'a, b'`; `groupBy` threw
`TypeError`; `where` matched 0 rows instead of 1.[^audit] All three sites now use
`\Stringable` explicitly and the shadowing import is gone. The repo's own
`Contracts/Enumerable.php` documents the key as `array-key|\UnitEnum|\Stringable`,
fully qualified, corroborating that native was always the intent.[^src-tree]

## Thirteen `Str` signatures silently coerced `Collection` arguments

`array|string` hints over bodies already written for iterables. A `Collection`
has `__toString()`, so it was coerced to its JSON form and matched as a single
literal pattern — `Str::is(collect(['a*','b*']), 'bat')` returned `false` with no
error.[^audit] Widened to `iterable|string`, which is what the bodies expected.
`chopStart`/`chopEnd` also `(array)`-cast an object (yielding its properties, not
its items) and now normalise explicitly.

This is a systemic class, not three one-offs — see
[port hazards](/architecture/port-hazards.md).

## `LazyCollection::make()` rejected a `Closure`

`__construct` accepted `Closure`, `make()` did not, so the documented
`LazyCollection::make(fn () => yield ...)` form threw `TypeError` while
`new LazyCollection(...)` worked.[^audit] Widened; generators are still rejected
with the original `InvalidArgumentException`, as intended.

## `Str::singular()` did not exist

`Stringable::singular()` called `Str::singular()`, which was never
defined — so both `Str::singular('users')` and `Str::of('users')->singular()`
threw `BadMethodCallException` via the `Macroable` fallback.[^audit]
`Pluralizer::singular()` existed all along; `Str::singular()` now forwards to it.

## `now()` fatalled, and `time.php` guards could never fire

`now()` delegated to `Voyager\NutsAndBolts\MagicAliases\Date::now()`, which does
not exist in this tree.[^src-tree] Separately, all ten guards in the file tested
`function_exists('Voyager\NutsAndBolts\<fn>')` while the file has no `namespace`
declaration, so it actually declares **global** functions — the guards could
never suppress a redefinition, and a second include would fatal on duplicate
declaration.[^audit] The file also called `enum_value()` unqualified from the
global namespace, where it does not resolve.

Guards now name the global functions, `enum_value` is imported, and `now()` is
wired to `Carbon::now()` **as an interim measure** with the seam noted in
source. `MagicAliases` is the facade layer and needs a container to exist first —
see [the 0.7.x reference](/reference/upstream-0-7-x.md).

## `ReflectsClosures` was a class where a trait was intended

Declared `class` with all-protected methods, giving it zero public surface and
making `use ReflectsClosures;` fail to compile.[^audit] Settled against the
0.7.x tree, which declares it a `trait` — a port regression, not a
redesign.[^ref-0-7-x] Now a trait; verified by composing it and calling
`firstClosureParameterType()`.

## Three more defects, found by writing the tests

Each surfaced the moment a test called the method the way Laravel documents.[^audit]

**`Arr::first()` and `Arr::last()` typed `$default` as `?Closure`.** The value is
passed through `value()`, which returns non-closures unchanged, so a plain
default is valid — but the hint rejected it. `Arr::first([1,2], $callback, 'none')`
threw a `TypeError` instead of returning `'none'`. Now `mixed`.

**`chunk()` declared `$preserveKeys` with no default.** In the `Enumerable`
contract and both implementations, so the ordinary `chunk($size)` call was a
fatal, not a divergence. Defaulted to `true`, matching `array_chunk` and Laravel.

**`LazyCollection::chunk()` ignored `$preserveKeys` entirely.** It accepted the
argument but never captured it in the generator closure, so it always preserved
keys. That also silently broke `splitIn()`, its only internal caller, which
passes `false`.

The first is the same type-hint narrowing as the `Str` family; see
[port hazards](/architecture/port-hazards.md).

## `Pluralizer` was a stub — now the real inflector

Its docblock deferred a Doctrine inflector port, and `doctrine/inflector` was not
installed, so the stub was load-bearing: 20 of 24 common words were wrong
(`child` → `childs`, `box` → `boxs`, `bus` unchanged).

`doctrine/inflector ^2.0` turns out to be a declared requirement of
`illuminate/support`, so it came in as part of the wave-0 port rather than as a
separate decision. The upstream `Pluralizer` replaced the stub: **24/24 plural
and 8/8 singular** cases now correct.[^audit]

# Open

## Wave 5 ships driver and boundary cuts

Recorded so nobody "restores" them from upstream by mistake.[^wave5]

**Queue** — Beanstalkd and SQS connectors, their queues and jobs, and the
DynamoDB failed-job provider are cut by the driver policy (no AWS bar S3). The
`database` driver's source is kept and registered but inert: nothing resolves it
without a `db` binding, so it lights up when Database lands in wave 6.
`config/queue.php` therefore defaults to `sync`, and the failed-job driver to
`file`, rather than Laravel's `database` and `database-uuids`.

**Broadcasting** — Ably is cut by the driver policy. The channel-authorization
surface is cut by the HTTP boundary rule: `auth()` and
`validAuthenticationResponse()` from the `Broadcaster` contract and every
broadcaster, `BroadcastController`, and `routes()`/`userRoutes()`/
`channelRoutes()`/`socket()` on `BroadcastManager`. Authenticating an incoming
request for a channel is receiving-HTTP. The send path is untouched.

**Notifications** — `Channels\MailChannel`, `Messages\MailMessage`, its
`Messages\SimpleMessage` base and the `resources/views/email.blade.php` template
are cut with Mail, and `NotificationServiceProvider::boot()` no longer registers
a Blade view namespace. `ChannelManager`'s default channel is **`database`**
rather than Laravel's `mail`.

Four of Laravel's Broadcasting test files and two of its Notifications test
files cover only cut surface, so they are cut rather than deferred — see the
READMEs in `tests/Broadcasting` and `tests/Notifications`.

## Validation ships three deliberate cuts

Recorded so nobody "restores" them from upstream by mistake.[^wave4]

| Cut | Why |
|-----|-----|
| `Rules\Can` and `Rule::can()` | Its whole body is one `Gate::allows()` call. Auth is out of scope for this port, and there is no `Gate` magic alias — nothing is left of the class once the Gate goes. |
| `ValidationException::$response`, `$status`, `$redirectTo`, `status()`, `redirectTo()`, `getResponse()` | These shape an HTTP response. Cut per the HTTP boundary rule; `errors()`, `errorBag()` and `withMessages()` stay. Nothing in the tree read them. |
| The precognition branch of `ValidatesWhenResolvedTrait::validateResolved()` | Precognition is an HTTP request feature, and `isPrecognitive()` came from the `CanBePrecognitive` trait that the `Http` port already cut, so the branch was dead. |

The `exists`/`unique` rules are **not** cut. They guard on
`Instrument\Model` with `instanceof`/`is_subclass_of`, which is simply false
until wave 6 lands the class, and `ValidationServiceProvider` only wires the
presence verifier when `db` is bound. They light up on their own.

## Validation's file rules have no upload value object

`file`, `image`, `mimes` and `dimensions` are ported and work against Symfony's
`File`/`UploadedFile`. Laravel's tests for them build subjects with
`Illuminate\Http\UploadedFile::fake()`, and `Http` is ported as the client only —
neither `Http\UploadedFile` nor `Http\Testing\FileFactory` exists here, both
being part of *receiving* a request.[^wave4]

So three upstream test files sit in `tests/Validation/deferred/` unrun. Reviving
them means first deciding what a Venusian file-validation subject is — a design
question, not a port step. Until then the rules are ported but uncovered.

## `voyager/contracts` is declared but not yet built

The root manifest `replace`s it, but there is no `src/Voyager/Contracts/`
directory, so publishing it today would ship nothing. **Planned work, not a
defect** — listed so nobody "fixes" it by deleting the entry. It is the
framework-wide interface package for System and the components, Venusian's
`illuminate/contracts`. The foundation's own interfaces stay inside the
NutsAndBolts family by design. See
[dependency direction](/architecture/dependency-direction.md).

## `TransformsToResourceCollection` is an empty trait

Declared and `use`d by `Collection`, body empty.[^src-tree] A placeholder for a
layer not yet written. Worth a decision rather than a fix: the name comes from
Laravel's web-facing API-resource concept, which a CLI-and-sketch framework may
not want at all.

## Sub-package manifests are stale — but the `fabricate/*` names are not typos

The five per-package manifests have drifted from the root:[^subpackage-manifests]

| Manifest | Declares | Problem |
|----------|----------|---------|
| `Collections/` | `fabricate/macroable ^0.8.0` | Stale **0.7.x package name**; now `voyager/macroable` |
| `Reflection/` | `fabricate/collection ^0.6\|^0.7` | Stale 0.7.x name; now `voyager/collections` |
| `NutsAndBolts/` | `voyager/macroable ^0.7.0`, `voyager/collections ^0.7.0` | Pinned to 0.7 while the monorepo is 0.8.0 |
| `NutsAndBolts/` | `symfony/polyfill-php86 ^8.0.0` | Root declares `^1.34`; no `8.x` exists |
| `Reflection/` | `branch-alias: 0.7.x-dev` | The other four say `0.8.x-dev` |

The `fabricate/*` entries are **rename leftovers**, not mistakes — 0.7.x really
was namespaced `Fabricate`.[^ref-0-7-x] They still need updating, but the fix is
"finish the rename", not "correct a typo".

These are invisible in the monorepo — the root autoloader covers everything — and
only bite on the first standalone package split.

## `Str::is()` no longer accepts a null `$value`

`$value` is typed `string`, so `Str::is('foo*', null)` throws `TypeError` where
Laravel coerces null to `''` and returns `false`.[^audit] Arguably an improvement
over Laravel; recorded because it is a real behavioural divergence for anyone
porting calling code.

## `foreach ($pattern as $pattern)` in `Str::is()` and `Str::isMatch()`

The loop variable shadows the array being iterated. **Not a bug** — PHP iterates a
by-value copy, verified with multi-element patterns reaching elements 2 and
3.[^audit] Left as-is because it appears to be verbatim from Laravel and
gratuitous divergence costs more than the ugliness. Flagged so the next reader
does not re-investigate it. Adjacent vestigial bits in the same method: a
`(string)` cast on an already-`string` parameter, and an `is_iterable()` check
that predates the type hint.

## `config/` is still empty

`tests/` and `.github/` are now populated; `config/` remains an empty directory
with nothing reading from it. Harmless, but it implies a configuration layer that
does not exist yet.

## No record of the upstream Laravel revision ported from

Nothing records which `laravel/framework` revision the Support code corresponds
to, so there is no mechanical way to tell which upstream bugfixes are in. The
[0.7.x reference implementation](/reference/upstream-0-7-x.md) answers
*Venusian*-generation questions but not *Laravel*-generation ones. See
[Laravel lineage](/architecture/laravel-lineage.md).

## Fixed: the port missed `Foundation` -> `System` in two stack-trace regexes

`tests/System/Bootstrap/HandleExceptionsTest.php` matched stack frames against
`Voyager\\Foundation\\Bootstrap\\HandleExceptions`. The root `Illuminate` ->
`Voyager` rename was applied to those regexes but the `Foundation` -> `System`
half was not, and `Voyager\Foundation` exists nowhere in `src`, so the `m::on()`
matcher could never fire.

The failure was invisible for three compounding reasons: `warning()` was never
called, `HandleExceptions` swallowed Mockery's `NoMatchingExpectationException`
in its `catch (Throwable) { return; }`, and the file — a plain PHPUnit
`TestCase` with no `MockeryPHPUnitIntegration` — never closed the Mockery
container. The unmet expectation leaked into the global container and surfaced
as an `InvalidCountException` against whichever *Pest* test next closed it,
which is why it appeared to be a Wave 0 defect.

Fixed during the Wave 2 conversion on 2026-08-21. Two permanently-broken cases
now pass. **The lesson generalises**: `grep -rn 'Illuminate' src/Voyager/<X>`
returns empty for a half-renamed `Voyager\Foundation`, so it does not prove the
namespace rewrite is complete. Grep for the *old component names* too.

## `Suit` enum lengths in `tests/Log/deferred/ContextTest.php` are stale

The serialized-enum literals still carry the byte lengths of the upstream
`Illuminate\Tests\Log\…` namespace — `E:31:"Tests\Log\Fixtures\Suit:Clubs"`
where the new name is 29 bytes, and `43` where it is 41. Harmless today because
the file is deferred and never runs; it will fail the moment Log's deferred
tests are restored.

## Waves 0-2 are Pest v4; waves 3-6 are still PHPUnit

`tests/Vessel`, `tests/NutsAndBolts` (wave 0), `tests/Config`, `tests/Pipeline`,
`tests/Encryption`, `tests/Hashing`, `tests/JsonSchema` (wave 1), `tests/System`,
`tests/Console` and `tests/Log` (wave 2) were converted from upstream PHPUnit
classes to Pest v4 on 2026-08-21. Inline stub classes moved to
`tests/<Component>/Fixtures/` under `Tests\<Component>\Fixtures`, one class per
file (PSR-4). Two exceptions load through `autoload-dev.files` because PSR-4
cannot carry them: `tests/Vessel/Fixtures/functions.php` and
`tests/NutsAndBolts/Fixtures/functions.php` hold plain functions, and
`tests/Vessel/Fixtures/AttributeTargets.php` holds classes whose upstream names
end in `Test` and would otherwise be collected as test files.

Wave 2 added two wrinkles worth knowing before converting waves 3-6:

* **`tests/System/Stubs/`, not `Fixtures/`.** `tests/System/fixtures/` already
  exists in lowercase, and the dev volume is case-insensitive — `Fixtures` and
  `fixtures` resolve to the same inode, so git would record the classes under
  the lowercase path and PSR-4 would fail on a case-sensitive CI box.
* **Pest counts a fulfilled Mockery expectation as an assertion** and closes the
  container after every test. Converting a file therefore raises its assertion
  count and turns "risky, no assertions" cases into honest passes, without any
  case being added. Wave 2 went 534 -> 698 assertions for this reason alone
  while the suite-wide total did not move.

Every other `tests/<Component>` directory still holds PHPUnit classes and is
converted wave by wave.

[^audit]: Runtime audit and 37-check regression sweep
[^src-tree]: Framework source tree
[^subpackage-manifests]: Per-package composer manifests
[^ref-0-7-x]: 0.7.x reference implementation (Fabricate namespace)
[^agents-md]: Agent guidelines — venusian/framework
