---
type: Convention
title: Port hazards — type hints added to ported Laravel code
description: Adding strict PHP type hints to Laravel code written for untyped parameters silently narrows behaviour; the failure is a wrong answer, not an error.
tags: [porting, type-hints, php, laravel, bugs, review]
status: draft
generated: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: audit
    resource: runtime audit of Str, Collection, EnumeratesValues and LazyCollection against ../../vendor/autoload.php on 2026-08-19
    title: Type-hint narrowing audit
  - id: str-source
    resource: ../../src/Voyager/NutsAndBolts/DataObjects/Str.php
    title: Str source
  - id: lazy-source
    resource: ../../src/Voyager/Collections/LazyCollection.php
    title: LazyCollection source
  - id: wave5
    resource: ../../tests/Notifications and ../../tests/Bus run against the wave 5 port on 2026-08-20
    title: Wave 5 Queue, Broadcasting and Notifications port
  - id: wave4
    resource: ../../tests/Validation/ValidationInArrayKeysTest.php run against ../../src/Voyager/Collections/DataObjects/Arr.php on 2026-08-20
    title: Wave 4 Validation port
---

# Still live after PR 1

PR 1 (`fb9c2d9`) was another pass over this hazard — over-narrow hints that
broke Pest on 8.4/8.5. The pattern has not gone away.
`Bus\DatabaseBatchRepository::find(): ?Batch` still falls off the end with
no `return null` (see [known gaps](/known-gaps.md)). That is the same
"declared return type turns an implicit null into a fatal" case as the
wave-5 `Batchable::batch()` row below.

# The hazard

Laravel's Support code is largely **untyped by design**: `Str::is($pattern, $value)`,
`Str::contains($haystack, $needles)`, `LazyCollection::make($source)`. The bodies
do their own runtime narrowing — `is_iterable()`, `instanceof Traversable`,
`(string)` casts — so they accept arrays, Collections, generators, and stringable
objects alike.

The Venusian port added PHP type hints to those signatures. Where a hint is
narrower than what the body handles, **PHP's coercion rules turn a type error
into a wrong answer**:

> In non-strict mode, an object with `__toString()` passed to a `string` or
> `array|string` parameter is silently coerced to its string form. It never
> reaches the body. No error, no warning.

`Collection` has `__toString()` returning JSON. So passing a `Collection` of
patterns to an `array|string $pattern` parameter converts it to the literal
string `["a*","b*"]` and matches *that* — returning `false` forever.[^audit]

The tell is **defensive code in the body that can never fire**. If a method
checks `is_iterable($x)` or `$x instanceof Traversable` but its signature says
`array|string`, the check is dead and the hint is wrong.[^str-source]

# Why it is worse than a crash

A `Generator` or `ArrayObject` hitting the same parameter throws `TypeError`
immediately — they have no `__toString()`. Only the **stringable** types fail
silently, and `Collection` is both the most idiomatic thing to pass and the one
that fails quietly.[^audit] Loud failures got found; the quiet one did not.

# Audit, 2026-08-19

Thirteen `Str` signatures declared `array|string` (or bare `array`) over bodies
already written for iterables. All were widened to `iterable|string`, which is
what the bodies expected all along:[^str-source]

`chopStart`, `chopEnd`, `contains`, `containsAll`, `doesntContain`, `endsWith`,
`doesntEndWith`, `startsWith`, `doesntStartWith`, `is`, `isMatch`, `replace`,
`remove`.

`chopStart` and `chopEnd` additionally used `(array) $needle`, which on an object
yields its *properties*, not its items; both now normalise explicitly.[^str-source]

`LazyCollection::make()` was declared `Arrayable|iterable|null` while its own
`__construct` accepts `Closure` — so `new LazyCollection(fn () => yield ...)`
worked and the documented `LazyCollection::make(fn () => yield ...)` threw.
Widening a parameter in an override is contravariant and legal.[^lazy-source]

`Arr::first()` and `Arr::last()` typed `$default` as `?Closure`, although the
value is handed to `value()`, which returns non-closures unchanged. The plain
default Laravel accepts threw a `TypeError`. Now `mixed`.[^audit]

Two `chunk()` defects came from the same porting pass but are not type-hint
narrowing: `$preserveKeys` was declared with no default across the `Enumerable`
contract and both implementations, making the ordinary `chunk($size)` call a
fatal; and `LazyCollection::chunk()` accepted the argument without capturing it
in its generator closure, so it was silently ignored.[^audit]

A related but distinct case is the `Stringable` name-resolution bug in
Collections — see [known gaps](/known-gaps.md).

# Second audit, wave 0

