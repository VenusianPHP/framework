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
  - id: str-test-port
    resource: ../../tests/NutsAndBolts/StrTest.php (ported from upstream SupportStrTest.php) run against ../../src/Voyager/NutsAndBolts/DataObjects/Str.php on 2026-08-21
    title: SupportStrTest port
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

# Fifth audit, SupportStrTest port

Porting Laravel's own `SupportStrTest.php` (2,004 lines, 115 test methods)
into `tests/NutsAndBolts/StrTest.php` hit the hazard again, this time across
almost the whole `Str` search/substring family. Upstream `Str::startsWith`,
`endsWith`, `doesntStartWith`, `doesntEndWith`, `contains`, `containsAll`,
`doesntContain`, `is`, `isMatch`, `before`, `beforeLast`, `after`, `afterLast`,
`between`, `betweenFirst`, `excerpt`, `replaceFirst`, `replaceStart`,
`replaceLast`, `replaceEnd`, `ascii`, `isAscii`, `slug`, and `numbers` are all
**completely untyped** in Laravel — no parameter or return hints at all.[^str-test-port]
The Venusian port had typed every one of them to `string`/`iterable|string`,
narrower than what their own bodies anticipated:[^str-test-port]

| Site | Narrowed to | Body actually handles |
|------|-------------|-----------------------|
| `startsWith`, `doesntStartWith`, `endsWith`, `doesntEndWith`, `contains`, `containsAll`, `doesntContain` | `string $haystack`, `iterable\|string $needles` | Each body opens with `if (is_null($haystack)) return false;` — dead code under a non-nullable hint — and upstream's own test passes `null`, ints, and floats for both haystack and needles (`Str::startsWith(null, 'Marc')`, `Str::startsWith('0123', 0)`, `Str::startsWith(7.123, '7.12')`) |
| `is`, `isMatch` | `string $value` | Body opens with `$value = (string) $value;` — the cast is the tell — and the test passes `Str::is([null], null)` and `Str::is('', 0)` |
| `before`, `beforeLast`, `after`, `afterLast` | `string $search` | Test passes an int search value (`Str::before('han0nah', 0)`, `Str::afterLast('yv0et0te', 0)`); `before()`'s body already does `strstr($subject, (string) $search, true)` |
| `between`, `betweenFirst` | `string $from, string $to` | Test passes ints (`Str::between('12345', 1, 5)`); widened to match `before`/`after`, which they call internally |
| `excerpt` | `string $text`, `string $phrase = ''` | Body casts both with `(string) $text` / `(string) $phrase`; test calls `Str::excerpt(null)` and `Str::excerpt('...', null, [...])` |
| `replaceFirst`, `replaceStart`, `replaceLast`, `replaceEnd` | `string $search` | All four bodies open with `$search = (string) $search;` — the cast is unreachable under the old hint; test passes `Str::replaceFirst(0, '1', '0')` |
| `ascii`, `isAscii`, `slug` | non-nullable `string` | Bodies cast with `(string) $value`; test calls `Str::ascii(null)`, `Str::isAscii(null)`, `Str::slug(null)`, all expecting `''`/`true` rather than a `TypeError` |
| `numbers` | `string $value` → `string` | Body is a bare `preg_replace('/[^0-9]/', '', $value)`; `preg_replace` returns an array when given an array subject, and the test asserts `Str::numbers($arrayOfStrings)` returns an array |

Fixed by widening to `float|int|string|null` (haystack/search/from/to family),
`mixed` (`is()`/`isMatch()`'s `$value`), `?string` (`excerpt`, `ascii`,
`isAscii`, `slug`), and `array|string` in both directions (`numbers`).[^str-test-port]

A sixth, different-shaped bug turned up in `Str::uuid()` and its five
siblings (`uuid7`, `orderedUuid`, `freezeUuids`, `ulid`, `freezeUlids`). A
prior pass (see "Audit, 2026-08-19" above) had already widened their return
type from `UuidInterface` to `UuidInterface|string` after finding that a
user-installed factory can return a plain string. This port's test installs
a factory returning a `Stringable` *object* instead
(`Str::createUuidsUsing(fn () => Str::of('1234'))`) and then calls
`Str::uuid()->toString()`. `Stringable` implements `__toString()`, so PHP's
weak-mode return-type coercion silently converted the returned object to a
plain string to satisfy the `UuidInterface|string` union — the same
"stringable object hits a type boundary and gets silently flattened" failure
mode as the original `Collection`/`array|string` hazard this document opened
with, just on a return type instead of a parameter. `->toString()` on the
resulting plain string is a fatal, not a wrong answer, which is how it
surfaced. Laravel's own `Str::uuid()` has no return type at all — the
factory's result is meant to pass through completely unchanged, whatever it
is. Fixed by widening all six to `: mixed`, removing the coercion entirely
rather than trying to enumerate every shape a factory might return.[^str-test-port]

Every caller in `src/` already does `(string) Str::uuid()` (or the `ulid`/
`orderedUuid` equivalents) at the call site, so none needed to change.[^str-test-port]

Two items flagged in the task brief for this port turned out to be one real
bug and one non-bug: `Str::is()`'s `string $value` parameter did reject a
`null` value (fixed above, to `mixed`), but the `foreach ($pattern as
$pattern)` variable shadow in `is()`/`isMatch()` is not a defect — it is
copied verbatim from Laravel's own source, which passes its own equivalent
test, so it was left alone.

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
[^str-test-port]: SupportStrTest port