Porting `Illuminate\Container` and the missing `Illuminate\Support` classes hit
the same hazard three more times, all in code that was already here:[^audit]

| Site | Narrowed to | Body actually handles |
|------|-------------|-----------------------|
| `Collection::__construct`, `Enumerable::make`, `LazyCollection::__construct`/`make`, `collect()` | `Arrayable\|array\|null` | `getArrayableItems()` explicitly branches on `is_scalar()` and `UnitEnum` |
| `Str::of()`, `Stringable::__construct` | `string` | body coerces with `(string)`, so Laravel accepts null |

Both were found by upstream tests calling the methods the way Laravel documents —
`collect('hello')` and `Str::of(null)` — not by reading the code. The tell was the
same each time: **defensive code in the body that the signature makes
unreachable**.

A fourth variant appeared in the hand-written contracts, running the other way:
`Voyager\Contracts\Vessel\Vessel` declares return types that Laravel's
implementation lacks. Return types are covariant, so the *implementation* had to
adopt them — and upstream test doubles implementing those contracts needed their
signatures updated too. Typed contracts are worth having; just budget for the
implementations and the test doubles to follow.

# Third audit, wave 4

Porting `Validation` hit the hazard once more, in code already here:[^wave4]

| Site | Narrowed to | Body actually handles |
|------|-------------|-----------------------|
| `Arr::exists()` | `float\|int\|string $key` | `if (is_float($key) \|\| is_null($key)) { $key = (string) $key; }` |

The tell was again defensive code the signature made unreachable — the body's
own `is_null($key)` branch could never run. Laravel leaves the parameter
untyped and documents it as `string|int|float`, so the docblock alone would not
have caught it; the *body* is the authority. Widened to
`float|int|string|null`.

Found by `ValidationInArrayKeysTest`, where `in_array_keys` passes each rule
parameter through `Arr::exists()` and a `null` parameter is ordinary input. Two
waves of Collections tests had not reached it, which is the argument for porting
each component's upstream tests rather than trusting the ones already green.

# Fourth audit, wave 5

Wave 5 hit the hazard four more times, all in code already here, and all found
by running the port rather than reading it:[^wave5]

| Site | Narrowed to | What broke |
|------|-------------|------------|
| `Macroable::__call`/`__callStatic` | `string $method, array $parameters` | Incompatible with the untyped `Manager::__call` it overrides — any class extending `Manager` and using `Macroable` is a fatal. `ChannelManager` was the first. |
| `Bus\Batch::$createdAt`/`$cancelledAt`/`$finishedAt` | `CarbonImmutable` | Laravel types the *constructor parameters* but leaves the properties untyped on purpose, so a subclass may assign any Carbon flavour. `BatchFake::cancel()` assigns a mutable `Carbon`. |
| `Bus\BatchRepository::find()` | `: ?Batch` | Laravel's own `BusBatchableTest` mocks the repository returning a sentinel string. |
| `Bus\Batchable::batch()` | `: mixed` | Upstream falls off the end and returns null implicitly. Under a declared return type that is a fatal, not a null. |

The last one is a variant worth naming on its own: **a declared return type turns
an implicit null return into a fatal.** Laravel leans on falling off the end;
`mixed` does not permit it. Every method given a return type needs its
fall-through path checked, and the failure only appears when that path runs.

There is a second lesson in the first row. Two sweeps typed two files
independently — `Macroable` got types, `Manager` did not — and the
incompatibility only exists where a class composes both. Typing is not a
per-file decision.

# Rules for future ports

1. **Port the signature as loosely as the body.** If the body calls
   `is_iterable()`, the hint must admit iterables. Tighten only after the body
   is tightened too.
2. **Treat unreachable defensive code as a signature bug**, not as dead weight to
   delete. It is documenting what the parameter used to accept.
3. **Test each `array|string` parameter with a `Collection`.** It is the one
   input that returns a wrong answer instead of throwing.
4. **Call every method the way Laravel documents it.** Three further defects
   surfaced the moment a test used the documented arity — a required parameter
   Laravel defaults, and a default value Laravel accepts. The test suite is the
   cheapest place to find these.
5. **Prefer `iterable` over `array`** for anything Laravel documented as
   accepting "an array of ...". Callers pass Collections.
6. **Never `(array)`-cast a parameter that may be an object.** Use
   `iterator_to_array()` on the iterable branch.

# Related

- [Laravel lineage](laravel-lineage.md) — the port this hazard comes from.
- [Known gaps](/known-gaps.md) — the individual defects and their status.
- [Local development](/playbooks/local-development.md) — how to run the regression sweep.

[^audit]: Type-hint narrowing audit
[^str-source]: Str source
[^lazy-source]: LazyCollection source
